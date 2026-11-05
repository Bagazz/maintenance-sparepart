<?php

namespace App\Services;

use App\Models\HistoryLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Rekonsiliasi pengambilan barang yang di-scan di kiosk dengan pemakaian resmi di ERP (sheet Issue).
 *
 *   Scan   = history_logs tanpa source_ref, aksi 'pakai' (barang habis pakai dari aplikasi)
 *   ERP    = history_logs dengan source_ref, aksi 'pakai' (baris STK-MTL hasil import)
 *
 * Hasil yang dicari:
 *   - Cocok              : barang di-scan DAN sudah diposting di ERP
 *   - Scan belum di ERP  : diambil lewat kiosk tapi belum diinput kepala sparepart ke ERP
 *   - ERP tanpa scan     : keluar menurut ERP tapi tidak pernah di-scan -> indikasi barang
 *                          diambil tanpa dicatat (masalah utama shift 2 & 3)
 *
 * Aturan pasangan otomatis: part sama, tanggal ERP antara 1 hari sebelum s/d 14 hari sesudah
 * scan, dan (No PM sama ATAU qty bersih sama). Kandidat dengan skor tertinggi dipilih.
 */
class RekonsiliasiService
{
    public const HARI_SEBELUM = 1;
    public const HARI_SESUDAH = 14;
    /** Scan yang belum diposting lebih lama dari ini dianggap terlambat posting. */
    public const BATAS_POSTING_HARI = 3;

    public function queryScan()
    {
        return HistoryLog::query()->whereNull('source_ref')->where('aksi', 'pakai');
    }

    public function queryErp()
    {
        return HistoryLog::query()->whereNotNull('source_ref')->where('aksi', 'pakai');
    }

    /**
     * Tanggal pertama kiosk dipakai (scan pertama oleh karyawan terdaftar).
     * Baris ERP sebelum tanggal ini tidak bisa dinilai "tanpa scan".
     */
    public function tanggalMulaiScan(): ?Carbon
    {
        $min = $this->queryScan()->whereNotNull('karyawan_id')->min('created_at');

        return $min ? Carbon::parse($min)->startOfDay() : null;
    }

    private function qtyBersih(HistoryLog $log): int
    {
        return (int) $log->jumlah - (int) $log->qty_returned;
    }

    private function skor(HistoryLog $scan, HistoryLog $erp): ?float
    {
        $pmSama = $scan->no_pm && $erp->no_pm && strtoupper($scan->no_pm) === strtoupper($erp->no_pm);
        $qtySama = $this->qtyBersih($scan) === (int) $erp->jumlah;

        if (!$pmSama && !$qtySama) {
            return null;
        }

        $selisihHari = abs($scan->created_at->copy()->startOfDay()->diffInDays($erp->created_at->copy()->startOfDay(), false));

        return ($pmSama ? 4 : 0) + ($qtySama ? 2 : 0) - $selisihHari * 0.1;
    }

    /** Jalankan pencocokan otomatis untuk semua baris yang belum berpasangan. Mengembalikan jumlah pasangan baru. */
    public function jalankan(): int
    {
        $scans = $this->queryScan()->whereNull('rekon_pasangan_id')->where('rekon_abaikan', false)
            ->orderBy('created_at')->get();

        if ($scans->isEmpty()) {
            return 0;
        }

        $awal = $scans->min('created_at')->copy()->subDays(self::HARI_SEBELUM)->startOfDay();
        $erpPerPart = $this->queryErp()->whereNull('rekon_pasangan_id')->where('rekon_abaikan', false)
            ->where('created_at', '>=', $awal)
            ->whereIn('part_no', $scans->pluck('part_no')->unique())
            ->orderBy('created_at')
            ->get()
            ->groupBy('part_no');

        $pasangan = 0;

        DB::transaction(function () use ($scans, $erpPerPart, &$pasangan) {
            foreach ($scans as $scan) {
                $kandidat = $erpPerPart->get($scan->part_no, collect())
                    ->filter(fn ($erp) => !$erp->rekon_pasangan_id
                        && $erp->created_at->gte($scan->created_at->copy()->startOfDay()->subDays(self::HARI_SEBELUM))
                        && $erp->created_at->lte($scan->created_at->copy()->endOfDay()->addDays(self::HARI_SESUDAH)));

                $terbaik = null;
                $skorTerbaik = null;
                foreach ($kandidat as $erp) {
                    $s = $this->skor($scan, $erp);
                    if ($s !== null && ($skorTerbaik === null || $s > $skorTerbaik)) {
                        [$terbaik, $skorTerbaik] = [$erp, $s];
                    }
                }

                if ($terbaik) {
                    $this->pasangkan($scan, $terbaik, 'otomatis');
                    $terbaik->rekon_pasangan_id = $scan->id; // tandai di koleksi supaya tidak dipakai dua kali
                    $pasangan++;
                }
            }
        });

        return $pasangan;
    }

    public function pasangkan(HistoryLog $scan, HistoryLog $erp, string $metode = 'manual'): void
    {
        DB::table('history_logs')->where('id', $scan->id)->update(['rekon_pasangan_id' => $erp->id, 'rekon_metode' => $metode, 'rekon_abaikan' => false]);
        DB::table('history_logs')->where('id', $erp->id)->update(['rekon_pasangan_id' => $scan->id, 'rekon_metode' => $metode, 'rekon_abaikan' => false]);
    }

    public function lepas(HistoryLog $log): void
    {
        DB::table('history_logs')->whereIn('id', array_filter([$log->id, $log->rekon_pasangan_id]))
            ->update(['rekon_pasangan_id' => null, 'rekon_metode' => null]);
    }

    /** Kandidat ERP untuk dipasangkan manual dengan satu scan (part sama, ±30 hari, belum berpasangan). */
    public function kandidatManual(HistoryLog $scan): Collection
    {
        return $this->queryErp()->whereNull('rekon_pasangan_id')
            ->where('part_no', $scan->part_no)
            ->whereBetween('created_at', [$scan->created_at->copy()->subDays(30), $scan->created_at->copy()->addDays(30)])
            ->orderBy('created_at')
            ->limit(20)
            ->get();
    }

    /** Nilai Rp tiap baris history (dari transaksi OUT terkait). */
    private function nilai(Collection $logs, bool $erp): array
    {
        if ($logs->isEmpty()) {
            return [];
        }

        $q = DB::table('inventory_transactions')->where('type', 'OUT');
        return $erp
            ? $q->whereIn('source_ref', $logs->pluck('source_ref'))->pluck('total_harga', 'source_ref')->map(fn ($v) => (float) $v)->all()
            : $q->whereIn('history_log_id', $logs->pluck('id'))->groupBy('history_log_id')
                ->selectRaw('history_log_id, SUM(total_harga) as total')->pluck('total', 'history_log_id')->map(fn ($v) => (float) $v)->all();
    }

    /**
     * Ringkasan + daftar per kategori untuk periode [awal, akhir].
     */
    public function laporan(Carbon $awal, Carbon $akhir): array
    {
        $mulaiScan = $this->tanggalMulaiScan();

        $scan = $this->queryScan()->with('karyawan')->whereBetween('created_at', [$awal, $akhir])->orderByDesc('created_at')->get();

        // ERP hanya dinilai sejak kiosk mulai dipakai.
        $erp = $mulaiScan
            ? $this->queryErp()->whereBetween('created_at', [$awal->max($mulaiScan), $akhir])->orderByDesc('created_at')->get()
            : collect();

        $nilaiScan = $this->nilai($scan, false);
        $nilaiErp = $this->nilai($erp, true);
        $rpScan = fn ($l) => $nilaiScan[$l->id] ?? 0;
        $rpErp = fn ($l) => $nilaiErp[$l->source_ref] ?? 0;

        $pasanganErp = HistoryLog::whereIn('id', $scan->pluck('rekon_pasangan_id')->filter())->get()->keyBy('id');

        $cocok = $scan->filter(fn ($l) => $l->rekon_pasangan_id)
            ->each(fn ($l) => $l->setRelation('pasangan', $pasanganErp->get($l->rekon_pasangan_id)));
        $scanBelum = $scan->filter(fn ($l) => !$l->rekon_pasangan_id && !$l->rekon_abaikan);
        $erpTanpaScan = $erp->filter(fn ($l) => !$l->rekon_pasangan_id && !$l->rekon_abaikan);
        $selisihQty = $cocok->filter(fn ($l) => $l->pasangan && $this->qtyBersih($l) !== (int) $l->pasangan->jumlah);

        $dinilai = $scan->count() + $erp->filter(fn ($l) => !$l->rekon_pasangan_id)->count();

        return [
            'mulai_scan'       => $mulaiScan,
            'cocok'            => $cocok,
            'scan_belum'       => $scanBelum,
            'erp_tanpa_scan'   => $erpTanpaScan,
            'selisih_qty'      => $selisihQty,
            'nilai_scan'       => $nilaiScan,
            'nilai_erp'        => $nilaiErp,
            'statistik'        => [
                'scan_total'        => $scan->count(),
                'erp_total'         => $erp->count(),
                'cocok'             => $cocok->count(),
                'scan_belum'        => $scanBelum->count(),
                'scan_terlambat'    => $scanBelum->filter(fn ($l) => $l->created_at->lt(now()->subDays(self::BATAS_POSTING_HARI)))->count(),
                'scan_belum_rp'     => $scanBelum->sum($rpScan),
                'erp_tanpa_scan'    => $erpTanpaScan->count(),
                'erp_tanpa_scan_rp' => $erpTanpaScan->sum($rpErp),
                'selisih_qty'       => $selisihQty->count(),
                'persen_cocok'      => $dinilai ? round($cocok->count() / $dinilai * 100, 1) : null,
            ],
        ];
    }
}
