<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Biaya spare part per mesin. "Mesin" = nama mesin dari padanan Job PM; Job yang belum dipetakan
 * diperlakukan sebagai mesin tersendiri bernama nomor Job (sama seperti Top 5 di dashboard).
 */
class MesinAnalitikService
{
    private const NAMA = AnalitikBiayaService::NAMA_MESIN;
    private const TGL = AnalitikBiayaService::TANGGAL;

    public function __construct(private AnalitikBiayaService $a)
    {
    }

    /** Pilihan periode: [awal, akhir, label]. */
    public function periode(string $kode): array
    {
        $cakupan = $this->a->cakupanData();

        return match ($kode) {
            'fy'    => [AnalitikBiayaService::awalFY(), now()->endOfDay(), AnalitikBiayaService::labelFY(AnalitikBiayaService::awalFY())],
            'semua' => [($cakupan['awal'] ?? now())->copy()->startOfDay(), now()->endOfDay(), __('All data')],
            default => [now()->subMonthsNoOverflow(12)->startOfMonth(), now()->endOfDay(), __('Last 12 months')],
        };
    }

    private function dasar(Carbon $awal, Carbon $akhir)
    {
        return $this->a->denganMesin($this->a->periode($this->a->queryBiayaOut(), $awal, $akhir));
    }

    private function untuk(string $nama, Carbon $awal, Carbon $akhir)
    {
        return $this->dasar($awal, $akhir)->whereRaw(self::NAMA . ' = ?', [$nama]);
    }

    /** Semua mesin dengan biaya di periode, terbesar dulu, plus perbandingan periode sebelumnya. */
    public function daftar(Carbon $awal, Carbon $akhir): Collection
    {
        $hari = $awal->diffInDays($akhir) + 1;
        $sebelum = $this->dasar($awal->copy()->subDays($hari), $awal->copy()->subDay()->endOfDay())
            ->selectRaw(self::NAMA . ' as nama, SUM(total_harga) as rp')->groupBy('nama')->pluck('rp', 'nama');

        return $this->dasar($awal, $akhir)
            ->selectRaw(self::NAMA . ' as nama, SUM(total_harga) as rp, SUM(qty) as qty, SUM(qty > 0) as frek,
                COUNT(DISTINCT inventory_transactions.inventory_id) as jumlah_part,
                COUNT(DISTINCT inventory_transactions.mesin) as jumlah_job,
                MAX(' . self::TGL . ') as terakhir,
                MAX(CASE WHEN jm.id IS NULL AND inventory_transactions.mesin LIKE \'PM%\' THEN 1 ELSE 0 END) as belum_dipetakan')
            ->groupBy('nama')
            ->orderByDesc('rp')
            ->get()
            ->map(function ($m) use ($sebelum) {
                $m->rp_sebelum = (float) ($sebelum[$m->nama] ?? 0);
                $m->ubah = $m->rp_sebelum > 0 ? round(($m->rp - $m->rp_sebelum) / $m->rp_sebelum * 100, 1) : null;
                return $m;
            });
    }

    public function detail(string $nama, Carbon $awal, Carbon $akhir): ?array
    {
        $daftar = $this->daftar($awal, $akhir);
        $posisi = $daftar->search(fn ($m) => $m->nama === $nama);
        $ringkas = $posisi === false ? null : $daftar[$posisi];

        // Mesin tanpa pemakaian di periode ini tetap boleh dibuka bila pernah ada transaksinya.
        $pernahAda = $ringkas || $this->untuk($nama, Carbon::create(2000), now()->endOfDay())->exists()
            || DB::table('job_mesins')->where('mesin', $nama)->exists()
            || DB::table('mesins')->where('nama', $nama)->exists();
        if (!$pernahAda) {
            return null;
        }

        $bulan = max(1, (int) ceil($awal->floatDiffInMonths($akhir)));

        return [
            'nama'      => $nama,
            'ringkas'   => $ringkas,
            'peringkat' => $posisi === false ? null : $posisi + 1,
            'dari'      => $daftar->count(),
            'per_bulan' => $ringkas ? $ringkas->rp / $bulan : 0,
            'jobs'      => $this->perJob($nama, $awal, $akhir),
            'job_peta'  => DB::table('job_mesins')->where('mesin', $nama)->orderBy('no_job')->pluck('no_job'),
            'tren'      => $this->tren($nama, $akhir),
            'sering'    => $this->partSering($nama, $awal, $akhir),
            'riwayat'   => $this->riwayat($nama, $awal, $akhir),
        ];
    }

    /** Biaya per bulan, 12 bulan terakhir sampai akhir periode. */
    public function tren(string $nama, Carbon $akhir): array
    {
        $mulai = $akhir->copy()->subMonthsNoOverflow(11)->startOfMonth();
        $data = $this->untuk($nama, $mulai, $akhir)
            ->selectRaw('DATE_FORMAT(' . self::TGL . ", '%Y-%m') as bln, SUM(total_harga) as rp, SUM(qty > 0) as frek")
            ->groupBy('bln')->get()->keyBy('bln');

        $hasil = [];
        for ($b = $mulai->copy(); $b->lte($akhir); $b->addMonthNoOverflow()) {
            $k = $b->format('Y-m');
            $hasil[] = ['label' => $b->translatedFormat('M y'), 'rp' => (float) ($data[$k]->rp ?? 0), 'frek' => (int) ($data[$k]->frek ?? 0)];
        }

        return $hasil;
    }

    /**
     * Part yang paling sering diganti di mesin ini. Bila diganti ≥ 2 kali, dihitung rata-rata jarak antar
     * penggantian dan perkiraan penggantian berikutnya — bahan untuk preventive maintenance & stok minimum.
     */
    public function partSering(string $nama, Carbon $awal, Carbon $akhir, int $limit = 15): Collection
    {
        return $this->untuk($nama, $awal, $akhir)
            ->join('inventories as i', 'i.id', '=', 'inventory_transactions.inventory_id')
            ->selectRaw('i.id, i.part_no, i.deskripsi, i.lokasi_rak, i.stock, SUM(inventory_transactions.qty > 0) as kali,
                SUM(inventory_transactions.qty) as qty, SUM(inventory_transactions.total_harga) as rp,
                MIN(' . self::TGL . ') as pertama, MAX(' . self::TGL . ') as terakhir')
            ->groupBy('i.id', 'i.part_no', 'i.deskripsi', 'i.lokasi_rak', 'i.stock')
            ->orderByDesc('kali')->orderByDesc('rp')
            ->limit($limit)
            ->get()
            ->map(function ($p) {
                $p->pertama = Carbon::parse($p->pertama);
                $p->terakhir = Carbon::parse($p->terakhir);
                $p->jarak = $p->kali >= 2 ? (int) round($p->pertama->diffInDays($p->terakhir) / ($p->kali - 1)) : null;
                $p->berikut = $p->jarak ? $p->terakhir->copy()->addDays($p->jarak) : null;
                return $p;
            });
    }

    public function perJob(string $nama, Carbon $awal, Carbon $akhir): Collection
    {
        return $this->untuk($nama, $awal, $akhir)
            ->selectRaw("COALESCE(inventory_transactions.mesin, '-') as job, SUM(total_harga) as rp, SUM(qty > 0) as frek, MAX(" . self::TGL . ') as terakhir')
            ->groupBy('job')->orderByDesc('rp')->get();
    }

    public function riwayat(string $nama, Carbon $awal, Carbon $akhir, int $limit = 50): Collection
    {
        return $this->untuk($nama, $awal, $akhir)
            ->join('inventories as i', 'i.id', '=', 'inventory_transactions.inventory_id')
            ->leftJoin('history_logs as h', 'h.id', '=', 'inventory_transactions.history_log_id')
            ->selectRaw(self::TGL . ' as tgl, i.part_no, i.deskripsi, inventory_transactions.qty, inventory_transactions.total_harga as rp,
                inventory_transactions.mesin as job, inventory_transactions.source_type as sumber, h.peminjam')
            ->orderByDesc('tgl')->orderByDesc('inventory_transactions.id')
            ->limit($limit)
            ->get();
    }
}
