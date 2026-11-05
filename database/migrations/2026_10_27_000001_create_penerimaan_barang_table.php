<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penerimaan Barang (goods receipt) langsung di dashboard: stok bertambah saat barang datang,
 * tanpa menunggu impor Excel ERP berikutnya. Baris barangnya disimpan sebagai transaksi IN
 * (source_type 'app_receipt') yang menunjuk ke dokumen penerimaan ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('penerimaan_barang', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 30)->unique();               // PB-2610-001
            $table->date('tanggal');
            $table->string('pemasok', 150)->nullable();
            $table->string('no_dokumen', 100)->nullable();       // No. PO / surat jalan
            $table->string('catatan', 500)->nullable();
            $table->enum('status', ['aktif', 'batal'])->default('aktif')->index();
            $table->string('alasan_batal', 300)->nullable();
            $table->timestamp('dibatalkan_at')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->foreignId('penerimaan_id')->nullable()->after('history_log_id')->constrained('penerimaan_barang')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('penerimaan_id');
        });
        Schema::dropIfExists('penerimaan_barang');
    }
};
