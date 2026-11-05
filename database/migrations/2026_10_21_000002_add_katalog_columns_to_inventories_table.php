<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom pendukung fitur "Cari Barang" untuk shift 2 & 3:
 * - kategori    : untuk telusur katalog foto (Bearing, Relay, Baut, ...)
 * - kata_kunci  : nama panggilan / istilah lapangan, mis. "relay kecil omron"
 * - foto_barang : foto fisik barangnya (foto_lokasi tetap untuk foto rak/laci)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventories', function (Blueprint $table) {
            if (!Schema::hasColumn('inventories', 'kategori')) {
                $table->string('kategori', 100)->nullable()->after('deskripsi')->index();
            }
            if (!Schema::hasColumn('inventories', 'kata_kunci')) {
                $table->string('kata_kunci', 500)->nullable()->after('kategori');
            }
            if (!Schema::hasColumn('inventories', 'foto_barang')) {
                $table->string('foto_barang')->nullable()->after('lokasi_rak');
            }
        });
    }

    public function down(): void
    {
        Schema::table('inventories', function (Blueprint $table) {
            if (Schema::hasColumn('inventories', 'kategori')) {
                $table->dropIndex(['kategori']);
            }
            foreach (['kategori', 'kata_kunci', 'foto_barang'] as $kolom) {
                if (Schema::hasColumn('inventories', $kolom)) {
                    $table->dropColumn($kolom);
                }
            }
        });
    }
};
