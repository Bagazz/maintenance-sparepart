<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Services\AnalitikBiayaService;
use App\Services\PurchaseRequestExcel;
use App\Services\RekonsiliasiService;
use App\Support\Ekspor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard operasional (manager maintenance / kepala sparepart).
 * Analitik keuangan mendalam untuk Presdir ada di AnalitikController.
 */
class DashboardController extends Controller
{
    public function __construct(private AnalitikBiayaService $analitik)
    {
    }

    public function index(RekonsiliasiService $rekon)
    {
        $a = $this->analitik;

        $awalBulanIni  = now()->startOfMonth();
        $akhirBulanIni = now()->endOfMonth();
        $awalBulanLalu  = now()->subMonthNoOverflow()->startOfMonth();
        $akhirBulanLalu = now()->subMonthNoOverflow()->endOfMonth();

        $totalSparePart = Inventory::count();

        // Nilai stok gudang M03 (master ERP juga memuat mesin & aset area produksi — tidak dihitung)
        $totalAset = Inventory::where('lokasi_rak', 'like', 'M03-%')->sum(DB::raw('stock * harga_beli_terakhir'));

        $biayaBulanIni  = $a->periode($a->queryBiayaOut(), $awalBulanIni, $akhirBulanIni)->sum('total_harga');
        $biayaBulanLalu = $a->periode($a->queryBiayaOut(), $awalBulanLalu, $akhirBulanLalu)->sum('total_harga');
        $trenBiaya = $biayaBulanLalu > 0 ? (($biayaBulanIni - $biayaBulanLalu) / $biayaBulanLalu) * 100 : 0;

        $awalFY = AnalitikBiayaService::awalFY();
        $labelFY = AnalitikBiayaService::labelFY($awalFY);
        $biayaFY = $a->periode($a->queryBiayaOut(), $awalFY, now())->sum('total_harga');
        $beliFY = $a->periode($a->queryPembelian(), $awalFY, now())->sum('total_harga');

        $rincian = fn ($awal, $akhir) => $a->periode($a->queryBiayaOut(), $awal, $akhir)
            ->join('inventories', 'inventory_transactions.inventory_id', '=', 'inventories.id')
            ->select('inventories.deskripsi', DB::raw('SUM(inventory_transactions.total_harga) as total_biaya'), DB::raw('SUM(inventory_transactions.qty) as total_qty'))
            ->groupBy('inventories.id', 'inventories.deskripsi')
            ->orderByDesc('total_biaya')->limit(10)->get();
        $rincianBiayaBulanIni  = $rincian($awalBulanIni, $akhirBulanIni);
        $rincianBiayaBulanLalu = $rincian($awalBulanLalu, $akhirBulanLalu);

        // Indikator operasional
        $restock = $a->ringkasanRestock();
        $aktivitasHariIni = DB::table('history_logs')->whereNull('source_ref')->whereDate('created_at', today())->count();
        $rekonStat = $rekon->laporan(now()->subDays(30)->startOfDay(), now()->endOfDay())['statistik'];
        $kualitasFY = $a->kualitasPembelian($awalFY, now(), $a->cakupanData());
        $beliPerluDicek = collect(['perlu_dicek', 'terlalu_cepat', 'belum_terpakai'])
            ->sum(fn ($s) => $kualitasFY['per_status'][$s]['jumlah'] ?? 0);

        // Grafik 12 bulan: pembelian vs pemakaian
        $grafikData = [];
        for ($i = 11; $i >= 0; $i--) {
            $bln = now()->subMonthsNoOverflow($i);
            $awal = $bln->copy()->startOfMonth();
            $akhir = $bln->copy()->endOfMonth();
            $grafikData[] = [
                'label' => $bln->translatedFormat('M y'),
                'total' => (float) $a->periode($a->queryBiayaOut(), $awal, $akhir)->sum('total_harga'),
                'beli'  => (float) $a->periode($a->queryPembelian(), $awal, $akhir)->sum('total_harga'),
            ];
        }

        // Top 5 mesin — 90 hari terakhir (Job PM yang sudah dipetakan digabung ke nama mesinnya)
        $awalTop = now()->subDays(90)->startOfDay();
        $mesinTerboros = $a->topJob($awalTop, now())->map(fn ($m) => (object) [
            'machine' => $m->job, 'total_qty' => $m->qty, 'total_biaya' => $m->rp, 'freq' => $m->frek,
            'jumlah_job' => (int) $m->jumlah_job, 'belum_dipetakan' => (bool) $m->belum_dipetakan,
        ]);
        $detailMesin = $a->rincianPerMesin($awalTop, now(), $mesinTerboros->pluck('machine'));
        $jobBelumDipetakan = (clone $a->daftarJob(null, 'belum'))->get()->count();

        return view('dashboard', compact(
            'totalSparePart', 'totalAset', 'biayaBulanIni', 'biayaBulanLalu', 'trenBiaya',
            'rincianBiayaBulanIni', 'rincianBiayaBulanLalu', 'aktivitasHariIni',
            'restock', 'rekonStat', 'beliPerluDicek', 'grafikData', 'mesinTerboros', 'detailMesin',
            'biayaFY', 'beliFY', 'labelFY', 'jobBelumDipetakan'
        ));
    }

    public function restock(Request $request)
    {
        $q = $request->get('q');
        $barang = $this->queryRestock($q)->paginate(20)->withQueryString();

        $ringkasan = $this->analitik->ringkasanRestock();
        $cakupan = $this->analitik->cakupanData();

        return view('restock.index', compact('barang', 'q', 'ringkasan', 'cakupan'));
    }

    /** Daftar Perlu Restock (mengikuti pencarian) sebagai Purchase Request Excel. */
    public function exportRestock(Request $request, PurchaseRequestExcel $excel)
    {
        $q = $request->get('q');
        $barang = $this->queryRestock($q)
            // "penting" = habis atau stok cukup < 30 hari (stok < pemakaian 30 hari).
            ->when($request->get('prioritas') === 'penting', fn ($query) => $query->whereRaw('inventories.stock * 3 < p.pakai_90'))
            ->get();

        $nomor = 'PR-MTC-' . now()->format('ymd-Hi');
        if ($request->get('format') === 'csv') {
            return Ekspor::unduhCsv($nomor . '.csv', $excel->header(), $excel->barisCsv($barang));
        }

        $buku = $excel->buat($barang, [
            'nomor'   => $nomor,
            'tanggal' => now()->translatedFormat('d F Y'),
            'pemohon' => $request->user()->name,
            'dasar'   => __('Stock < usage in the last 90 days; suggested qty refills to :n months of demand.', ['n' => AnalitikBiayaService::BULAN_TARGET_STOK])
                . ($q ? ' ' . __('Filter: ":q".', ['q' => $q]) : ''),
        ]);

        return Ekspor::unduhXlsx($buku, $nomor . '.xlsx');
    }

    private function queryRestock(?string $q)
    {
        return $this->analitik->queryPerluRestock()
            ->when($q, function ($query) use ($q) {
                // Dibungkus closure: tanpa ini orWhere ikut meloloskan part yang stoknya aman.
                $query->where(function ($sub) use ($q) {
                    $sub->where('inventories.part_no', 'like', "%$q%")->orWhere('inventories.deskripsi', 'like', "%$q%");
                });
            })
            // Paling kritis dulu: stok terkecil dibanding kebutuhan 3 bulan
            ->orderByRaw('inventories.stock / p.pakai_90')
            ->orderByDesc('p.pakai_90');
    }
}
