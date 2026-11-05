<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Inventory;
use App\Models\InventoryTransaction;
use App\Models\InventoryStockSnapshot;
use App\Services\BackupService;
use App\Services\StockOpnameService;
use App\Models\ImportBatch;
use App\Services\RekonsiliasiService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use App\Support\HargaExcel;

/**
 * Import file Excel ERP (sheet master stok + Receipt + Issue).
 *
 * Aman untuk data historis:
 * - Stok & harga saat ini HANYA ditimpa bila tanggal stok file >= snapshot terakhir.
 *   File lama tetap disimpan sebagai snapshot historis + transaksinya masuk.
 * - Setiap baris punya source_ref (kunci alami dari ERP), jadi file yang sama /
 *   periode yang tumpang tindih boleh diimport berulang tanpa dobel.
 * - Part yang tidak ada di master dibuat otomatis (deskripsi/bin/harga dari Issue & Receipt)
 *   supaya biaya historis & pembelian part baru tidak hilang. Stoknya dihitung dari
 *   transaksi setelah tanggal stok master terakhir (part lama -> 0).
 */
class ImportController extends Controller
{
    /** @var Worksheet[] sheet berformat stock take (diisi cariSheetMaster) */
    private array $sheetStockTake = [];

    public function index()
    {
        $batches = ImportBatch::with('pengunggah:id,name')->latest()->paginate(15);

        $cakupan = DB::table('inventory_transactions')
            ->whereIn('source_type', ['receipt', 'issue'])
            ->selectRaw('MIN(tanggal_transaksi) as awal, MAX(tanggal_transaksi) as akhir, COUNT(*) as jumlah')
            ->first();

        $snapshotTerakhir = InventoryStockSnapshot::max('tanggal_snapshot');

        return view('import.index', compact('batches', 'cakupan', 'snapshotTerakhir'));
    }

    public function storeStockSnapshot(Request $request, RekonsiliasiService $rekon)
    {
        $request->validate([
            'file'         => 'required|file|mimes:xlsx,xls|max:51200',
            'tanggal_stok' => 'nullable|date|before_or_equal:today',
        ]);

        $file = $request->file('file');
        $tanggalStok = Carbon::parse($request->input('tanggal_stok', today()))->startOfDay();
        $snapshotTerakhir = InventoryStockSnapshot::max('tanggal_snapshot');
        $perbaruiMaster = !$snapshotTerakhir || $tanggalStok->gte(Carbon::parse($snapshotTerakhir));

        // Pengaman: stok akan ditimpa, jadi simpan backup dulu. Gagal backup tidak menghentikan impor, tetapi diberitahukan.
        $backupGagal = false;
        if ($perbaruiMaster && config('backup.sebelum_impor', true)) {
            try {
                $backupGagal = !(app(BackupService::class)->jalankan('sebelum-impor')['ok'] ?? false);
            } catch (\Throwable $e) {
                report($e);
                $backupGagal = true;
            }
        }

        $batch = ImportBatch::create([
            'file_type'       => 'master_receipt_issue',
            'file_name'       => $file->getClientOriginalName(),
            'file_hash'       => hash_file('sha256', $file->getPathname()),
            'uploaded_by'     => auth()->id(),
            'status'          => 'processing',
            'tanggal_stok'    => $tanggalStok->toDateString(),
            'perbarui_master' => $perbaruiMaster,
        ]);

        // File & tanggal stok yang sama sudah pernah diimport -> snapshot tidak ditulis dua kali.
        $hash = $batch->file_hash;
        $snapshotSudahAda = ImportBatch::where('id', '!=', $batch->id)->where('status', 'success')
            ->where('file_hash', $hash)->whereDate('tanggal_stok', $tanggalStok)->exists();

        $c = ['total' => 0, 'inserted' => 0, 'skipped' => 0, 'failed' => 0, 'part_baru' => 0, 'snapshot' => 0, 'awal' => null, 'akhir' => null, 'dibuat' => []];

        try {
            @set_time_limit(600);
            ini_set('memory_limit', '1024M');

            $reader = IOFactory::createReaderForFile($file->getPathname());
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($file->getPathname());

            $master = $this->cariSheetMaster($spreadsheet);
            $receipt = $this->cariSheet($spreadsheet, 'receipt');
            $issue = $this->cariSheet($spreadsheet, 'issue');

            if (!$master && !$receipt && !$issue) {
                throw new \RuntimeException(__('No stock master, "Receipt", or "Issue" sheet in this file.'));
            }

            DB::transaction(function () use ($master, $receipt, $issue, $batch, &$c, $tanggalStok, $perbaruiMaster, $snapshotSudahAda) {
                $peta = $this->petaInventory();
                $info = $this->infoPartDariTransaksi($receipt, $issue);

                if ($master) {
                    $this->prosesMaster($master, $batch, $c, $peta, $tanggalStok, $perbaruiMaster, !$snapshotSudahAda);
                }
                if ($receipt) {
                    $this->prosesReceipt($receipt, $batch, $c, $peta, $info);
                }
                if ($issue) {
                    $this->prosesIssue($issue, $batch, $c, $peta, $info);
                }

                $this->hitungStokPartBaru($c['dibuat']);

                $batch->update([
                    'status'        => 'success',
                    'total_rows'    => $c['total'],
                    'inserted_rows' => $c['inserted'],
                    'skipped_rows'  => $c['skipped'],
                    'failed_rows'   => $c['failed'],
                    'part_baru'     => $c['part_baru'],
                    'periode_awal'  => $c['awal'],
                    'periode_akhir' => $c['akhir'],
                ]);
            });
        } catch (\Throwable $e) {
            $batch->update(['status' => 'failed', 'error_message' => mb_substr($e->getMessage(), 0, 1000)]);

            return back()->with('error', __('Failed to process Excel:') . ' ' . $e->getMessage());
        }

        $dicocokkan = $rekon->jalankan();

        $pesan = __('Import finished: :baru new transactions, :lewat duplicates/skipped, :gagal failed.', ['baru' => $c['inserted'], 'lewat' => $c['skipped'], 'gagal' => $c['failed']]);
        if ($c['snapshot']) {
            $pesan .= ' ' . __('Stock snapshot of :n parts.', ['n' => $c['snapshot']]);
        }
        if ($c['awal']) {
            $pesan .= ' ' . __('Transaction period :dari – :sampai.', ['dari' => Carbon::parse($c['awal'])->format('d M Y'), 'sampai' => Carbon::parse($c['akhir'])->format('d M Y')]);
        }
        // Part baru dari file stok sendiri vs. part yang hanya muncul di transaksi Receipt/Issue.
        $dariFile = $c['part_baru_file'] ?? 0;
        if ($dariFile) {
            $pesan .= ' ' . __(':n new parts from this stock file were added.', ['n' => $dariFile]);
        }
        if ($c['part_baru'] - $dariFile > 0) {
            $pesan .= ' ' . __(':n parts missing from the master were created automatically (description, bin & stock from transactions).', ['n' => $c['part_baru'] - $dariFile]);
        }
        if ($backupGagal) {
            $pesan .= ' ' . __('Warning: the automatic backup before this import failed. Check the Backup page.');
        }
        if (!empty($c['waste'])) {
            $pesan .= ' ' . __(':n rows in the WASTE bin were skipped (items to be disposed of are not counted as stock).', ['n' => $c['waste']]);
        }
        if (!empty($c['sebagian'])) {
            $pesan .= ' ' . __('This file covers only part of the warehouse, so parts missing from it were left unchanged.');
        }
        if (!empty($c['dinolkan'])) {
            $pesan .= ' ' . __(':n parts in the M03 warehouse are no longer in this file, so their stock was set to 0 (as in ERP).', ['n' => $c['dinolkan']]);
        }
        if (!empty($c['disesuaikan'])) {
            $pesan .= ' ' . __('Stock of :n parts was corrected for take-outs, returns and receipts recorded in the app after :tgl (not yet in the ERP figure).', ['n' => $c['disesuaikan'], 'tgl' => $tanggalStok->format('d M Y')]);
        }
        if ($master && $snapshotSudahAda) {
            $pesan .= ' ' . __('A stock snapshot for this file & date already exists; it was not rewritten.');
        }
        if ($master) {
            $pesan .= $perbaruiMaster
                ? ' ' . __('Stock & prices updated to the position of :tgl.', ['tgl' => $tanggalStok->format('d M Y')])
                : ' ' . __('The file is older than the latest stock data: current stock was NOT overwritten, only saved as a historical snapshot.');
        }
        if ($dicocokkan) {
            $pesan .= ' ' . __('Reconciliation: :n new scans matched with ERP.', ['n' => $dicocokkan]);
        }

        // Part / laci baru dari impor ini belum punya label barcode: ingatkan untuk dicetak.
        $perluLabel = \App\Models\Inventory::perluLabel()->count();
        if ($perluLabel) {
            $pesan .= ' ' . __('Barcode labels: :n parts still need a label — open Print Labels.', ['n' => number_format($perluLabel, 0, ',', '.')]);
        }

        return redirect()->route('import.index')->with('success', $pesan)
            ->with('label_impor', ['baru' => $c['part_baru_file'] ?? 0, 'part' => $perluLabel, 'laci' => \App\Models\Inventory::laciPerluLabel()->count()]);
    }

    // ================================================================
    // DETEKSI SHEET
    // ================================================================

    /** Sheet master = header A1 "Part" dan C1 mengandung "On Hand" (nama sheet bisa berubah tiap bulan, mis. M0331agt). */
    /**
     * Sheet master stok. Dua format dikenali:
     *  - "erp"        : laporan stok ERP  -> A Part | B Description | C On Hand | ... | F Bin | G-I harga
     *  - "stock_take" : formulir stock take -> A No | B Part | C Description | D On Hand | E Actual | F UOM | G Bin (tanpa harga).
     *    Sheet per rak (M03-A1, M03-B5, ...) hanya salinan Sheet1; semuanya digabung dan baris ganda (part + bin sama) dibuang.
     */
    private function cariSheetMaster($spreadsheet): ?Worksheet
    {
        $this->sheetStockTake = [];
        foreach ($spreadsheet->getWorksheetIterator() as $ws) {
            $a1 = strtolower(trim((string) $ws->getCell('A1')->getValue()));
            $c1 = strtolower(trim((string) $ws->getCell('C1')->getValue()));
            if ($a1 === 'part' && str_contains($c1, 'on hand')) {
                return $ws;
            }
            if (self::kolomStockTake($ws)) {
                $this->sheetStockTake[] = $ws;
            }
        }

        return $this->sheetStockTake[0] ?? null;
    }

    /**
     * Kolom stock take dicari dari NAMA header (bukan posisi), supaya kolom yang bergeser
     * atau bertambah di file bulan berikutnya tidak membuat data terbaca salah.
     * @return array{part: string, deskripsi: string, qty: string, bin: string}|null
     */
    private static function kolomStockTake(Worksheet $ws): ?array
    {
        $kolom = [];
        foreach ($ws->getRowIterator(1, 1) as $baris) {
            foreach ($baris->getCellIterator() as $sel) {
                $nama = strtolower(trim((string) $sel->getValue()));
                $kunci = match (true) {
                    $nama === 'part', $nama === 'part no', $nama === 'part number' => 'part',
                    str_starts_with($nama, 'desc') => 'deskripsi',
                    str_contains($nama, 'on hand') => 'qty',
                    $nama === 'bin' => 'bin',
                    default => null,
                };
                if ($kunci && !isset($kolom[$kunci])) {
                    $kolom[$kunci] = $sel->getColumn();
                }
            }
        }

        return isset($kolom['part'], $kolom['deskripsi'], $kolom['qty'], $kolom['bin']) ? $kolom : null;
    }

    /**
     * Baris master dalam bentuk seragam [part, deskripsi, qty, bin, harga] dari format apa pun.
     * @return array<int, array{0: string, 1: string, 2: int, 3: string, 4: float, 5: array}>
     */
    private function barisMaster(Worksheet $sheet): array
    {
        if (!$this->sheetStockTake) {
            $rows = $sheet->toArray(null, true, false, true);
            array_shift($rows);
            return array_values(array_filter(array_map(fn ($r) => empty($r['A']) ? null
                : [$this->bersihkanPartNo($r['A']), trim((string) ($r['B'] ?? '')), (int) ($r['C'] ?? 0), trim((string) ($r['F'] ?? '')), HargaExcel::dariBarisMaster($r), $r], $rows)));
        }

        $hasil = [];
        $sudah = [];
        foreach ($this->sheetStockTake as $ws) {
            $k = self::kolomStockTake($ws);
            $rows = $ws->toArray(null, true, false, true);
            array_shift($rows);
            foreach ($rows as $r) {
                if (empty($r[$k['part']])) {
                    continue;
                }
                $part = $this->bersihkanPartNo($r[$k['part']]);
                $bin = strtoupper(trim((string) ($r[$k['bin']] ?? '')));
                if (isset($sudah[$part . '|' . $bin])) {
                    continue;   // sheet per rak = salinan Sheet1
                }
                $sudah[$part . '|' . $bin] = true;
                $hasil[] = [$part, trim((string) ($r[$k['deskripsi']] ?? '')), (int) ($r[$k['qty']] ?? 0), $bin, 0.0, $r];
            }
        }

        return $hasil;
    }
    private function cariSheet($spreadsheet, string $nama): ?Worksheet
    {
        foreach ($spreadsheet->getWorksheetIterator() as $ws) {
            if (strtolower(trim($ws->getTitle())) === $nama) {
                return $ws;
            }
        }

        return null;
    }

    // ================================================================
    // BANTUAN
    // ================================================================

    /** part_no => [id, harga] — dimuat sekali supaya tidak query per baris. */
    private function petaInventory(): array
    {
        $peta = [];
        foreach (DB::table('inventories')->get(['id', 'part_no', 'harga_beli_terakhir']) as $r) {
            $peta[$r->part_no] = ['id' => $r->id, 'harga' => (float) $r->harga_beli_terakhir];
        }

        return $peta;
    }

    /**
     * Baris ringkasan/keterangan di sheet master ERP, bukan barang. Contoh yang pernah ikut ter-import:
     *   "Data per 31 augudt 2026" | "Qty :" 3158 | "Jumlah On hand :" 33399.7 | "Total Cost :" ... | "3158" - -
     * Barang asli selalu punya part number berangka tanpa ":" dan punya deskripsi atau lokasi bin.
     */
    public static function barisRingkasan(string $partNo, string $deskripsi, string $lokasi): bool
    {
        $partNo = trim($partNo);
        $kosong = fn ($v) => in_array(trim($v), ['', '-'], true);

        return $partNo === ''
            || str_contains($partNo, ':')
            || !preg_match('/\d/', $partNo)
            || preg_match('/^(data per|qty|jumlah|total|grand total|subtotal|printed|dicetak)\b/i', $partNo)
            || ($kosong($deskripsi) && $kosong($lokasi));
    }

    private function bersihkanPartNo($nilai): string
    {
        return preg_replace('/\.0$/', '', trim((string) $nilai));
    }

    /**
     * Info part dari sheet transaksi, untuk part yang belum ada di master:
     *   - deskripsi  : Issue kolom C (txtPartDescription), semua jenis baris
     *   - bin        : Receipt kolom AD
     *   - harga (Rp) : Issue PUR-STK, txtExtCost (P, USD) / qty x kurs
     */
    private function infoPartDariTransaksi(?Worksheet $receipt, ?Worksheet $issue): array
    {
        $info = [];

        foreach ($issue ? $issue->toArray(null, true, false, true) : [] as $i => $r) {
            if ($i === 1 || empty($r['B'])) {
                continue;
            }
            $p = $this->bersihkanPartNo($r['B']);
            if ($desk = trim((string) ($r['C'] ?? ''))) {
                $info[$p]['deskripsi'] ??= $desk;
            }
            $qty = (float) ($r['L'] ?? 0);
            $ext = HargaExcel::angka($r['P'] ?? null);
            if (trim((string) ($r['R'] ?? '')) === 'PUR-STK' && $qty > 0 && $ext) {
                $info[$p]['harga'] = round($ext / $qty * HargaExcel::KURS_USD_IDR, 2);
            }
        }

        foreach ($receipt ? $receipt->toArray(null, true, false, true) : [] as $i => $r) {
            if ($i === 1 || empty($r['E'])) {
                continue;
            }
            if ($bin = trim((string) ($r['AD'] ?? ''))) {
                $info[$this->bersihkanPartNo($r['E'])]['bin'] ??= $bin;
            }
        }

        return $info;
    }

    /** Part yang muncul di transaksi tapi tidak ada di master: dibuat dari info transaksi (stok dihitung di akhir). */
    private function pastikanPart(string $partNo, ?string $deskripsi, array &$peta, array &$c, array $info = []): array
    {
        if (!isset($peta[$partNo])) {
            $i = $info[$partNo] ?? [];
            $harga = (float) ($i['harga'] ?? 0);
            $inv = Inventory::create([
                'part_no'             => $partNo,
                'deskripsi'           => trim((string) ($deskripsi ?: ($i['deskripsi'] ?? ''))) ?: "Part {$partNo} (dari data historis ERP)",
                'lokasi_rak'          => $i['bin'] ?? '-',
                'stock'               => 0,
                'harga_beli_terakhir' => $harga,
            ]);
            $peta[$partNo] = ['id' => $inv->id, 'harga' => $harga];
            $c['part_baru']++;
            $c['dibuat'][] = $inv->id;
        }

        return $peta[$partNo];
    }

    /**
     * Stok part baru (tidak ada di master) = barang masuk - pemakaian SETELAH tanggal stok master terakhir.
     * Transaksi sebelum tanggal itu diabaikan: part yang sudah tidak ada di master per tanggal
     * tersebut memang stoknya 0, berapa pun pembelian lamanya.
     */
    private function hitungStokPartBaru(array $ids): void
    {
        $tanggalMaster = InventoryStockSnapshot::max('tanggal_snapshot');

        foreach ($ids as $id) {
            $stok = DB::table('inventory_transactions')->where('inventory_id', $id)->whereIn('type', ['IN', 'OUT'])
                ->when($tanggalMaster, fn ($q) => $q->where('tanggal_transaksi', '>', $tanggalMaster))
                ->selectRaw("SUM(CASE WHEN type = 'IN' THEN qty ELSE -qty END) as s")
                ->value('s');

            DB::table('inventories')->where('id', $id)->update(['stock' => max(0, (int) $stok), 'updated_at' => now()]);
        }
    }

    private function catatPeriode(array &$c, ?string $tanggal): void
    {
        if (!$tanggal) {
            return;
        }
        $c['awal'] = $c['awal'] === null || $tanggal < $c['awal'] ? $tanggal : $c['awal'];
        $c['akhir'] = $c['akhir'] === null || $tanggal > $c['akhir'] ? $tanggal : $c['akhir'];
    }

    private function refSudahAda(string $sourceType): array
    {
        return array_flip(DB::table('inventory_transactions')->where('source_type', $sourceType)->pluck('source_ref')->all());
    }

    // ================================================================
    // 1. SHEET MASTER — snapshot stok + (bila terbaru) update master part
    // ================================================================
    /**
     * Selisih stok per part dari transaksi aplikasi setelah tanggal stok ERP:
     * ambil kiosk (-), retur (+), penerimaan barang (+), penyesuaian opname (+/-).
     * Transaksi pada tanggal stok itu sendiri dianggap sudah termasuk di angka ERP.
     *
     * @return array<int, int> inventory_id => selisih
     */
    public static function gerakanAplikasiSetelah(Carbon $tanggal): array
    {
        return DB::table('inventory_transactions')
            ->whereIn('source_type', ['app', 'app_return', 'app_receipt', 'opname'])
            ->where('tanggal_transaksi', '>', $tanggal->toDateString())
            ->groupBy('inventory_id')
            ->selectRaw("inventory_id, SUM(CASE WHEN type = 'OUT' THEN -qty ELSE qty END) as selisih")
            ->pluck('selisih', 'inventory_id')
            ->map(fn ($v) => (int) $v)->filter()->all();
    }

    private function prosesMaster(Worksheet $sheet, ImportBatch $batch, array &$c, array &$peta, Carbon $tanggal, bool $perbarui, bool $tulisSnapshot = true): void
    {
        $parts = [];
        $bins = [];
        foreach ($this->barisMaster($sheet) as [$partNo, $deskripsi, $qty, $lokasi, $harga, $row]) {
            // Baris ringkasan di bawah tabel ERP ("Qty :", "Total Cost :", total qty, dll.) bukan barang.
            if (self::barisRingkasan($partNo, $deskripsi, $lokasi)) {
                $c['skipped']++;
                continue;
            }

            // Bin WASTE = barang yang akan dibuang: tidak dihitung sebagai stok, tidak membuat part baru.
            if (Inventory::binBuang($lokasi)) {
                $c['waste'] = ($c['waste'] ?? 0) + 1;
                continue;
            }

            if (!isset($parts[$partNo])) {
                $parts[$partNo] = [
                    'deskripsi'           => $deskripsi ?: '-',
                    'stock'               => $qty,
                    'harga_beli_terakhir' => $harga,
                ];
            } else {
                // Part yang sama di beberapa bin: stok dijumlah, harga terakhir yang > 0 dipakai.
                $parts[$partNo]['stock'] += $qty;
                if ($harga > 0) {
                    $parts[$partNo]['harga_beli_terakhir'] = $harga;
                }
            }
            $bin = $lokasi ?: '-';
            $bins[$partNo][$bin] = ($bins[$partNo][$bin] ?? 0) + $qty;
        }

        // Bin utama = stok terbanyak. Bin lainnya disimpan di lokasi_lain.
        foreach ($parts as $partNo => &$data) {
            [$data['lokasi_rak'], $data['lokasi_lain']] = self::pilihBinUtama($bins[$partNo]);
        }
        unset($data);
        // Stok ERP = posisi per tanggal stok. Gerakan yang dicatat aplikasi SETELAH tanggal itu
        // (ambil kiosk, retur, penerimaan, opname) belum ada di angka ERP, jadi diterapkan ulang.
        $gerak = $perbarui ? self::gerakanAplikasiSetelah($tanggal) : [];

        $snapshot = [];
        foreach ($parts as $partNo => $data) {
            if ($perbarui) {
                // File tanpa harga (stock take) / harga 0: harga terakhir yang sudah ada dipertahankan.
                $isi = $data;
                if ($isi['harga_beli_terakhir'] <= 0 && Inventory::where('part_no', $partNo)->exists()) {
                    unset($isi['harga_beli_terakhir']);
                }
                $inv = Inventory::updateOrCreate(['part_no' => $partNo], $isi);
                $baru = $inv->wasRecentlyCreated;
                if (!empty($gerak[$inv->id])) {
                    $inv->update(['stock' => max(0, (int) $data['stock'] + $gerak[$inv->id])]);
                    $c['disesuaikan'] = ($c['disesuaikan'] ?? 0) + 1;
                }
            } else {
                // File historis: jangan sentuh stok/harga/lokasi saat ini.
                $inv = Inventory::firstOrCreate(['part_no' => $partNo], array_merge($data, ['stock' => 0, 'lokasi_lain' => null]));
                $baru = $inv->wasRecentlyCreated;
            }
            if ($baru) {
                $c['part_baru']++;
                $c['part_baru_file'] = ($c['part_baru_file'] ?? 0) + 1;
            }
            $peta[$partNo] = ['id' => $inv->id, 'harga' => (float) $inv->harga_beli_terakhir];

            if (!$tulisSnapshot) {
                continue;
            }

            $snapshot[] = [
                'inventory_id'          => $inv->id,
                'import_batch_id'       => $batch->id,
                'tanggal_snapshot'      => $tanggal->toDateString(),
                'stock_fisik'           => $data['stock'],
                'harga_satuan_saat_itu' => (float) $inv->harga_beli_terakhir,
                'nilai_total_saat_itu'  => (float) $inv->harga_beli_terakhir * $data['stock'],
                'created_at'            => now(),
                'updated_at'            => now(),
            ];
            $c['snapshot']++;
        }

        foreach (array_chunk($snapshot, 500) as $potongan) {
            DB::table('inventory_stock_snapshots')->insert($potongan);
        }

        if ($perbarui) {
            $this->nolkanYangTidakAdaDiFile(array_keys($parts), $gerak, $c);
        }
    }

    /**
     * File master yang LENGKAP hanya memuat barang yang masih ada stoknya. Part gudang M03 yang tidak
     * tercantum berarti stok ERP-nya 0 (sudah habis dipakai / dipindah) -> stok dinolkan,
     * lalu gerakan aplikasi setelah tanggal stok tetap diterapkan (mis. barang yang baru diterima).
     * Pengaman: hanya bila file memuat sedikitnya 80% part M03; file sebagian (satu rak) tidak menolkan apa pun.
     */
    private function nolkanYangTidakAdaDiFile(array $partDiFile, array $gerak, array &$c): void
    {
        $m03 = Inventory::where('lokasi_rak', 'like', StockOpnameService::GUDANG . '-%');
        $jumlahM03 = (clone $m03)->count();
        $diFile = (clone $m03)->whereIn('part_no', $partDiFile)->count();
        if ($jumlahM03 === 0 || $diFile < $jumlahM03 * 0.8) {
            $c['sebagian'] = true;
            return;
        }

        $hilang = (clone $m03)->whereNotIn('part_no', $partDiFile)->where('stock', '!=', 0)->get(['id', 'part_no', 'stock']);
        foreach ($hilang as $inv) {
            $inv->update(['stock' => max(0, (int) ($gerak[$inv->id] ?? 0)), 'lokasi_lain' => null]);
        }
        $c['dinolkan'] = $hilang->count();
    }

    /**
     * @param array<string, int> $bins bin => qty, urut sesuai file
     * @return array{0: string, 1: ?array} [bin utama, bin lain [{bin, qty}] atau null]
     */
    public static function pilihBinUtama(array $bins): array
    {
        $utama = array_keys($bins, max($bins))[0];
        $lain = [];
        foreach ($bins as $bin => $qty) {
            if ($bin !== $utama) {
                $lain[] = ['bin' => $bin, 'qty' => $qty];
            }
        }

        return [$utama, $lain ?: null];
    }

    // ================================================================
    // 2. SHEET RECEIPT — barang masuk (IN)
    //    Kunci anti-duplikat: Pack Slip | Pack Line | Part
    // ================================================================
    private function prosesReceipt(Worksheet $sheet, ImportBatch $batch, array &$c, array &$peta, array $info = []): void
    {
        $rows = $sheet->toArray(null, true, false, true);
        array_shift($rows);
        $sudahAda = $this->refSudahAda('receipt');
        $sisipan = [];

        foreach ($rows as $row) {
            if (empty($row['E'])) {
                continue;
            }
            $c['total']++;

            $partNo = $this->bersihkanPartNo($row['E']);
            $qty = (int) ($row['I'] ?? 0);
            if ($qty <= 0) {
                $c['skipped']++;
                continue;
            }

            $ref = InventoryTransaction::makeSourceRef(trim((string) ($row['B'] ?? '')), trim((string) ($row['C'] ?? '')), $partNo);
            if (isset($sudahAda[$ref])) {
                $c['skipped']++;
                continue;
            }
            $sudahAda[$ref] = true;

            $inv = $this->pastikanPart($partNo, null, $peta, $c, $info);
            $tanggal = $this->parseExcelDate($row['D'] ?? null);
            $this->catatPeriode($c, $tanggal);
            $supplier = trim((string) ($row['X'] ?? '')) ?: 'Supplier tidak tercatat';

            $sisipan[] = [
                'inventory_id'      => $inv['id'],
                'type'              => 'IN',
                'qty'               => $qty,
                'mesin'             => null,
                'harga_satuan'      => $inv['harga'],
                'total_harga'       => $inv['harga'] * $qty,
                'keterangan'        => "Purchasing dari: {$supplier}",
                'tanggal_transaksi' => $tanggal,
                'source_type'       => 'receipt',
                'source_ref'        => $ref,
                'import_batch_id'   => $batch->id,
                'created_at'        => now(),
                'updated_at'        => now(),
            ];
            $c['inserted']++;
        }

        foreach (array_chunk($sisipan, 500) as $potongan) {
            DB::table('inventory_transactions')->insert($potongan);
        }
    }

    // ================================================================
    // 3. SHEET ISSUE — pemakaian & penyesuaian
    //    txtTranType (R):
    //      STK-MTL  pemakaian ke Job PM   -> OUT  + history_logs (aksi 'pakai')
    //      MTL-STK  retur dari Job ke stok -> OUT negatif (mengurangi biaya)
    //      STK-STK  pindah bin             -> dilewati (stok total tetap)
    //      PUR-STK  penerimaan             -> dilewati (sudah dari sheet Receipt)
    //      ADJ-CST  penyesuaian NILAI      -> dilewati (qty-nya bukan perubahan jumlah barang)
    //      lainnya (ADJ-*)                 -> ADJ
    //    Kunci anti-duplikat (JANGAN diubah, harus sama dengan data yang sudah masuk):
    //      txtNum|txtLine|part|tipe|tanggal|job|qty + "|#n" (urutan kemunculan di file)
    // ================================================================
    private function prosesIssue(Worksheet $sheet, ImportBatch $batch, array &$c, array &$peta, array $info = []): void
    {
        $rows = $sheet->toArray(null, true, false, true);
        array_shift($rows);
        $sudahAda = $this->refSudahAda('issue');
        $kemunculan = [];
        $transaksi = [];
        $logs = [];

        foreach ($rows as $row) {
            if (empty($row['B'])) {
                continue;
            }
            $c['total']++;

            $partNo = $this->bersihkanPartNo($row['B']);
            $qty = (int) ($row['L'] ?? 0);
            $job = trim((string) ($row['E'] ?? 'UMUM')) ?: 'UMUM';
            $tipeErp = trim((string) ($row['R'] ?? ''));
            $tanggal = $this->parseExcelDate($row['D'] ?? null);

            // Nomor urut dihitung untuk SEMUA baris sebelum filter apa pun (penomoran stabil).
            $kunci = InventoryTransaction::makeSourceRef(
                trim((string) ($row['I'] ?? '')), trim((string) ($row['J'] ?? '')), $partNo, $tipeErp, (string) $tanggal, $job, (string) $qty
            );
            $kemunculan[$kunci] = ($kemunculan[$kunci] ?? 0) + 1;
            $ref = $kunci . '|#' . $kemunculan[$kunci];

            if ($qty <= 0 || in_array($tipeErp, ['STK-STK', 'PUR-STK', 'ADJ-CST'], true)) {
                $c['skipped']++;
                continue;
            }
            if (isset($sudahAda[$ref])) {
                $c['skipped']++;
                continue;
            }
            $sudahAda[$ref] = true;

            $inv = $this->pastikanPart($partNo, $row['C'] ?? null, $peta, $c, $info);
            $this->catatPeriode($c, $tanggal);

            $pakai = $tipeErp === 'STK-MTL';
            $retur = $tipeErp === 'MTL-STK';
            $qtyTercatat = $retur ? -$qty : $qty;

            $transaksi[] = [
                'inventory_id'      => $inv['id'],
                'type'              => ($pakai || $retur) ? 'OUT' : 'ADJ',
                'qty'               => $qtyTercatat,
                'mesin'             => $job,
                'harga_satuan'      => $inv['harga'],
                'total_harga'       => $inv['harga'] * $qtyTercatat,
                'keterangan'        => $pakai ? "Maintenance Mesin: {$job}"
                    : ($retur ? "Retur dari Job {$job} ke stok" : "Penyesuaian stok ({$tipeErp}) - Mesin/Job: {$job}"),
                'tanggal_transaksi' => $tanggal,
                'source_type'       => 'issue',
                'source_ref'        => $ref,
                'import_batch_id'   => $batch->id,
                'created_at'        => now(),
                'updated_at'        => now(),
            ];

            if ($pakai) {
                $logs[] = [
                    'part_no'      => $partNo,
                    'item_name'    => trim((string) ($row['C'] ?? '')) ?: null,
                    'machine'      => $job,
                    'operator'     => 'Sistem',
                    'peminjam'     => 'Auto Import',
                    'aksi'         => 'pakai',
                    'no_pm'        => $job,
                    'jumlah'       => $qty,
                    'qty_returned' => 0,
                    'source_ref'   => $ref,
                    'created_at'   => $tanggal ? $tanggal . ' 00:00:00' : now(),
                    'updated_at'   => now(),
                ];
            }
            $c['inserted']++;
        }

        foreach (array_chunk($transaksi, 500) as $potongan) {
            DB::table('inventory_transactions')->insert($potongan);
        }
        foreach (array_chunk($logs, 500) as $potongan) {
            DB::table('history_logs')->insert($potongan);
        }
    }

    /**
     * Kolom tanggal bisa berupa serial number Excel, DateTime, atau teks.
     * Bila tidak terbaca -> null (BUKAN now(), supaya tidak diam-diam masuk bulan berjalan).
     */
    private function parseExcelDate($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->toDateString();
        }

        if (is_numeric($value)) {
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value)->format('Y-m-d');
            } catch (\Exception $e) {
                return null;
            }
        }

        try {
            return Carbon::parse((string) $value)->toDateString();
        } catch (\Exception $e) {
            return null;
        }
    }

    /** Format logbook manual lama belum ditentukan — sengaja ditolak, bukan pura-pura berhasil. */
    public function store(Request $request)
    {
        return back()->with('error', __('Importing the old manual logbook is not supported yet. Send a sample of its format so it can be added.'));
    }
}
