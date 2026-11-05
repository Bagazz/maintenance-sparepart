<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('history_logs', 'peminjam')) {
            Schema::table('history_logs', function (Blueprint $table) {
                $table->string('peminjam')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('history_logs', function (Blueprint $table) {
            $table->dropColumn('peminjam');
        });
    }
};