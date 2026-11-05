<?php

namespace App\Services;

use App\Models\Inventory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Analitik biaya spare part — satu sumber angka untuk Dashboard, Perlu Restock & Analitik Biaya.
 *
 * Model arus uang:
 *   Pembelian (Receipt ERP)  ->  Stok gudang  ->  Dipakai mesin/Job (biaya)
 *                                             ->  Mengendap (dead stock / overstock)
 *                                             ->  Hilang / selisih (tanpa scan, selisih opname)
 *   Retur dari Job / kiosk mengurangi biaya pemakaian.
 */
class AnalitikBiayaService
{
    /** Tanggal efektif transaksi: tanggal asli ERP, fallback tanggal input. */
    public const TANGGAL = 'COALESCE(inventory_transactions.tanggal_transaksi, DATE(inventory_transactions.created_at))';

    /** Stok ideal = kebutuhan 6 bulan; restock bila stok < kebutuhan 3 bulan. */
    public const BULAN_TARGET_STOK = 6;
    public const HARI_KEBUTUHAN = 90;
    /** Pembelian yang belum pernah keluar setelah selama ini dianggap belum terpakai. */
    public const HARI_BELUM_TERPAKAI = 90;
    /** Stok melebihi kebutuhan sekian bulan = overstock. */
    public const BULAN_OVERSTOCK = 12;

    public const STATUS_BELI = [
        'tepat'          => ['On target', 'emerald', 'Stock was low at purchase, or the item has been proven to be used.'],
        'perlu_dicek'    => ['Needs review', 'amber', 'Stock was still available at purchase, and nothing has been issued since.'],
        'terlalu_cepat'  => ['Too early', 'amber', 'Stock at purchase still covered > 6 months based on the previous 3 months of usage.'],
        'belum_terpakai' => ['Unused', 'accent', '≥ 90 days since purchase and never issued — idle money.'],
        'pantau'         => ['Monitor', 'slate', 'New part / out of stock at purchase, not issued yet (< 90 days).'],
    ];

    // ================================================================
    // DASAR
    // ================================================================

    /**
     * Biaya pemakaian (OUT). Sumber resmi = ERP (Issue). Transaksi aplikasi (scan kiosk & retur)
     * hanya dihitung untuk tanggal SETELAH data ERP terakhir, supaya tidak dobel.
     */
    public function queryBiayaOut()
    {
        $cutoffErp = DB::table('inventory_transactions')->where('source_type', 'issue')->max('tanggal_transaksi');

        return DB::table('inventory_transactions')
            ->where('inventory_transactions.type', 'OUT')
            ->where(function ($q) use ($cutoffErp) {
                $q->whereNull('inventory_transactions.source_type')
                  ->orWhereNotIn('inventory_transactions.source_type', ['app', 'app_return']);
                $cutoffErp
                    ? $q->orWhereRaw(self::TANGGAL . ' > ?', [$cutoffErp])
                    : $q->orWhereIn('inventory_transactions.source_type', ['app', 'app_return']);
            });
    }

    /**
     * Pembelian (IN). Sumber resmi = ERP (Receipt). Penerimaan yang dicatat di dashboard (app_receipt)
     * hanya dihitung untuk tanggal SETELAH data Receipt ERP terakhir, karena penerimaan yang sama
     * nantinya ikut masuk lewat impor ERP — supaya tidak terhitung dua kali.
     */
    public function queryPembelian()
    {
        $cutoffErp = DB::table('inventory_transactions')->where('source_type', 'receipt')->max('tanggal_transaksi');

        return DB::table('inventory_transactions')
            ->where('inventory_transactions.type', 'IN')
            ->where(function ($q) use ($cutoffErp) {
                $q->whereNull('inventory_transactions.source_type')
                  ->orWhere('inventory_transactions.source_type', '!=', PenerimaanService::SUMBER);
                if ($cutoffErp) {
                    $q->orWhereRaw(self::TANGGAL . ' > ?', [$cutoffErp]);
                } else {
                    $q->orWhere('inventory_transactions.source_type', PenerimaanService::SUMBER);
                }
            });
    }

    public function periode($query, Carbon $awal, Carbon $akhir)
    {
        return $query->whereRaw(self::TANGGAL . ' BETWEEN ? AND ?', [$awal->toDateString(), $akhir->toDateString()]);
    }

    /** Rentang data transaksi ERP yang tersedia (untuk peringatan akurasi). */
    public function cakupanData(): array
    {
        $r = DB::table('inventory_transactions')->whereIn('source_type', ['receipt', 'issue'])
            ->selectRaw('MIN(tanggal_transaksi) as awal, MAX(tanggal_transaksi) as akhir')->first();

        $awal = $r?->awal ? Carbon::parse($r->awal) : null;
        $hari = $awal ? (int) $awal->diffInDays(today()) + 1 : 0;

        return [
            'awal'        => $awal,
            'akhir'       => $r?->akhir ? Carbon::parse($r->akhir) : null,
            'hari'        => $hari,
            'cukup_90'    => $hari >= 90,
            'cukup_tahun' => $hari >= 365,
        ];
    }

    /** Fiscal year perusahaan: 1 Juli – 30 Juni. */
    public static function awalFY(?Carbon $tanggal = null): Carbon
    {
        $t = ($tanggal ?? now())->copy();

        return ($t->month >= 7 ? $t : $t->subYear())->startOfYear()->setMonth(7)->startOfDay();
    }

    public static function labelFY(Carbon $awalFY): string
    {
        return 'FY ' . $awalFY->format('Y') . '/' . $awalFY->copy()->addYear()->format('y');
    }

    // ================================================================
    // PERLU RESTOCK
    // ================================================================

    /**
     * Part gudang M03 yang stoknya < kebutuhan 3 bulan (pemakaian 90 hari terakhir).
     * Saran beli = target stok 6 bulan − stok sekarang.
     */
    public function queryPerluRestock()
    {
        $pemakaian = DB::table('inventory_transactions')
            ->where('type', 'OUT')
            ->whereRaw(self::TANGGAL . ' >= ?', [now()->subDays(self::HARI_KEBUTUHAN)->toDateString()])
            ->select('inventory_id', DB::raw('SUM(qty) as pakai_90'))
            ->groupBy('inventory_id')
            ->havingRaw('SUM(qty) > 0');

        $target = self::BULAN_TARGET_STOK / 3; // kelipatan dari kebutuhan 3 bulan

        return Inventory::query()
            ->joinSub($pemakaian, 'p', 'p.inventory_id', '=', 'inventories.id')
            ->where('inventories.lokasi_rak', 'like', 'M03-%')
            ->whereColumn('inventories.stock', '<', 'p.pakai_90')
            // Qty yang sudah dipesan lewat PR terbuka (belum datang) mengurangi saran beli -> tidak dipesan dua kali.
            ->leftJoinSub(PurchaseRequestService::subqueryDipesan(), 'prd', 'prd.inventory_id', '=', 'inventories.id')
            ->select('inventories.*', 'p.pakai_90')
            ->selectRaw('COALESCE(prd.dipesan, 0) as dipesan')
            ->selectRaw("GREATEST(CEIL(p.pakai_90 * {$target}) - inventories.stock - COALESCE(prd.dipesan, 0), 0) as saran_beli")
            ->selectRaw("GREATEST(CEIL(p.pakai_90 * {$target}) - inventories.stock - COALESCE(prd.dipesan, 0), 0) * inventories.harga_beli_terakhir as saran_rp");
    }

    public function ringkasanRestock(): array
    {
        $r = DB::query()->fromSub($this->queryPerluRestock()->toBase(), 'r')
            ->selectRaw('COUNT(*) as jumlah, COALESCE(SUM(saran_rp), 0) as rp, SUM(stock <= 0) as habis')->first();

        return ['jumlah' => (int) $r->jumlah, 'rp' => (float) $r->rp, 'habis' => (int) $r->habis];
    }

    // ================================================================
    // LAPORAN ANALITIK (halaman Presdir)
    // ================================================================

    public function laporan(Carbon $awal, Carbon $akhir): array
    {
        $akhir = $akhir->copy()->min(today()->endOfDay());
        $cakupan = $this->cakupanData();

        $beli = (float) $this->periode($this->queryPembelian(), $awal, $akhir)->sum('total_harga');
        $pakai = (float) $this->periode($this->queryBiayaOut(), $awal, $akhir)->sum('total_harga');

        $kualitas = $this->kualitasPembelian($awal, $akhir, $cakupan);
        $mengendap = $this->uangMengendap($cakupan);
        $rekon = app(RekonsiliasiService::class)->laporan($awal->copy()->startOfDay(), $akhir->copy()->endOfDay())['statistik'];

        return [
            'cakupan'    => $cakupan,
            'ringkasan'  => [
                'pembelian'   => $beli,
                'pemakaian'   => $pakai,
                'tertahan'    => $beli - $pakai,
                'rasio'       => $beli > 0 ? round($pakai / $beli * 100) : null,
            ],
            'arus'       => $this->arusUang($awal, $akhir),
            'tren'       => $this->trenBulanan($awal, $akhir),
            'top_job'    => $this->topJob($awal, $akhir),
            'top_beli'   => $this->topBeli($awal, $akhir),
            'kualitas'   => $kualitas,
            'mengendap'  => $mengendap,
            'restock'    => $this->ringkasanRestock(),
            'kerugian'   => [
                'erp_tanpa_scan'    => $rekon['erp_tanpa_scan'],
                'erp_tanpa_scan_rp' => $rekon['erp_tanpa_scan_rp'],
                'opname'            => app(StockOpnameService::class)->selisihPeriode($awal, $akhir),
            ],
        ];
    }

    /** Nilai stok M03 per tanggal snapshot. */
    private function nilaiSnapshot(string $tanggal): float
    {
        return (float) DB::table('inventory_stock_snapshots as s')
            ->join('inventories as i', 'i.id', '=', 's.inventory_id')
            ->where('i.lokasi_rak', 'like', 'M03-%')
            ->whereDate('s.tanggal_snapshot', $tanggal)
            ->sum('s.nilai_total_saat_itu');
    }

    /**
     * Stok awal (snapshot) + pembelian − pemakaian = perkiraan stok akhir.
     * Bila ada snapshot kedua di akhir periode: selisih = perkiraan − aktual (indikasi barang hilang / opname).
     */
    public function arusUang(Carbon $awal, Carbon $akhir): array
    {
        $tanggalSnapshot = DB::table('inventory_stock_snapshots')->distinct()->orderBy('tanggal_snapshot')->pluck('tanggal_snapshot');

        $snapAwal = $tanggalSnapshot->filter(fn ($t) => $t <= $awal->toDateString())->last()
            ?? $tanggalSnapshot->filter(fn ($t) => $t <= $akhir->toDateString())->first();

        if (!$snapAwal) {
            return ['tersedia' => false];
        }

        $mulai = Carbon::parse($snapAwal);
        $snapAkhir = $tanggalSnapshot->filter(fn ($t) => $t > $snapAwal && $t <= $akhir->toDateString())->last();

        $hitungSampai = $snapAkhir ? Carbon::parse($snapAkhir) : $akhir;
        $rentang = fn ($q) => $q->whereRaw(self::TANGGAL . ' > ? AND ' . self::TANGGAL . ' <= ?', [$mulai->toDateString(), $hitungSampai->toDateString()]);

        $nilaiAwal = $this->nilaiSnapshot($snapAwal);
        $beli = (float) $rentang($this->queryPembelian())->sum('total_harga');
        $pakai = (float) $rentang($this->queryBiayaOut())->sum('total_harga');
        $perkiraan = $nilaiAwal + $beli - $pakai;
        $aktual = $snapAkhir ? $this->nilaiSnapshot($snapAkhir) : null;

        return [
            'tersedia'   => true,
            'tgl_awal'   => $mulai,
            'tgl_akhir'  => $hitungSampai,
            'awal'       => $nilaiAwal,
            'beli'       => $beli,
            'pakai'      => $pakai,
            'perkiraan'  => $perkiraan,
            'aktual'     => $aktual,
            'selisih'    => $aktual !== null ? $perkiraan - $aktual : null,
        ];
    }

    public function trenBulanan(Carbon $awal, Carbon $akhir): array
    {
        $kelompok = fn ($q) => $q->selectRaw('DATE_FORMAT(' . self::TANGGAL . ", '%Y-%m') as bln, SUM(total_harga) as rp")->groupBy('bln')->pluck('rp', 'bln');

        $beli = $kelompok($this->periode($this->queryPembelian(), $awal, $akhir));
        $pakai = $kelompok($this->periode($this->queryBiayaOut(), $awal, $akhir));

        $hasil = [];
        $kumulatif = 0;
        for ($b = $awal->copy()->startOfMonth(); $b->lte($akhir); $b->addMonthNoOverflow()) {
            $k = $b->format('Y-m');
            $rpBeli = (float) ($beli[$k] ?? 0);
            $rpPakai = (float) ($pakai[$k] ?? 0);
            $kumulatif += $rpBeli - $rpPakai;
            $hasil[] = ['label' => $b->translatedFormat('M y'), 'beli' => $rpBeli, 'pakai' => $rpPakai, 'tertahan' => $kumulatif];
        }

        return $hasil;
    }

    /**
     * Nama mesin untuk satu transaksi: padanan Job PM -> mesin bila ada; selain itu nilai kolom
     * mesin apa adanya (scan kiosk sudah berisi nama mesin, Job yang belum dipetakan tetap nomor Job).
     */
    public const NAMA_MESIN = "COALESCE(jm.mesin, inventory_transactions.mesin, 'UMUM')";

    public function denganMesin($query)
    {
        return $query->leftJoin('job_mesins as jm', 'jm.no_job', '=', 'inventory_transactions.mesin');
    }

    /** Top pemakai biaya per MESIN (Job PM yang sudah dipetakan digabung ke mesinnya). */
    public function topJob(Carbon $awal, Carbon $akhir, int $limit = 5): Collection
    {
        return $this->denganMesin($this->periode($this->queryBiayaOut(), $awal, $akhir))
            ->selectRaw(self::NAMA_MESIN . ' as job, SUM(total_harga) as rp, SUM(qty) as qty, COUNT(*) as frek,
                COUNT(DISTINCT CASE WHEN jm.id IS NOT NULL THEN inventory_transactions.mesin END) as jumlah_job,
                MAX(CASE WHEN jm.id IS NULL AND inventory_transactions.mesin LIKE \'PM%\' THEN 1 ELSE 0 END) as belum_dipetakan')
            ->groupBy('job')
            ->orderByDesc('rp')
            ->limit($limit)
            ->get();
    }

    /** Rincian part per mesin (untuk modal "klik untuk rincian"). */
    public function rincianPerMesin(Carbon $awal, Carbon $akhir, Collection $namaMesin): array
    {
        if ($namaMesin->isEmpty()) {
            return [];
        }

        $baris = $this->denganMesin($this->periode($this->queryBiayaOut(), $awal, $akhir))
            ->join('inventories', 'inventory_transactions.inventory_id', '=', 'inventories.id')
            ->whereIn(DB::raw(self::NAMA_MESIN), $namaMesin)
            ->selectRaw(self::NAMA_MESIN . ' as machine, inventories.deskripsi as item_name, SUM(inventory_transactions.qty) as qty, SUM(inventory_transactions.total_harga) as biaya')
            ->groupBy('machine', 'inventories.id', 'inventories.deskripsi')
            ->orderByDesc('biaya')
            ->get();

        $hasil = [];
        foreach ($baris as $b) {
            $hasil[$b->machine][] = ['part' => $b->item_name, 'qty' => $b->qty, 'biaya' => $b->biaya];
        }

        return $hasil;
    }

    /**
     * Semua nomor Job PM yang muncul di pemakaian ERP, beserta nilai & padanannya —
     * daftar kerja halaman "Mesin & Job PM".
     */
    public function daftarJob(?string $cari = null, string $filter = 'semua')
    {
        return DB::table('inventory_transactions')
            ->leftJoin('job_mesins as jm', 'jm.no_job', '=', 'inventory_transactions.mesin')
            ->where('inventory_transactions.type', 'OUT')
            ->where('inventory_transactions.source_type', 'issue')
            ->whereNotNull('inventory_transactions.mesin')
            ->when($cari, fn ($q) => $q->where(fn ($s) => $s->where('inventory_transactions.mesin', 'like', "%$cari%")->orWhere('jm.mesin', 'like', "%$cari%")))
            ->when($filter === 'belum', fn ($q) => $q->whereNull('jm.id'))
            ->when($filter === 'sudah', fn ($q) => $q->whereNotNull('jm.id'))
            ->selectRaw('inventory_transactions.mesin as no_job, jm.mesin, jm.keterangan, SUM(inventory_transactions.total_harga) as rp,
                COUNT(*) as frek, MAX(' . self::TANGGAL . ') as terakhir')
            ->groupBy('inventory_transactions.mesin', 'jm.mesin', 'jm.keterangan')
            ->orderByRaw('jm.id IS NOT NULL')
            ->orderByDesc('rp');
    }

    public function topBeli(Carbon $awal, Carbon $akhir, int $limit = 10): Collection
    {
        return $this->periode($this->queryPembelian(), $awal, $akhir)
            ->join('inventories as i', 'i.id', '=', 'inventory_transactions.inventory_id')
            ->selectRaw('i.id, i.part_no, i.deskripsi, SUM(inventory_transactions.qty) as qty, SUM(inventory_transactions.total_harga) as rp, COUNT(*) as kali')
            ->groupBy('i.id', 'i.part_no', 'i.deskripsi')
            ->orderByDesc('rp')
            ->limit($limit)
            ->get();
    }

    /**
     * Menilai setiap baris pembelian di periode: apakah barang itu memang dibutuhkan?
     * Aturan (berurutan) — lihat STATUS_BELI untuk penjelasan tiap status.
     */
    public function kualitasPembelian(Carbon $awal, Carbon $akhir, array $cakupan): array
    {
        $beli = $this->periode($this->queryPembelian(), $awal, $akhir)
            ->join('inventories as i', 'i.id', '=', 'inventory_transactions.inventory_id')
            ->selectRaw('inventory_transactions.id, inventory_transactions.inventory_id, inventory_transactions.qty, inventory_transactions.total_harga as rp, '
                . self::TANGGAL . ' as tgl, inventory_transactions.keterangan, i.part_no, i.deskripsi, i.stock as stok_sekarang')
            ->orderByDesc('rp')
            ->get();

        if ($beli->isEmpty()) {
            return ['baris' => collect(), 'per_status' => [], 'total_rp' => 0];
        }

        $ids = $beli->pluck('inventory_id')->unique()->values();

        // Semua pergerakan part terkait, dimuat sekali.
        $gerak = DB::table('inventory_transactions')->whereIn('inventory_id', $ids)->whereIn('type', ['IN', 'OUT'])
            ->selectRaw('inventory_id, type, qty, ' . self::TANGGAL . ' as tgl')->get()->groupBy('inventory_id');
        $snap = DB::table('inventory_stock_snapshots')->whereIn('inventory_id', $ids)
            ->select('inventory_id', 'tanggal_snapshot', 'stock_fisik')->orderBy('tanggal_snapshot')->get()->groupBy('inventory_id');

        $mulaiData = $cakupan['awal'];

        $baris = $beli->map(function ($b) use ($gerak, $snap, $mulaiData) {
            $tgl = Carbon::parse($b->tgl);
            $g = $gerak->get($b->inventory_id, collect());
            $s = $snap->get($b->inventory_id, collect());

            // Stok tepat sebelum pembelian ini: snapshot terakhir <= tanggal beli, disesuaikan IN/OUT sesudahnya.
            $snapSebelum = $s->filter(fn ($x) => $x->tanggal_snapshot <= $b->tgl)->last();
            if ($snapSebelum) {
                $antara = $g->filter(fn ($x) => $x->tgl > $snapSebelum->tanggal_snapshot && $x->tgl < $b->tgl);
                $stokSaatBeli = max(0, (int) $snapSebelum->stock_fisik
                    + $antara->where('type', 'IN')->sum('qty') - $antara->where('type', 'OUT')->sum('qty'));
            } else {
                $stokSaatBeli = $s->isEmpty() ? 0 : null; // part baru -> 0; ada snapshot tapi lebih baru -> tidak diketahui
            }

            $pakaiSejak = (int) $g->where('type', 'OUT')->filter(fn ($x) => $x->tgl >= $b->tgl)->sum('qty');
            $pakai90Sebelum = (int) $g->where('type', 'OUT')
                ->filter(fn ($x) => $x->tgl < $b->tgl && $x->tgl >= $tgl->copy()->subDays(90)->toDateString())->sum('qty');
            $historiCukup = $mulaiData && $mulaiData->lte($tgl->copy()->subDays(90));
            $umur = (int) $tgl->diffInDays(today());

            if ($umur >= self::HARI_BELUM_TERPAKAI && $pakaiSejak === 0) {
                $status = 'belum_terpakai';
            } elseif ($historiCukup && $stokSaatBeli !== null) {
                $kebBulan = $pakai90Sebelum / 3;
                $status = $stokSaatBeli > self::BULAN_TARGET_STOK * $kebBulan ? 'terlalu_cepat' : 'tepat';
            } elseif ($pakaiSejak > 0) {
                $status = 'tepat';
            } elseif ($stokSaatBeli > 0) {
                $status = 'perlu_dicek';
            } else {
                $status = 'pantau';
            }

            return (object) [
                'id' => $b->id, 'tgl' => $tgl, 'part_no' => $b->part_no, 'deskripsi' => $b->deskripsi,
                'qty' => (int) $b->qty, 'rp' => (float) $b->rp, 'supplier' => trim(str_replace('Purchasing dari:', '', (string) $b->keterangan)),
                'stok_saat_beli' => $stokSaatBeli, 'pakai_sejak' => $pakaiSejak, 'pakai_90_sebelum' => $pakai90Sebelum,
                'histori_cukup' => $historiCukup, 'umur' => $umur, 'status' => $status,
            ];
        });

        $perStatus = [];
        foreach (array_keys(self::STATUS_BELI) as $st) {
            $grup = $baris->where('status', $st);
            $perStatus[$st] = ['jumlah' => $grup->count(), 'rp' => $grup->sum('rp')];
        }

        return ['baris' => $baris, 'per_status' => $perStatus, 'total_rp' => $baris->sum('rp')];
    }

    /**
     * Uang mengendap di gudang M03 (posisi sekarang):
     *  - Dead stock : stok > 0 tapi tidak pernah keluar sejak data tersedia (maks 12 bulan terakhir).
     *  - Overstock  : stok melebihi kebutuhan 12 bulan (diproyeksikan dari pemakaian yang tercatat).
     */
    public function uangMengendap(array $cakupan): array
    {
        $hariJendela = max(1, min(365, $cakupan['hari']));
        $mulai = now()->subDays($hariJendela)->toDateString();

        $pakai = DB::table('inventory_transactions')->where('type', 'OUT')
            ->whereRaw(self::TANGGAL . ' >= ?', [$mulai])
            ->select('inventory_id', DB::raw('SUM(qty) as qty'))->groupBy('inventory_id');

        $dasar = Inventory::query()
            ->leftJoinSub($pakai, 'p', 'p.inventory_id', '=', 'inventories.id')
            ->where('inventories.lokasi_rak', 'like', 'M03-%')
            ->where('inventories.stock', '>', 0)
            ->where('inventories.harga_beli_terakhir', '>', 0);

        $dead = (clone $dasar)->whereNull('p.qty')
            ->selectRaw('inventories.id, inventories.part_no, inventories.deskripsi, inventories.stock, inventories.harga_beli_terakhir, inventories.stock * inventories.harga_beli_terakhir as rp');

        $faktor = self::BULAN_OVERSTOCK * 30 / $hariJendela; // kebutuhan 12 bulan = pemakaian jendela x faktor
        $over = (clone $dasar)->where('p.qty', '>', 0)
            ->whereRaw("inventories.stock > p.qty * {$faktor}")
            ->selectRaw("inventories.id, inventories.part_no, inventories.deskripsi, inventories.stock, p.qty as pakai,
                CEIL(p.qty * {$faktor}) as kebutuhan_12,
                (inventories.stock - CEIL(p.qty * {$faktor})) * inventories.harga_beli_terakhir as rp");

        $ringkas = fn ($q) => DB::query()->fromSub($q->toBase(), 'x')->selectRaw('COUNT(*) as n, COALESCE(SUM(rp), 0) as rp')->first();
        $d = $ringkas($dead);
        $o = $ringkas($over);

        return [
            'hari_jendela' => $hariJendela,
            'dead_n'   => (int) $d->n,  'dead_rp' => (float) $d->rp,
            'over_n'   => (int) $o->n,  'over_rp' => (float) $o->rp,
            'dead_top' => (clone $dead)->orderByDesc('rp')->limit(10)->get(),
            'over_top' => (clone $over)->orderByDesc('rp')->limit(10)->get(),
        ];
    }
}
