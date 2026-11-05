<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persetujuan pengambilan oleh Kepala Spare Part (Pak Nardi):
 *  - pengaturan             : setelan aplikasi sederhana (kunci -> nilai), mis. batas nilai yang perlu disetujui
 *  - permintaan_ambil       : satu permintaan teknisi yang menunggu persetujuan (PA-YYMM-NNN)
 *  - permintaan_ambil_items : barang di dalamnya. Selama menunggu, jumlahnya "dipesan" (tidak bisa diambil orang lain).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengaturan', function (Blueprint $table) {
            $table->string('kunci', 60)->primary();
            $table->text('nilai')->nullable();
            $table->timestamps();
        });

        Schema::create('permintaan_ambil', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 20)->unique();
            $table->foreignId('karyawan_id')->nullable()->constrained('karyawans')->nullOnDelete();
            $table->string('peminjam');
            $table->enum('status', ['menunggu', 'disetujui', 'ditolak', 'dibatalkan'])->default('menunggu')->index();
            $table->decimal('total_nilai', 15, 2)->default(0);
            $table->string('alasan_tolak')->nullable();
            $table->string('diputus_oleh')->nullable();
            $table->timestamp('diputus_at')->nullable();
            $table->timestamps();
        });

        Schema::create('permintaan_ambil_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permintaan_id')->constrained('permintaan_ambil')->cascadeOnDelete();
            $table->foreignId('inventory_id')->constrained('inventories');
            $table->string('part_no', 100);
            $table->string('deskripsi');
            $table->string('lokasi', 50)->nullable();
            $table->string('mesin');
            $table->string('no_pm', 50)->nullable();
            $table->unsignedInteger('jumlah');
            $table->decimal('harga_satuan', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permintaan_ambil_items');
        Schema::dropIfExists('permintaan_ambil');
        Schema::dropIfExists('pengaturan');
    }
};
