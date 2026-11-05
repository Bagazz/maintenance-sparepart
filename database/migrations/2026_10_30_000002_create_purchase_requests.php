<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pelacakan Purchase Request: Diajukan -> PO terbit -> Diterima sebagian -> Selesai (atau Dibatalkan).
 * Penerimaan Barang otomatis mengisi qty diterima, dan qty yang masih dipesan
 * mengurangi saran beli di Rekomendasi Pembelian supaya tidak dipesan dua kali.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_requests', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 20)->unique();
            $table->enum('status', ['diajukan', 'po', 'sebagian', 'selesai', 'dibatalkan'])->default('diajukan')->index();
            $table->string('no_po', 100)->nullable();
            $table->string('pemasok', 150)->nullable();
            $table->date('perkiraan_datang')->nullable();
            $table->string('catatan', 500)->nullable();
            $table->string('dibuat_oleh')->nullable();
            $table->timestamp('po_at')->nullable();
            $table->timestamp('selesai_at')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_request_id')->constrained('purchase_requests')->cascadeOnDelete();
            $table->foreignId('inventory_id')->constrained('inventories');
            $table->string('part_no', 100);
            $table->string('deskripsi');
            $table->unsignedInteger('qty_minta');
            $table->unsignedInteger('qty_diterima')->default(0);
            $table->decimal('harga_perkiraan', 15, 2)->default(0);
            $table->timestamps();
            $table->index(['inventory_id', 'purchase_request_id']);
        });

        Schema::table('penerimaan_barang', function (Blueprint $table) {
            $table->foreignId('purchase_request_id')->nullable()->after('no_dokumen')->constrained('purchase_requests')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('penerimaan_barang', function (Blueprint $table) {
            $table->dropConstrainedForeignId('purchase_request_id');
        });
        Schema::dropIfExists('purchase_request_items');
        Schema::dropIfExists('purchase_requests');
    }
};
