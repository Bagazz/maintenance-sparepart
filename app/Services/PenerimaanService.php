<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\InventoryTransaction;
use App\Models\PenerimaanBarang;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Penerimaan barang di dashboard: stok bertambah saat barang datang.
 *
 * Hubungan dengan ERP: penerimaan tetap harus diposting di ERP (Receipt). Agar tidak dobel di Analitik,
 * pembelian dari menu ini hanya dihitung untuk tanggal SETELAH data Receipt ERP terakhir
 * (lihat AnalitikBiayaService::queryPembelian). Impor master ERP berikutnya menyamakan stok dengan ERP.
 */
class PenerimaanService
{
    public const SUMBER = 'app_receipt';

    /**
     * @param array $kepala ['tanggal', 'pemasok', 'no_dokumen', 'catatan']
     * @param array $baris  [['part_no', 'qty', 'harga', 'baru' => bool, 'deskripsi', 'lokasi_rak'], ...]
     */
    public function simpan(array $kepala, array $baris, ?User $user): PenerimaanBarang
    {
        return DB::transaction(function () use ($kepala, $baris, $user) {
            $dok = PenerimaanBarang::create([
                'kode'       => PenerimaanBarang::kodeBaru(),
                'tanggal'    => $kepala['tanggal'],
                'pemasok'    => $this->rapi($kepala['pemasok'] ?? null),
                'no_dokumen' => $this->rapi($kepala['no_dokumen'] ?? null),
                'purchase_request_id' => $kepala['purchase_request_id'] ?? null,
                'catatan'    => $this->rapi($kepala['catatan'] ?? null),
                'user_id'    => $user?->id,
            ]);

            foreach (array_values($baris) as $n => $b) {
                $partNo = trim($b['part_no']);
                $qty = (int) $b['qty'];
                $harga = round((float) ($b['harga'] ?? 0), 2);

                $part = Inventory::where('part_no', $partNo)->lockForUpdate()->first();
                if (!$part) {
                    if (empty($b['baru'])) {
                        throw new \RuntimeException(__('Part :part not found', ['part' => $partNo]));
                    }
                    // Barang yang belum pernah terdaftar: dibuat dari data penerimaan.
                    $part = Inventory::create([
                        'part_no'             => $partNo,
                        'deskripsi'           => mb_strtoupper(trim($b['deskripsi'])),
                        'lokasi_rak'          => strtoupper(trim((string) ($b['lokasi_rak'] ?? ''))) ?: '-',
                        'stock'               => 0,
                        'harga_beli_terakhir' => $harga,
                    ]);
                }

                $part->increment('stock', $qty);
                if ($harga > 0) {
                    $part->update(['harga_beli_terakhir' => $harga]);
                }

                InventoryTransaction::create([
                    'inventory_id'      => $part->id,
                    'type'              => 'IN',
                    'tanggal_transaksi' => $dok->tanggal->toDateString(),
                    'qty'               => $qty,
                    'harga_satuan'      => $harga,
                    'total_harga'       => $qty * $harga,
                    // Format sama dengan impor ERP supaya pemasok terbaca di Analitik & Purchase Request.
                    'keterangan'        => 'Purchasing dari: ' . ($dok->pemasok ?: '-'),
                    'source_type'       => self::SUMBER,
                    'source_ref'        => InventoryTransaction::makeSourceRef($dok->kode, (string) ($n + 1)),
                    'penerimaan_id'     => $dok->id,
                ]);
            }

            // Purchase Request terbuka ikut terisi (qty diterima & status) — tanpa input ulang.
            app(PurchaseRequestService::class)->terimaDariPenerimaan($dok);

            return $dok;
        });
    }

    /**
     * Batalkan penerimaan yang salah input: stok dikurangi kembali dan baris transaksinya dihapus.
     * Ditolak bila sebagian barang sudah terlanjur diambil (stok sekarang < qty yang diterima).
     */
    public function batal(PenerimaanBarang $dok, string $alasan): void
    {
        DB::transaction(function () use ($dok, $alasan) {
            $dok = PenerimaanBarang::whereKey($dok->id)->lockForUpdate()->firstOrFail();
            if ($dok->status !== 'aktif') {
                throw new \RuntimeException(__('This receipt has already been cancelled.'));
            }

            $baris = $dok->baris()->get();
            $kurang = [];
            foreach ($baris as $b) {
                $part = Inventory::whereKey($b->inventory_id)->lockForUpdate()->first();
                if ($part && $part->stock < $b->qty) {
                    $kurang[] = "{$part->deskripsi} ({$part->stock}/{$b->qty})";
                }
            }
            if ($kurang) {
                throw new \RuntimeException(__('Cannot cancel: some items have already been taken — :daftar. Correct it with a stock count instead.', ['daftar' => implode(', ', $kurang)]));
            }

            foreach ($baris as $b) {
                Inventory::whereKey($b->inventory_id)->decrement('stock', $b->qty);
            }
            $dok->baris()->delete();
            $dok->update(['status' => 'batal', 'alasan_batal' => $alasan, 'dibatalkan_at' => now()]);
        });
    }

    /** Daftar pemasok yang pernah dipakai (dari Receipt ERP & penerimaan dashboard) untuk saran isian. */
    public function daftarPemasok(): Collection
    {
        $erp = DB::table('inventory_transactions')->where('type', 'IN')->where('keterangan', 'like', 'Purchasing dari:%')
            ->distinct()->pluck('keterangan')->map(fn ($k) => trim(substr($k, strlen('Purchasing dari:'))));

        return $erp->merge(PenerimaanBarang::whereNotNull('pemasok')->distinct()->pluck('pemasok'))
            ->filter(fn ($p) => $p !== '' && $p !== '-' && stripos($p, 'tidak tercatat') === false)
            ->unique()->sort()->values();
    }

    private function rapi(?string $v): ?string
    {
        $v = trim((string) $v);

        return $v === '' ? null : $v;
    }
}
