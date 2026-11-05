<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Status cetak label barcode, supaya part / laci baru (dari impor ERP, Penerimaan Barang,
 * atau Parts Master) dan part yang pindah laci terdeteksi "perlu label".
 *  - inventories.label_dicetak_at : kapan label part terakhir ditandai sudah dicetak & ditempel
 *  - inventories.label_lokasi     : kode bin yang tertulis di label itu (beda dgn lokasi sekarang = part pindah)
 *  - label_laci                   : laci yang labelnya sudah dicetak & ditempel
 * Semua masih kosong: sebelum label pertama ditempel, memang semua part & laci perlu label.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventories', function (Blueprint $table) {
            $table->timestamp('label_dicetak_at')->nullable()->after('lokasi_rak');
            $table->string('label_lokasi', 50)->nullable()->after('label_dicetak_at');
        });

        Schema::create('label_laci', function (Blueprint $table) {
            $table->string('kode', 50)->primary();
            $table->timestamp('dicetak_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('label_laci');
        Schema::table('inventories', function (Blueprint $table) {
            $table->dropColumn(['label_dicetak_at', 'label_lokasi']);
        });
    }
};
