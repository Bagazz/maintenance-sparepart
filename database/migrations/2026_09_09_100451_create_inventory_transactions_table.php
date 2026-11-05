<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInventoryTransactionsTable extends Migration
{
    public function up()
    {
        Schema::create('inventory_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_id')->constrained('inventories')->onDelete('cascade');
            
            // Jenis transaksi: IN (Beli), OUT (Pakai), ADJ (Koreksi/Hilang/Saldo Awal)
            $table->enum('type', ['IN', 'OUT', 'ADJ']); 
            
            $table->integer('qty');
            
            // Menyimpan harga part saat transaksi terjadi
            $table->decimal('harga_satuan', 15, 2)->default(0); 
            $table->decimal('total_harga', 15, 2)->default(0);
            
            // Referensi (Misal: "Dipinjam oleh MAHEN", "Beli Baru Bulan Juli", dsb)
            $table->string('keterangan')->nullable();
            
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('inventory_transactions');
    }
}