<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('history_logs', 'source_ref')) {
            return;
        }

        Schema::table('history_logs', function (Blueprint $table) {
            $table->string('source_ref')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('history_logs', function (Blueprint $table) {
            if (Schema::hasColumn('history_logs', 'source_ref')) {
                $table->dropUnique(['source_ref']);
                $table->dropColumn('source_ref');
            }
        });
    }
};
