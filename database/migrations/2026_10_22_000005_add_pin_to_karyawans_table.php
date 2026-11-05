<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PIN pribadi karyawan untuk kiosk: setelah scan kartu wajib ketik PIN (diatur admin / kepala sparepart).
 * PIN disimpan sebagai hash; salah 5x berturut-turut -> kartu terkunci sampai admin membuka.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('karyawans', function (Blueprint $table) {
            $table->string('pin_hash')->nullable()->after('aktif');
            $table->unsignedTinyInteger('pin_gagal')->default(0)->after('pin_hash');
            $table->timestamp('pin_terkunci_at')->nullable()->after('pin_gagal');
            $table->timestamp('pin_diatur_at')->nullable()->after('pin_terkunci_at');
        });
    }

    public function down(): void
    {
        Schema::table('karyawans', function (Blueprint $table) {
            $table->dropColumn(['pin_hash', 'pin_gagal', 'pin_terkunci_at', 'pin_diatur_at']);
        });
    }
};
