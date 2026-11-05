<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Laporan bulanan spare part untuk Presdir: satu bulan dibanding bulan lalu & FY berjalan,
 * disusun dari angka yang sama dengan halaman Analitik Biaya.
 */
class LaporanBulananService
{
    public function __construct(private AnalitikBiayaService $a, private StockOpnameService $opname)
    {
    }

    /** Bulan yang bisa dipilih: dari data ERP pertama sampai bulan ini (terbaru di atas). */
    public function pilihanBulan(): array
    {
        $awal = $this->a->cakupanData()['awal'] ?? now();
        $opsi = [];
        for ($b = now()->startOfMonth(); $b->gte($awal->copy()->startOfMonth()); $b->subMonthNoOverflow()) {
            $opsi[$b->format('Y-m')] = $b->translatedFormat('F Y') . ($b->isSameMonth(now()) ? ' (' . __('to date') . ')' : '');
        }

        return $opsi;
    }

    /** Bulan bawaan: bulan lalu (sudah lengkap); bila data baru ada bulan ini, pakai bulan ini. */
    public function bulanBawaan(): string
    {
        $opsi = array_keys($this->pilihanBulan());

        return $opsi[1] ?? $opsi[0];
    }

    public function data(string $bulan): array
    {
        $awal = Carbon::createFromFormat('Y-m-d', $bulan . '-01')->startOfDay();
        $akhir = $awal->copy()->endOfMonth()->min(now()->endOfDay());
        $lap = $this->a->laporan($awal, $akhir);

        $jumlah = fn (Carbon $dari, Carbon $sampai) => [
            'beli'  => (float) $this->a->periode($this->a->queryPembelian(), $dari, $sampai)->sum('total_harga'),
            'pakai' => (float) $this->a->periode($this->a->queryBiayaOut(), $dari, $sampai)->sum('total_harga'),
        ];
        $laluAwal = $awal->copy()->subMonthNoOverflow()->startOfMonth();
        $lalu = $jumlah($laluAwal, $laluAwal->copy()->endOfMonth());
        $awalFY = AnalitikBiayaService::awalFY($awal);
        $fy = $jumlah($awalFY, $akhir);

        $ini = ['beli' => $lap['ringkasan']['pembelian'], 'pakai' => $lap['ringkasan']['pemakaian']];
        $ubah = fn ($baru, $lama) => $lama > 0 ? round(($baru - $lama) / $lama * 100, 1) : null;

        $kualitas = $lap['kualitas'];
        $berisiko = collect(['perlu_dicek', 'terlalu_cepat', 'belum_terpakai'])->sum(fn ($s) => $kualitas['per_status'][$s]['rp'] ?? 0);

        $restockTop = $this->a->queryPerluRestock()
            ->orderByRaw('inventories.stock / p.pakai_90')->orderByDesc('p.pakai_90')->limit(10)->get();

        $data = [
            'bulan'      => $bulan,
            'judul'      => $awal->translatedFormat('F Y'),
            'awal'       => $awal,
            'akhir'      => $akhir,
            'sebagian'   => $akhir->lt($awal->copy()->endOfMonth()),
            'label_lalu' => $laluAwal->translatedFormat('F Y'),
            'label_fy'   => AnalitikBiayaService::labelFY($awalFY),
            'ini'        => $ini + ['tertahan' => $ini['beli'] - $ini['pakai'], 'berisiko' => $berisiko],
            'lalu'       => $lalu,
            'fy'         => $fy + ['tertahan' => $fy['beli'] - $fy['pakai']],
            'ubah_beli'  => $ubah($ini['beli'], $lalu['beli']),
            'ubah_pakai' => $ubah($ini['pakai'], $lalu['pakai']),
            'arus'       => $lap['arus'],
            'tren'       => $this->a->trenBulanan($awal->copy()->subMonthsNoOverflow(11)->startOfMonth(), $akhir),
            'top_mesin'  => $lap['top_job'],
            'top_beli'   => $lap['top_beli'],
            'kualitas'   => $kualitas,
            'mengendap'  => $lap['mengendap'],
            'restock'    => $lap['restock'],
            'restock_top' => $restockTop,
            'kerugian'   => $lap['kerugian'],
            'cakupan'    => $lap['cakupan'],
            'dibuat'     => now(),
        ];
        $data['sorotan'] = $this->sorotan($data);

        return $data;
    }

    /** Poin-poin utama otomatis (bahasa sederhana untuk pimpinan). */
    private function sorotan(array $d): array
    {
        $rp = fn ($n) => 'Rp ' . number_format($n, 0, ',', '.');
        $poin = [];

        if ($d['ubah_pakai'] !== null) {
            $poin[] = __('Spare part usage was :rp, :arah :pct% vs :lalu.', [
                'rp' => $rp($d['ini']['pakai']), 'arah' => $d['ubah_pakai'] >= 0 ? __('up') : __('down'),
                'pct' => abs($d['ubah_pakai']), 'lalu' => $d['label_lalu'],
            ]);
        } else {
            $poin[] = __('Spare part usage was :rp.', ['rp' => $rp($d['ini']['pakai'])]);
        }

        $poin[] = $d['ini']['tertahan'] > 0
            ? __('Purchases (:beli) exceeded usage by :rp — this money is now stock on the racks.', ['beli' => $rp($d['ini']['beli']), 'rp' => $rp($d['ini']['tertahan'])])
            : __('Purchases (:beli) did not exceed usage — no new money was tied up in stock.', ['beli' => $rp($d['ini']['beli'])]);

        if ($d['ini']['berisiko'] > 0) {
            $poin[] = __(':rp of purchases need review (stock was still available, bought too early, or not used yet).', ['rp' => $rp($d['ini']['berisiko'])]);
        }
        if ($m = $d['top_mesin']->first()) {
            $poin[] = __('Highest-cost machine: :mesin (:rp).', ['mesin' => $m->job, 'rp' => $rp($m->rp)]);
        }
        if ($d['kerugian']['erp_tanpa_scan']) {
            $poin[] = __(':n ERP issues (:rp) were never scanned at the kiosk — follow up with the technicians.', ['n' => $d['kerugian']['erp_tanpa_scan'], 'rp' => $rp($d['kerugian']['erp_tanpa_scan_rp'])]);
        }
        if ($d['kerugian']['opname']['kurang_rp'] > 0) {
            $poin[] = __('Stock counts found :rp of missing items.', ['rp' => $rp($d['kerugian']['opname']['kurang_rp'])]);
        }
        $poin[] = __(':n parts need restocking now (estimated :rp), :habis of them out of stock.', [
            'n' => $d['restock']['jumlah'], 'rp' => $rp($d['restock']['rp']), 'habis' => $d['restock']['habis'],
        ]);

        return $poin;
    }
}
