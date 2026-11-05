<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\InventoryTransaction;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Stock opname per rak.
 *
 * Alur: mulai sesi untuk satu rak -> hitung fisik lewat HP (per part) -> tinjau selisih -> posting.
 * Stok sistem dicatat SAAT qty fisik disimpan, dan posting menambahkan selisih (fisik − sistem saat hitung)
 * ke stok sekarang. Jadi pengambilan kiosk di antara waktu hitung dan posting tidak membuat selisih palsu.
 */
class StockOpnameService
{
    public const GUDANG = 'M03';
    public const POLA_RAK = '/^[A-Z]\d{2}-[A-Z]+\d*$/';

    /** Rak dianggap perlu dihitung lagi bila opname terakhir lebih lama dari ini. */
    public const HARI_SIKLUS = 90;

    /** Semua rak gudang M03 + jumlah part, nilai stok, opname terakhir & sesi yang masih berjalan. */
    public function daftarRak(): Collection
    {
        $part = Inventory::gudangAktif()
            ->get(['lokasi_rak', 'stock', 'harga_beli_terakhir']);

        $terakhir = StockOpname::where('status', 'diposting')
            ->selectRaw('kode_rak, MAX(diposting_at) as tgl')->groupBy('kode_rak')->pluck('tgl', 'kode_rak');
        $berjalan = StockOpname::where('status', 'berjalan')->pluck('id', 'kode_rak');

        return $part->groupBy(fn ($p) => Inventory::uraiLokasi($p->lokasi_rak)['kode_rak'] ?? $p->lokasi_rak)
            ->map(fn ($grup, $kode) => (object) [
                'kode'      => $kode,
                'rak'       => Inventory::uraiLokasi($kode . 'D1')['rak'] ?? $kode,
                'jumlah'    => $grup->count(),
                'nilai'     => $grup->sum(fn ($p) => max(0, $p->stock) * $p->harga_beli_terakhir),
                'terakhir'  => isset($terakhir[$kode]) ? Carbon::parse($terakhir[$kode]) : null,
                'berjalan'  => $berjalan[$kode] ?? null,
            ])
            ->sortBy('kode', SORT_NATURAL)
            ->values();
    }

    /** Buka sesi baru untuk satu rak, atau kembalikan sesi yang masih berjalan di rak itu. */
    public function mulai(string $kodeRak, string $petugas, bool $hitungButa, ?User $user): StockOpname
    {
        return DB::transaction(function () use ($kodeRak, $petugas, $hitungButa, $user) {
            if ($aktif = StockOpname::where('kode_rak', $kodeRak)->where('status', 'berjalan')->lockForUpdate()->first()) {
                return $aktif;
            }

            $opname = StockOpname::create([
                'kode'        => StockOpname::kodeBaru(),
                'kode_rak'    => $kodeRak,
                'petugas'     => mb_strtoupper(trim($petugas)),
                'hitung_buta' => $hitungButa,
                'user_id'     => $user?->id,
            ]);

            $sekarang = now();
            $baris = Inventory::diRak($kodeRak)->get(['id', 'harga_beli_terakhir'])
                ->map(fn ($p) => [
                    'stock_opname_id' => $opname->id,
                    'inventory_id'    => $p->id,
                    'harga_satuan'    => $p->harga_beli_terakhir ?? 0,
                    'created_at'      => $sekarang,
                    'updated_at'      => $sekarang,
                ]);
            foreach ($baris->chunk(500) as $potong) {
                StockOpnameItem::insert($potong->all());
            }

            return $opname;
        });
    }

    /** Simpan hasil hitung satu part. Stok sistem diambil saat ini juga. */
    public function simpanHitung(StockOpnameItem $item, int $qtyFisik, ?string $catatan = null): StockOpnameItem
    {
        $item->update([
            'stok_sistem' => (int) Inventory::whereKey($item->inventory_id)->value('stock'),
            'qty_fisik'   => $qtyFisik,
            'catatan'     => $catatan !== null && trim($catatan) !== '' ? trim($catatan) : $item->catatan,
            'dihitung_at' => now(),
        ]);

        return $item;
    }

    /** Part yang ditemukan di rak tapi tidak ada di daftar sistem rak ini. */
    public function tambahItem(StockOpname $opname, Inventory $part): StockOpnameItem
    {
        return StockOpnameItem::firstOrCreate(
            ['stock_opname_id' => $opname->id, 'inventory_id' => $part->id],
            ['tambahan' => true, 'harga_satuan' => $part->harga_beli_terakhir ?? 0],
        );
    }

    public function ringkasan(StockOpname $opname): array
    {
        $items = $opname->items()->get(['qty_fisik', 'stok_sistem', 'harga_satuan']);
        $dihitung = $items->whereNotNull('qty_fisik');
        $selisih = $dihitung->map(fn ($i) => ($i->qty_fisik - $i->stok_sistem) * $i->harga_satuan);

        return [
            'total'      => $items->count(),
            'dihitung'   => $dihitung->count(),
            'belum'      => $items->count() - $dihitung->count(),
            'cocok'      => $dihitung->filter(fn ($i) => $i->qty_fisik === $i->stok_sistem)->count(),
            'kurang_n'   => $dihitung->filter(fn ($i) => $i->qty_fisik < $i->stok_sistem)->count(),
            'lebih_n'    => $dihitung->filter(fn ($i) => $i->qty_fisik > $i->stok_sistem)->count(),
            'kurang_rp'  => (float) -$selisih->filter(fn ($v) => $v < 0)->sum(),
            'lebih_rp'   => (float) $selisih->filter(fn ($v) => $v > 0)->sum(),
            'bersih_rp'  => (float) $selisih->sum(),
            'persen'     => $items->count() ? (int) floor($dihitung->count() / $items->count() * 100) : 0,
        ];
    }

    /**
     * Terapkan selisih ke stok aplikasi sebagai transaksi ADJ (source_type 'opname').
     * Part yang belum dihitung dilewati (stoknya tidak diubah).
     * ADJ tidak dihitung sebagai pembelian maupun pemakaian di Analitik Biaya.
     */
    public function posting(StockOpname $opname): array
    {
        return DB::transaction(function () use ($opname) {
            $opname = StockOpname::whereKey($opname->id)->lockForUpdate()->firstOrFail();
            if ($opname->status !== 'berjalan') {
                throw new \RuntimeException(__('This count session has already been closed.'));
            }

            $disesuaikan = 0;
            $items = $opname->items()->whereNotNull('qty_fisik')->whereColumn('qty_fisik', '!=', 'stok_sistem')->get();

            foreach ($items as $item) {
                $part = Inventory::whereKey($item->inventory_id)->lockForUpdate()->first();
                if (!$part) {
                    continue;
                }
                $selisih = $item->qty_fisik - $item->stok_sistem;
                // Stok tidak boleh negatif walau ada pengambilan setelah dihitung.
                $baru = max(0, (int) $part->stock + $selisih);
                $qtyNyata = $baru - (int) $part->stock;
                $part->update(['stock' => $baru]);

                InventoryTransaction::create([
                    'inventory_id'      => $part->id,
                    'type'              => 'ADJ',
                    'tanggal_transaksi' => now()->toDateString(),
                    'qty'               => $qtyNyata,
                    'harga_satuan'      => $item->harga_satuan,
                    'total_harga'       => $qtyNyata * $item->harga_satuan,
                    'keterangan'        => "Stock opname {$opname->kode} rak {$opname->kode_rak}: sistem {$item->stok_sistem}, fisik {$item->qty_fisik}"
                        . ($item->catatan ? " ({$item->catatan})" : ''),
                    'source_type'       => 'opname',
                    'source_ref'        => InventoryTransaction::makeSourceRef($opname->kode, (string) $part->id),
                ]);
                $disesuaikan++;
            }

            $opname->update(['status' => 'diposting', 'diposting_at' => now()]);

            return ['disesuaikan' => $disesuaikan];
        });
    }

    /** Selisih opname yang sudah diposting dalam periode (nilai dari transaksi ADJ opname). */
    public function selisihPeriode(Carbon $awal, Carbon $akhir): array
    {
        $q = DB::table('inventory_transactions')->where('source_type', 'opname')
            ->whereBetween('tanggal_transaksi', [$awal->toDateString(), $akhir->toDateString()]);

        return [
            'kurang_rp' => (float) -(clone $q)->where('total_harga', '<', 0)->sum('total_harga'),
            'lebih_rp'  => (float) (clone $q)->where('total_harga', '>', 0)->sum('total_harga'),
            'sesi'      => StockOpname::where('status', 'diposting')
                ->whereBetween('diposting_at', [$awal->copy()->startOfDay(), $akhir->copy()->endOfDay()])->count(),
        ];
    }
}
