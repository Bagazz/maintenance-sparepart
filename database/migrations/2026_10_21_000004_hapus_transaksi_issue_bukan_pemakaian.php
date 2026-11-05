<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Import sheet Issue versi lama mencatat SEMUA baris non STK-MTL sebagai ADJ.
 * Dua jenis di antaranya bukan penyesuaian stok:
 * - STK-STK : pindah bin di gudang (stok total tidak berubah)
 * - PUR-STK : penerimaan pembelian, sudah tercatat dari sheet Receipt (jadi dobel)
 * Baris tersebut dihapus; import yang baru sudah melewatinya.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('inventory_transactions')
            ->where('source_type', 'issue')
            ->where('type', 'ADJ')
            ->where(function ($q) {
                $q->where('source_ref', 'like', '%|STK-STK|%')
                  ->orWhere('source_ref', 'like', '%|PUR-STK|%');
            })
            ->delete();
    }

    public function down(): void
    {
        // Tidak bisa dikembalikan; import ulang file Excel bila perlu.
    }
};
