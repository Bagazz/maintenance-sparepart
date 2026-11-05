<?php

namespace App\Services;

use App\Models\HistoryLog;
use App\Models\Inventory;
use App\Models\InventoryTransaction;
use App\Models\Karyawan;
use App\Models\PermintaanAmbilItem;
use Illuminate\Support\Facades\DB;

/**
 * Pencatatan pengambilan spare part: stok berkurang, riwayat (HistoryLog) dan transaksi OUT dibuat.
 * Dipakai oleh kiosk (langsung) dan oleh Persetujuan (setelah disetujui Kepala Spare Part).
 */
class PengambilanService
{
    /** Jumlah yang sedang "dipesan" permintaan menunggu persetujuan (tidak boleh diambil orang lain). */
    public static function dipesan(int $inventoryId, ?int $kecualiPermintaan = null): int
    {
        return (int) PermintaanAmbilItem::where('inventory_id', $inventoryId)
            ->whereHas('permintaan', fn ($q) => $q->where('status', 'menunggu')
                ->when($kecualiPermintaan, fn ($q) => $q->whereKeyNot($kecualiPermintaan)))
            ->sum('jumlah');
    }

    /** Stok yang benar-benar bisa diambil = stok - yang dipesan permintaan lain. */
    public static function tersedia(Inventory $item, ?int $kecualiPermintaan = null): int
    {
        return max(0, (int) $item->stock - self::dipesan($item->id, $kecualiPermintaan));
    }

    /**
     * @param int|null $permintaanId permintaan yang sedang disetujui (pesanannya sendiri tidak dihitung)
     * @throws \RuntimeException stok tidak cukup / part tidak ada
     */
    public function catat(string $partNo, int $jumlah, string $mesin, ?Karyawan $karyawan, ?string $noPm = null, ?string $kode = null, ?int $permintaanId = null): Inventory
    {
        $namaPeminjam = $karyawan?->nama ?? auth()->user()->name;
        $noPm = $noPm !== null && trim($noPm) !== '' ? strtoupper(trim($noPm)) : null;

        return DB::transaction(function () use ($partNo, $jumlah, $mesin, $karyawan, $namaPeminjam, $noPm, $kode, $permintaanId) {
            $item = Inventory::where('part_no', $partNo)->lockForUpdate()->first();

            if (!$item) {
                throw new \RuntimeException(__('Part :part not found', ['part' => $partNo]));
            }

            $tersedia = self::tersedia($item, $permintaanId);
            if ($jumlah > $tersedia) {
                throw new \RuntimeException(__('Not enough stock for ":item" (:sisa left)', ['item' => $item->deskripsi, 'sisa' => $tersedia]));
            }

            $item->decrement('stock', $jumlah);

            $log = HistoryLog::create([
                'part_no'          => $item->part_no,
                'item_name'        => $item->deskripsi,
                'machine'          => $mesin,
                'operator'         => auth()->user()->name,
                'peminjam'         => $namaPeminjam,
                'karyawan_id'      => $karyawan?->id,
                'no_pm'            => $noPm,
                'kode_pengambilan' => $kode,
                // Semua pengambilan = dipakai. Yang tidak jadi dipakai cukup diretur lewat menu Pengembalian.
                'aksi'             => 'pakai',
                'jumlah'           => $jumlah,
                'qty_returned'     => 0,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);

            InventoryTransaction::create([
                'inventory_id'      => $item->id,
                'type'              => 'OUT',
                'qty'               => $jumlah,
                'mesin'             => $mesin,
                'harga_satuan'      => $item->harga_beli_terakhir,
                'total_harga'       => $item->harga_beli_terakhir * $jumlah,
                'keterangan'        => "Diambil via aplikasi oleh {$namaPeminjam} untuk mesin {$mesin}" . ($noPm ? " (Job {$noPm})" : '')
                    . ($kode ? " — disetujui {$kode}" : ''),
                'tanggal_transaksi' => today(),
                'source_type'       => 'app',
                'source_ref'        => 'history_log:' . $log->id,
                'history_log_id'    => $log->id,
            ]);

            return $item;
        });
    }
}
