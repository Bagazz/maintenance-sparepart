<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Melengkapi kolom history_logs yang dipakai oleh model HistoryLog,
     * DashboardController, dan HistoryController, tetapi tidak dibuat oleh
     * migration yang ada saat ini.
     *
     * Setiap kolom dicek dulu dengan hasColumn, jadi aman dijalankan
     * di database yang kolomnya sebagian sudah ada.
     */
    public function up(): void
    {
        Schema::table('history_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('history_logs', 'item_name')) {
                $table->string('item_name')->nullable();
            }

            if (!Schema::hasColumn('history_logs', 'machine')) {
                $table->string('machine')->nullable()->index();
            }

            if (!Schema::hasColumn('history_logs', 'operator')) {
                $table->string('operator')->nullable()->index();
            }

            if (!Schema::hasColumn('history_logs', 'qty_returned')) {
                $table->unsignedInteger('qty_returned')->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('history_logs', function (Blueprint $table) {
            foreach (['item_name', 'machine', 'operator', 'qty_returned'] as $kolom) {
                if (Schema::hasColumn('history_logs', $kolom)) {
                    $table->dropColumn($kolom);
                }
            }
        });
    }
};