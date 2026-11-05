<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Bersihkan master barang dari:
 *  - 5 baris RINGKASAN di bawah sheet master ERP yang dulu ikut ter-import sebagai "barang"
 *    ("Data per 31 augudt 2026", "Qty :", "Jumlah On hand :", "Total Cost :", dan total qty "3158");
 *  - 8 barang DUMMY dari aplikasi lama (part 12345678 dst., lokasi "Rak A-04..10" / "-").
 * Dicocokkan persis (part no + deskripsi + lokasi) dan hanya dihapus bila tidak punya transaksi.
 * Snapshot stoknya ikut terhapus (cascade). Backup dibuat sebelum migrasi ini dijalankan.
 */
return new class extends Migration
{
    private const SAMPAH = [
        // [part_no, deskripsi, lokasi_rak]
        ['Data per 31 augudt 2026', '-', '-'],
        ['Qty :', '3158', '-'],
        ['Jumlah On hand :', '33399.7', '-'],
        ['Total Cost :', '12578692842.516', '-'],
        ['3158', '-', '-'],
        ['87654321', 'Seven Segment', 'Rak A-04'],
        ['12345678', 'Switch', 'Rak A-05'],
        ['13245768', 'Mur Baut', 'Rak A-06'],
        ['12348765', 'Baut', 'Rak A-07'],
        ['12312312', 'Ring', 'Rak A-08'],
        ['45645645', 'Cakram', 'Rak A-09'],
        ['78978978', 'Bearing', 'Rak A-10'],
        ['8908908', 'Plat', '-'],
    ];

    public function up(): void
    {
        foreach (self::SAMPAH as [$partNo, $deskripsi, $lokasi]) {
            $part = DB::table('inventories')->where('part_no', $partNo)->where('deskripsi', $deskripsi)->where('lokasi_rak', $lokasi)->first();
            if (!$part) {
                continue;
            }
            $dipakai = DB::table('inventory_transactions')->where('inventory_id', $part->id)->exists()
                || DB::table('history_logs')->where('part_no', $partNo)->exists();
            if ($dipakai) {
                continue;   // ternyata punya riwayat -> jangan dihapus
            }
            DB::table('inventory_stock_snapshots')->where('inventory_id', $part->id)->delete();
            DB::table('stock_opname_items')->where('inventory_id', $part->id)->delete();
            DB::table('inventories')->where('id', $part->id)->delete();
        }
    }

    public function down(): void
    {
        // Tidak dikembalikan otomatis; pulihkan dari backup bila benar-benar diperlukan.
    }
};
