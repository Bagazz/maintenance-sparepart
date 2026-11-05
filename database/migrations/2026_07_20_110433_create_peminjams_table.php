<?php

// Simpan file ini di: database/migrations/2026_07_20_000001_create_peminjams_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('peminjams', function (Blueprint $table) {
            $table->id();
            $table->string('nama')->unique();
            $table->string('divisi')->nullable(); // opsional, kalau di excel ada kolom divisi/departemen
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('peminjams');
    }
};