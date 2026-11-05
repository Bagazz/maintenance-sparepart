<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel import_batches SUDAH ADA (dibuat sesi lain) dengan kolom:
     * id, file_type, file_name, file_hash, uploaded_by, status, error_message,
     * created_at, updated_at.
     *
     * Migration ini HANYA menambah kolom counter yang belum ada, dengan
     * pengecekan hasColumn supaya aman dijalankan di kondisi skema manapun
     * (tidak akan error "column already exists").
     */
    public function up(): void
    {
        Schema::table('import_batches', function (Blueprint $table) {
            if (!Schema::hasColumn('import_batches', 'total_rows')) {
                $table->unsignedInteger('total_rows')->default(0)->after('status');
            }
            if (!Schema::hasColumn('import_batches', 'inserted_rows')) {
                $table->unsignedInteger('inserted_rows')->default(0)->after('total_rows');
            }
            if (!Schema::hasColumn('import_batches', 'skipped_rows')) {
                $table->unsignedInteger('skipped_rows')->default(0)->after('inserted_rows');
            }
            if (!Schema::hasColumn('import_batches', 'failed_rows')) {
                $table->unsignedInteger('failed_rows')->default(0)->after('skipped_rows');
            }
        });
    }

    public function down(): void
    {
        Schema::table('import_batches', function (Blueprint $table) {
            foreach (['total_rows', 'inserted_rows', 'skipped_rows', 'failed_rows'] as $col) {
                if (Schema::hasColumn('import_batches', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
