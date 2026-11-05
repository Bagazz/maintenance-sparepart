<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Konsep "alat dipinjam / wajib kembali / terlambat" dihapus atas permintaan user:
 * spare part pada umumnya DIPAKAI; sebagian kecil yang tidak jadi dipakai cukup
 * dikembalikan kapan saja lewat menu Pengembalian (retur), tanpa batas waktu.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('history_logs')->where('aksi', 'pinjam')->update(['aksi' => 'pakai']);

        if (Schema::hasColumn('inventories', 'tipe')) {
            Schema::table('inventories', function (Blueprint $table) {
                $table->dropIndex(['tipe']);
                $table->dropColumn('tipe');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('inventories', 'tipe')) {
            Schema::table('inventories', function (Blueprint $table) {
                $table->string('tipe', 20)->default('habis_pakai')->after('kategori')->index();
            });
        }
    }
};
