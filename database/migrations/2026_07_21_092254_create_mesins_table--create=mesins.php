<?php

// Simpan file ini di: database/migrations/2026_07_21_000001_create_mesins_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('mesins')) {
            Schema::create('mesins', function (Blueprint $table) {
                $table->id();
                $table->string('nama')->unique();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mesins');
    }
};