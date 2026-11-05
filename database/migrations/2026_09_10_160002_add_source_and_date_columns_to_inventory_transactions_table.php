<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('inventory_transactions', 'tanggal_transaksi')) {
                $table->date('tanggal_transaksi')->nullable()->after('type');
            }
            if (!Schema::hasColumn('inventory_transactions', 'source_type')) {
                $table->string('source_type')->nullable()->after('keterangan');
            }
            if (!Schema::hasColumn('inventory_transactions', 'source_ref')) {
                $table->string('source_ref')->nullable()->after('source_type');
            }
            if (!Schema::hasColumn('inventory_transactions', 'import_batch_id')) {
                $table->foreignId('import_batch_id')->nullable()->after('source_ref')
                    ->constrained('import_batches')->nullOnDelete();
            }
        });

        // Index dibuat terpisah supaya tidak gagal kalau kolomnya baru saja dibuat
        // di block di atas dalam transaksi migration yang sama (aman di MySQL/Postgres).
        Schema::table('inventory_transactions', function (Blueprint $table) {
            $indexes = collect(\DB::select("SHOW INDEX FROM inventory_transactions"))
                ->pluck('Key_name')->unique();

            if (!$indexes->contains('inv_trans_source_unique')) {
                $table->unique(['source_type', 'source_ref'], 'inv_trans_source_unique');
            }
            if (!$indexes->contains('inventory_transactions_tanggal_transaksi_index')) {
                $table->index('tanggal_transaksi');
            }
        });
    }

    public function down(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('inventory_transactions', 'import_batch_id')) {
                $table->dropConstrainedForeignId('import_batch_id');
            }
            foreach (['tanggal_transaksi', 'source_type', 'source_ref'] as $col) {
                if (Schema::hasColumn('inventory_transactions', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
