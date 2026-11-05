<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stock opname per rak: satu sesi = satu rak gudang (mis. M03-B5), diisi lewat HP.
 * Selisih baru mengubah stok setelah sesi "diposting" (jadi transaksi ADJ, source_type 'opname').
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_opnames', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 30)->unique();              // SO-2610-001
            $table->string('kode_rak', 30)->index();           // M03-B5
            $table->enum('status', ['berjalan', 'diposting', 'batal'])->default('berjalan')->index();
            $table->boolean('hitung_buta')->default(true);     // stok sistem disembunyikan sampai qty fisik diisi
            $table->string('petugas', 100);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('catatan', 500)->nullable();
            $table->timestamp('diposting_at')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_opname_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_opname_id')->constrained('stock_opnames')->cascadeOnDelete();
            $table->foreignId('inventory_id')->constrained('inventories')->cascadeOnDelete();
            $table->boolean('tambahan')->default(false);       // ditemukan di rak tapi tidak ada di daftar sistem
            $table->integer('stok_sistem')->nullable();        // stok aplikasi SAAT qty fisik disimpan
            $table->integer('qty_fisik')->nullable();          // null = belum dihitung
            $table->decimal('harga_satuan', 15, 2)->default(0);
            $table->string('catatan', 200)->nullable();
            $table->timestamp('dihitung_at')->nullable();
            $table->timestamps();

            $table->unique(['stock_opname_id', 'inventory_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_opname_items');
        Schema::dropIfExists('stock_opnames');
    }
};
