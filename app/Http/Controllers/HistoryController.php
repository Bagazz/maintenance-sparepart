<?php

namespace App\Http\Controllers;

use App\Models\HistoryLog;
use App\Models\InventoryTransaction;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Support\Ekspor;
use Illuminate\Support\Carbon;

class HistoryController extends Controller
{
    public function index(Request $request)
    {
        $q = $request->get('q');
        $exact = $request->boolean('exact'); // true kalau q berasal dari klik/Enter saran pencarian
        $filterMesin = $request->get('mesin');
        $filterOperator = $request->get('operator');
        $activeTab = $request->get('tab', 'out');

        // ---------------------------------------------------------
        // 1. DATA TAB OUT (PEMINJAMAN) - LOGIKA ASLI TETAP UTUH
        // ---------------------------------------------------------
        $queryOut = $this->queryKeluar($request);

        $histories = $queryOut->paginate(15, ['*'], 'out_page')->withQueryString();

        // ---------------------------------------------------------
        // 2. DATA TAB IN (BARANG MASUK / PURCHASING)
        // ---------------------------------------------------------
        $queryIn = $this->queryTransaksi('IN', $q);
        $inHistories = $queryIn->paginate(15, ['*'], 'in_page')->withQueryString();

        // ---------------------------------------------------------
        // 3. DATA TAB ADJ (PENYESUAIAN STOK)
        // ---------------------------------------------------------
        $queryAdj = $this->queryTransaksi('ADJ', $q);
        $adjHistories = $queryAdj->paginate(15, ['*'], 'adj_page')->withQueryString();

        // ---------------------------------------------------------
        // 4. STATISTIK KARTU ATAS
        // ---------------------------------------------------------
        $totalTransaksi = HistoryLog::count() + InventoryTransaction::count();
        $barangKeluar   = HistoryLog::sum('jumlah');
        $barangMasuk    = InventoryTransaction::where('type', 'IN')->sum('qty');
        
        $mesin          = HistoryLog::whereNotNull('machine')->distinct()->count('machine');
        $operator       = HistoryLog::whereNotNull('operator')->distinct()->count('operator');

        $daftarMesin    = HistoryLog::whereNotNull('machine')->distinct()->orderBy('machine')->pluck('machine');
        $daftarOperator = HistoryLog::whereNotNull('operator')->distinct()->orderBy('operator')->pluck('operator');

        return view('history.index', compact(
            'totalTransaksi', 'barangKeluar', 'barangMasuk', 'mesin', 'operator',
            'daftarMesin', 'daftarOperator',
            'histories', 'inHistories', 'adjHistories',
            'q', 'filterMesin', 'filterOperator', 'activeTab'
        ));
    }

    /**
     * Halaman direktori Mesin — daftar semua mesin yang tercatat,
     * masing-masing bisa diklik untuk lihat riwayat pemakaian part di mesin itu.
     */
    public function daftarMesin()
    {
        $daftarMesin = HistoryLog::whereNotNull('machine')->distinct()->orderBy('machine')->pluck('machine');

        return view('history.mesin', compact('daftarMesin'));
    }

    /**
     * Halaman direktori Operator — daftar semua operator yang tercatat,
     * masing-masing bisa diklik untuk lihat riwayat aktivitasnya.
     */
    public function daftarOperator()
    {
        $daftarOperator = HistoryLog::whereNotNull('operator')->distinct()->orderBy('operator')->pluck('operator');

        return view('history.operator', compact('daftarOperator'));
    }

    /**
     * Endpoint autocomplete untuk kotak pencarian History (Logika Asli).
     */
    public function suggest(Request $request)
    {
        $q = trim($request->get('q', ''));

        if ($q === '') {
            return response()->json([]);
        }

        $partNo = HistoryLog::where('part_no', 'like', "%{$q}%")
            ->distinct()->limit(5)->pluck('part_no');

        $peminjam = HistoryLog::where('peminjam', 'like', "%{$q}%")
            ->whereNotNull('peminjam')->distinct()->limit(5)->pluck('peminjam');

        $machine = HistoryLog::where('machine', 'like', "%{$q}%")
            ->whereNotNull('machine')->distinct()->limit(5)->pluck('machine');

        $hasil = collect()
            ->merge($partNo->map(fn($v) => ['label' => $v, 'type' => __('Part Number')]))
            ->merge($peminjam->map(fn($v) => ['label' => $v, 'type' => __('Taken by')]))
            ->merge($machine->map(fn($v) => ['label' => $v, 'type' => __('Machine')]))
            ->unique('label')
            ->values();

        return response()->json($hasil);
    }

    /** Filter tab "Barang keluar" — sama untuk halaman dan ekspor. */
    private function queryKeluar(Request $request)
    {
        $q = $request->get('q');
        $exact = $request->boolean('exact'); // true kalau q berasal dari klik/Enter saran pencarian
        $filterMesin = $request->get('mesin');
        $filterOperator = $request->get('operator');

        $query = HistoryLog::orderByDesc('created_at');

        if ($q) {
            $query->where(function ($sub) use ($q, $exact) {
                if ($exact) {
                    // Dipilih dari daftar saran -> cocokkan persis, jangan ikut ke part number
                    // lain yang kebetulan mengandung angka yang sama (mis. 300112 vs 3001121).
                    $sub->where('part_no', $q)
                        ->orWhere('peminjam', $q)
                        ->orWhere('machine', $q);
                } else {
                    $sub->where('part_no', 'like', "%$q%")
                        ->orWhere('item_name', 'like', "%$q%")
                        ->orWhere('peminjam', 'like', "%$q%")
                        ->orWhere('machine', 'like', "%$q%")
                        ->orWhere('no_pm', 'like', "%$q%");
                }
            });
        }

        if ($filterMesin) {
            $query->where('machine', $filterMesin);
        }

        if ($filterOperator) {
            $query->where('operator', $filterOperator);
        }

        return $query;
    }

    /** Filter tab "Barang masuk" (IN) / "Penyesuaian" (ADJ) — sama untuk halaman dan ekspor. */
    private function queryTransaksi(string $tipe, ?string $q)
    {
        $query = InventoryTransaction::with('inventory')->where('type', $tipe)->orderByDesc('created_at');
        if ($q) {
            $query->whereHas('inventory', function ($sub) use ($q) {
                $sub->where('part_no', 'like', "%$q%")->orWhere('deskripsi', 'like', "%$q%");
            });
        }

        return $query;
    }

    /**
     * Batas baris PDF. dompdf butuh ±0,5 MB memori per baris tabel (400 baris ≈ 200 MB, ±4 detik),
     * jadi PDF = laporan cetak berisi catatan terbaru; daftar lengkap tanpa batas ada di CSV.
     */
    public const MAKS_BARIS_PDF = 400;

    public function exportPdf(Request $request)
    {
        return $this->ekspor($request, 'pdf');
    }

    public function exportCsv(Request $request)
    {
        return $this->ekspor($request, 'csv');
    }

    /**
     * Ekspor riwayat (PDF atau CSV) sesuai tab yang sedang dibuka dan filter yang sama dengan halaman.
     * Kolom: [judul, jenis] — jenis menentukan format PDF (tanggal, angka, rupiah); CSV memakai nilai mentah.
     */
    private function ekspor(Request $request, string $format)
    {
        $tab = in_array($request->get('tab'), ['out', 'in', 'adj'], true) ? $request->get('tab') : 'out';
        $q = $request->get('q');

        if ($tab === 'out') {
            $judul = __('Parts Issued History');
            $file = 'Parts-Issued-History';
            $kolom = [[__('Date'), 'tanggal'], [__('Time'), 'teks'], ['Shift', 'teks'], [__('Part No'), 'teks'], [__('Description'), 'teks'],
                ['Qty', 'angka'], [__('Returned'), 'angka'], [__('Net used'), 'angka'], [__('Taken by'), 'teks'], [__('Source'), 'teks'],
                [__('Machine / Job'), 'teks'], [__('PM No'), 'teks'], [__('Status'), 'teks']];
            $data = $this->queryKeluar($request)->get();
            $baris = $data->map(function ($h) {
                $erp = (bool) $h->source_ref;
                $status = $h->sisa <= 0 ? __('Fully returned')
                    : ($h->qty_returned > 0 ? __('Used') . ' · ' . __(':n returned', ['n' => $h->qty_returned]) : __('Used'));

                return [
                    $h->created_at->format('Y-m-d'), $erp ? '-' : $h->created_at->format('H:i'), $erp ? '-' : $h->shift,
                    $h->part_no, $h->item_name ?? '-', (int) $h->jumlah, (int) $h->qty_returned, (int) $h->sisa,
                    $h->peminjam ?? '-', $erp ? __('ERP import') : __('App scan'), $h->machine ?? '-', $h->no_pm ?? '-', $status,
                ];
            });
            $ringkasan = [__('Records') => $data->count(), __('Qty issued') => $data->sum('jumlah'), __('Qty returned') => $data->sum('qty_returned'), __('Net used') => $data->sum('jumlah') - $data->sum('qty_returned')];
            $total = [0 => __('Total'), 5 => $data->sum('jumlah'), 6 => $data->sum('qty_returned'), 7 => $data->sum('jumlah') - $data->sum('qty_returned')];
        } elseif ($tab === 'in') {
            $judul = __('Goods Received History');
            $file = 'Goods-Received-History';
            $kolom = [[__('Received date'), 'tanggal'], [__('Part No'), 'teks'], [__('Description'), 'teks'], ['Qty', 'angka'],
                [__('Unit price (Rp)'), 'rp'], [__('Total (Rp)'), 'rp'], [__('Notes'), 'teks']];
            $data = $this->queryTransaksi('IN', $q)->get();
            $baris = $data->map(fn ($t) => [
                optional($t->tanggal_transaksi ?? $t->created_at)->format('Y-m-d'), $t->inventory->part_no ?? '-', $t->inventory->deskripsi ?? '-',
                (int) $t->qty, (float) $t->harga_satuan, (float) $t->total_harga, $t->keterangan ?? __('Purchasing Excel import'),
            ]);
            $ringkasan = [__('Records') => $data->count(), __('Qty received') => $data->sum('qty'), __('Total value') => 'Rp ' . number_format((float) $data->sum('total_harga'), 0, ',', '.')];
            $total = [0 => __('Total'), 3 => $data->sum('qty'), 5 => (float) $data->sum('total_harga')];
        } else {
            $judul = __('Stock Adjustment History');
            $file = 'Stock-Adjustment-History';
            $kolom = [[__('Date'), 'tanggal'], [__('Part No'), 'teks'], [__('Description'), 'teks'], ['Qty', 'angka'], [__('Notes'), 'teks']];
            $data = $this->queryTransaksi('ADJ', $q)->get();
            $baris = $data->map(fn ($t) => [
                optional($t->tanggal_transaksi ?? $t->created_at)->format('Y-m-d'), $t->inventory->part_no ?? '-', $t->inventory->deskripsi ?? '-',
                (int) $t->qty, $t->keterangan ?? __('System adjustment'),
            ]);
            $ringkasan = [__('Records') => $data->count(), __('Qty added') => $data->where('qty', '>', 0)->sum('qty'), __('Qty removed') => abs($data->where('qty', '<', 0)->sum('qty'))];
            $total = [0 => __('Total'), 3 => $data->sum('qty')];
        }

        $header = array_column($kolom, 0);
        $namaFile = $file . '-' . now()->format('Ymd-Hi');

        if ($format === 'csv') {
            return Ekspor::unduhCsv($namaFile . '.csv', $header, $baris);
        }

        // ---- PDF ---- (memori & waktu ditambah khusus untuk pembuatan PDF)
        ini_set('memory_limit', '512M');
        set_time_limit(120);
        $tanggal = $baris->pluck(0)->filter();
        $keterangan = [
            $tanggal->isNotEmpty() ? __('Period :dari – :sampai', ['dari' => Carbon::parse($tanggal->min())->translatedFormat('d M Y'), 'sampai' => Carbon::parse($tanggal->max())->translatedFormat('d M Y')]) : null,
            collect([
                $q ? __('Search') . ': "' . $q . '"' : null,
                $tab === 'out' && $request->get('mesin') ? __('Machine') . ': ' . $request->get('mesin') : null,
                $tab === 'out' && $request->get('operator') ? __('Operator') . ': ' . $request->get('operator') : null,
            ])->filter()->implode(' · ') ?: __('All records (no filter)'),
        ];
        $terpotong = $baris->count() > self::MAKS_BARIS_PDF;
        $jenis = array_column($kolom, 1);
        $tampil = fn ($v, $j) => match ($j) {
            'tanggal' => $v ? Carbon::parse($v)->translatedFormat('d M Y') : '-',
            'angka'   => number_format((float) $v, 0, ',', '.'),
            'rp'      => number_format((float) $v, 0, ',', '.'),
            default   => (string) $v,
        };
        $barisPdf = $baris->take(self::MAKS_BARIS_PDF)->map(fn ($r) => array_map($tampil, $r, $jenis))->values()->all();
        $kanan = array_keys(array_filter($jenis, fn ($j) => in_array($j, ['angka', 'rp'], true)));
        $totalPdf = collect($total)->map(fn ($v, $i) => $i === 0 ? $v : $tampil($v, $jenis[$i]))->all();

        $pdf = Pdf::loadView('ekspor.tabel-pdf', [
            'judulDok'   => $judul,
            'keterangan' => $keterangan,
            'kaki'       => $judul,
            'ringkasan'  => collect($ringkasan)->map(fn ($v) => is_numeric($v) ? number_format((float) $v, 0, ',', '.') : $v)->all(),
            'header'     => $header,
            'baris'      => $barisPdf,
            'kanan'      => $kanan,
            'total'      => $terpotong ? [] : $totalPdf,
            'catatan'    => $terpotong ? __('Showing the latest :n of :total records. Download the CSV for the complete list.', ['n' => number_format(self::MAKS_BARIS_PDF, 0, ',', '.'), 'total' => number_format($baris->count(), 0, ',', '.')]) : null,
        ])->setOption('enable_font_subsetting', true)->setPaper('a4', 'landscape');   // font dipangkas: file kecil, mudah dikirim

        return $pdf->download($namaFile . '.pdf');
    }
}
