<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Padanan nomor Job PM (ERP, mis. PM25094) -> nama mesin (mis. WEBBER).
 * ERP sheet Issue hanya mencatat nomor Job; tabel ini membuat laporan biaya
 * (Top 5, rincian per mesin) bisa ditampilkan per mesin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_mesins', function (Blueprint $table) {
            $table->id();
            $table->string('no_job', 50)->unique();
            $table->string('mesin', 100)->index();
            $table->string('keterangan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_mesins');
    }
};
