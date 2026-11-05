<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('inventory_stock_snapshots')) {
            return;
        }

        Schema::create('inventory_stock_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_id')->constrained('inventories')->cascadeOnDelete();
            $table->foreignId('import_batch_id')->nullable()->constrained('import_batches')->nullOnDelete();

            $table->date('tanggal_snapshot');
            $table->integer('stock_fisik');
            $table->decimal('harga_satuan_saat_itu', 15, 2)->default(0);
            $table->decimal('nilai_total_saat_itu', 15, 2)->default(0);

            $table->timestamps();

            $table->unique(['inventory_id', 'import_batch_id'], 'stock_snapshot_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_stock_snapshots');
    }
};
