<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Part yang tersimpan di lebih dari satu bin (mis. M03-F2D2 dan M03-WASTE).
 * lokasi_rak = bin utama (stok terbanyak, bukan WASTE); lokasi_lain = bin lain beserta qty-nya
 * sesuai file ERP terakhir, mis. [{"bin":"M03-WASTE","qty":5}].
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventories', function (Blueprint $table) {
            $table->json('lokasi_lain')->nullable()->after('lokasi_rak');
        });
    }

    public function down(): void
    {
        Schema::table('inventories', function (Blueprint $table) {
            $table->dropColumn('lokasi_lain');
        });
    }
};
