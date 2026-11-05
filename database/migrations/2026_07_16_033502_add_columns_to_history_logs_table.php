<?php

// Simpan file ini di: database/migrations/2026_07_20_000002_add_peminjam_to_history_logs_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('history_logs', function (Blueprint $table) {
            $table->string('peminjam')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('history_logs', function (Blueprint $table) {
            $table->dropColumn('peminjam');
        });
    }
};