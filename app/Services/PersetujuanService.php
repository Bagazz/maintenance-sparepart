<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\Karyawan;
use App\Models\Pengaturan;
use App\Models\PermintaanAmbil;
use Illuminate\Support\Facades\DB;

/**
 * Persetujuan pengambilan oleh Kepala Spare Part (hanya admin).
 * Aturan (diatur di layar Persetujuan):
 *  - "batas" : baris yang nilainya >= batas Rupiah, atau jumlahnya >= batas pcs (0 = tidak dipakai), perlu disetujui;
 *              bila total satu konfirmasi >= batas total (0 = tidak dipakai), SEMUA barangnya perlu disetujui
 *  - "semua" : semua pengambilan teknisi perlu disetujui
 *  - "mati"  : tidak ada persetujuan
 * Pengambilan oleh admin sendiri tidak pernah perlu persetujuan.
 */
class PersetujuanService
{
    public const MODE = ['batas', 'semua', 'mati'];
    public const BAWAAN_NILAI = 1000000;

    public function __construct(private PengambilanService $pengambilan)
    {
    }

    /** @return array{mode: string, nilai: float, qty: int, total: float} */
    public static function aturan(): array
    {
        $mode = Pengaturan::ambil('persetujuan_mode', 'batas');

        return [
            'mode'  => in_array($mode, self::MODE, true) ? $mode : 'batas',
            'nilai' => (float) Pengaturan::ambil('persetujuan_nilai', self::BAWAAN_NILAI),
            'qty'   => (int) Pengaturan::ambil('persetujuan_qty', 0),
            'total' => (float) Pengaturan::ambil('persetujuan_total', 0),
        ];
    }

    /** Apakah satu baris keranjang teknisi perlu persetujuan? */
    public static function perlu(float $hargaSatuan, int $jumlah, ?array $aturan = null): bool
    {
        $a = $aturan ?? self::aturan();

        return match ($a['mode']) {
            'semua' => true,
            'mati'  => false,
            default => ($a['nilai'] > 0 && $hargaSatuan * $jumlah >= $a['nilai']) || ($a['qty'] > 0 && $jumlah >= $a['qty']),
        };
    }

    /**
     * Baris keranjang yang perlu persetujuan. Bila total satu konfirmasi mencapai batas total,
     * seluruh keranjang ikut menunggu (mencegah banyak barang murah yang totalnya besar lolos).
     *
     * @param array $keranjang baris keranjang (part_no, jumlah)
     * @param \ArrayAccess|array $harga part_no => harga satuan
     * @return array<int, bool> indeks baris => perlu persetujuan
     */
    public static function tandaiKeranjang(array $keranjang, $harga, ?array $aturan = null): array
    {
        $a = $aturan ?? self::aturan();
        $total = collect($keranjang)->sum(fn ($b) => (float) ($harga[$b['part_no']] ?? 0) * (int) $b['jumlah']);
        $semua = $a['mode'] === 'batas' && $a['total'] > 0 && $total >= $a['total'];

        return collect($keranjang)->map(fn ($b) => $semua || self::perlu((float) ($harga[$b['part_no']] ?? 0), (int) $b['jumlah'], $a))->all();
    }

    /**
     * Buat satu permintaan dari baris-baris keranjang yang perlu persetujuan.
     * Stok belum berkurang; jumlahnya dipesan sampai disetujui / ditolak.
     *
     * @param array<int, array{part_no: string, jumlah: int, mesin: string, no_pm: ?string}> $baris
     */
    public function ajukan(array $baris, ?Karyawan $karyawan): PermintaanAmbil
    {
        return DB::transaction(function () use ($baris, $karyawan) {
            $p = PermintaanAmbil::create([
                'kode'      => PermintaanAmbil::kodeBaru(),
                'karyawan_id' => $karyawan?->id,
                'peminjam'  => $karyawan?->nama ?? auth()->user()->name,
                'status'    => 'menunggu',
            ]);

            $total = 0;
            foreach ($baris as $b) {
                $item = Inventory::where('part_no', $b['part_no'])->lockForUpdate()->firstOrFail();
                $jumlah = (int) $b['jumlah'];
                if ($jumlah > PengambilanService::tersedia($item)) {
                    throw new \RuntimeException(__('Not enough stock for ":item" (:sisa left)', ['item' => $item->deskripsi, 'sisa' => PengambilanService::tersedia($item)]));
                }
                $p->items()->create([
                    'inventory_id' => $item->id,
                    'part_no'      => $item->part_no,
                    'deskripsi'    => $item->deskripsi,
                    'lokasi'       => $item->lokasi_rak,
                    'mesin'        => $b['mesin'],
                    'no_pm'        => $b['no_pm'] ?? null,
                    'jumlah'       => $jumlah,
                    'harga_satuan' => (float) $item->harga_beli_terakhir,
                ]);
                $total += $jumlah * (float) $item->harga_beli_terakhir;
            }
            $p->update(['total_nilai' => $total]);

            return $p;
        });
    }

    /** Setujui: semua barang dicatat atas nama teknisi (semua atau tidak sama sekali). */
    public function setujui(PermintaanAmbil $p): void
    {
        DB::transaction(function () use ($p) {
            $p = PermintaanAmbil::whereKey($p->id)->lockForUpdate()->firstOrFail();
            if ($p->status !== 'menunggu') {
                throw new \RuntimeException(__('Request :kode was already decided.', ['kode' => $p->kode]));
            }
            foreach ($p->items as $it) {
                $this->pengambilan->catat($it->part_no, $it->jumlah, $it->mesin, $p->karyawan, $it->no_pm, $p->kode, $p->id);
            }
            $p->update(['status' => 'disetujui', 'diputus_oleh' => auth()->user()->name, 'diputus_at' => now()]);
        });
    }

    public function tolak(PermintaanAmbil $p, ?string $alasan): void
    {
        if ($p->status !== 'menunggu') {
            throw new \RuntimeException(__('Request :kode was already decided.', ['kode' => $p->kode]));
        }
        $p->update(['status' => 'ditolak', 'alasan_tolak' => $alasan ?: null, 'diputus_oleh' => auth()->user()->name, 'diputus_at' => now()]);
    }
}
