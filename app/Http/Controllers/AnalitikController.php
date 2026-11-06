<?php

namespace App\Http\Controllers;

use App\Services\AnalitikBiayaService;
use App\Services\LaporanBulananExcel;
use App\Services\LaporanBulananService;
use App\Support\Ekspor;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Analitik Biaya Spare Part (untuk Presdir & manager): arus uang, kualitas pembelian,
 * uang mengendap, pemakai terbesar, dan indikasi kerugian.
 */
class AnalitikController extends Controller
{
    public function index(Request $request, AnalitikBiayaService $analitik, LaporanBulananService $bulanan)
    {
        [$awal, $akhir, $label, $periode] = $this->periode($request);

        $laporan = $analitik->laporan($awal, $akhir);
        $filterStatus = $request->get('status');

        return view('analitik.index', [
            'laporan'      => $laporan,
            'awal'         => $awal,
            'akhir'        => $akhir,
            'labelPeriode' => $label,
            'periode'      => $periode,
            'pilihan'      => $this->pilihanPeriode(),
            'filterStatus' => array_key_exists($filterStatus, AnalitikBiayaService::STATUS_BELI) ? $filterStatus : null,
            'pilihanBulan' => $bulanan->pilihanBulan(),
            'bulanBawaan'  => $bulanan->bulanBawaan(),
        ]);
    }

    /** Laporan bulanan untuk Presdir: PDF (untuk dibaca/diteruskan), Excel (untuk ditelusuri) atau CSV (untuk diolah sistem lain). */
    public function laporanBulanan(Request $request, LaporanBulananService $bulanan, LaporanBulananExcel $excel)
    {
        $data = $request->validate([
            'bulan'  => ['required', 'in:' . implode(',', array_keys($bulanan->pilihanBulan()))],
            'format' => ['required', 'in:pdf,xlsx,csv'],
        ]);
        $isi = $bulanan->data($data['bulan']);
        $nama = 'Spare-Part-Report-' . $data['bulan'];

        if ($data['format'] === 'pdf') {
            ini_set('memory_limit', '512M');   // dompdf: cadangan memori untuk laporan yang panjang
            return Pdf::loadView('analitik.laporan-pdf', $isi)->setOption('enable_font_subsetting', true)->setPaper('a4')->download($nama . '.pdf');
        }

        // CSV: satu file per tabel (ringkasan, poin utama, tren, mesin, pembelian, restock, uang mengendap) dalam satu ZIP.
        if ($data['format'] === 'csv') {
            return Ekspor::unduhCsvZip($nama . '-CSV.zip', $excel->tabelCsv($isi));
        }

        return Ekspor::unduhXlsx($excel->buat($isi), $nama . '.xlsx');
    }

    /** Pilihan: fiscal year (Jul–Jun) yang punya data, plus jendela waktu umum. */
    private function pilihanPeriode(): array
    {
        $pertama = DB::table('inventory_transactions')->min('tanggal_transaksi');
        $awalData = AnalitikBiayaService::awalFY($pertama ? Carbon::parse($pertama) : now());

        $opsi = [];
        for ($fy = AnalitikBiayaService::awalFY(); $fy->gte($awalData); $fy->subYear()) {
            $opsi['fy-' . $fy->year] = AnalitikBiayaService::labelFY($fy) . ($fy->eq(AnalitikBiayaService::awalFY()) ? ' (' . __('current') . ')' : '');
        }

        return $opsi + [
            'bulan-ini' => __('This month'),
            '3-bulan'   => __('Last 3 months'),
            '12-bulan'  => __('Last 12 months'),
            'kustom'    => __('Date range...'),
        ];
    }

    private function periode(Request $request): array
    {
        $p = $request->get('periode', 'fy-' . AnalitikBiayaService::awalFY()->year);

        if (preg_match('/^fy-(\d{4})$/', $p, $m)) {
            $awal = Carbon::create((int) $m[1], 7, 1)->startOfDay();
            return [$awal, $awal->copy()->addYear()->subDay()->endOfDay(), AnalitikBiayaService::labelFY($awal), $p];
        }

        return match ($p) {
            'bulan-ini' => [now()->startOfMonth(), now()->endOfDay(), now()->translatedFormat('F Y'), $p],
            '3-bulan'   => [now()->subMonthsNoOverflow(3)->startOfDay(), now()->endOfDay(), __('Last 3 months'), $p],
            '12-bulan'  => [now()->subYear()->startOfDay(), now()->endOfDay(), __('Last 12 months'), $p],
            'kustom'    => $this->kustom($request),
            default     => [AnalitikBiayaService::awalFY(), now()->endOfDay(), AnalitikBiayaService::labelFY(AnalitikBiayaService::awalFY()), 'fy-' . AnalitikBiayaService::awalFY()->year],
        };
    }

    private function kustom(Request $request): array
    {
        try {
            $awal = Carbon::parse($request->get('dari', now()->subMonth()))->startOfDay();
            $akhir = Carbon::parse($request->get('sampai', now()))->endOfDay();
        } catch (\Exception $e) {
            [$awal, $akhir] = [now()->subMonth()->startOfDay(), now()->endOfDay()];
        }
        if ($awal->gt($akhir)) {
            [$awal, $akhir] = [$akhir->copy()->startOfDay(), $awal->copy()->endOfDay()];
        }

        return [$awal, $akhir, $awal->translatedFormat('d M Y') . ' – ' . $akhir->translatedFormat('d M Y'), 'kustom'];
    }
}
