<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shift di Detpak bergilir tiap minggu (mis. minggu ini pagi, minggu depan siang).
 * Teknisi mengonfirmasi / mengganti shift-nya sendiri sekali per minggu; Kepala Spare Part tetap bisa mengatur.
 * shift_dikonfirmasi_at = kapan shift terakhir dipastikan (oleh teknisi atau admin).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('karyawans', function (Blueprint $table) {
            $table->timestamp('shift_dikonfirmasi_at')->nullable()->after('shift');
        });
    }

    public function down(): void
    {
        Schema::table('karyawans', function (Blueprint $table) {
            $table->dropColumn('shift_dikonfirmasi_at');
        });
    }
};
