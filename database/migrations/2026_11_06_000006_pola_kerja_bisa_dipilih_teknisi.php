<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pola kerja (Shift / Non-shift): bawaan diatur Pak Nardi, teknisi boleh mengganti di kiosk untuk sesinya.
 * - karyawans.boleh_ganti_pola: Pak Nardi bisa mengunci karyawan yang menyalahgunakan pilihan ini.
 * - history_logs.shift_manual: pengambilan yang memakai pilihan teknisi (berbeda dari bawaan) ditandai.
 * - permintaan_ambil.pola: pola yang dipilih saat permintaan dibuat (dipakai saat disetujui).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('karyawans', 'boleh_ganti_pola')) {
            Schema::table('karyawans', fn (Blueprint $t) => $t->boolean('boleh_ganti_pola')->default(true)->after('shift'));
        }
        if (!Schema::hasColumn('history_logs', 'shift_manual')) {
            Schema::table('history_logs', fn (Blueprint $t) => $t->boolean('shift_manual')->default(false)->after('shift'));
        }
        if (!Schema::hasColumn('permintaan_ambil', 'pola')) {
            Schema::table('permintaan_ambil', fn (Blueprint $t) => $t->string('pola', 20)->nullable()->after('peminjam'));
        }
    }

    public function down(): void
    {
        foreach (['karyawans' => 'boleh_ganti_pola', 'history_logs' => 'shift_manual', 'permintaan_ambil' => 'pola'] as $tabel => $kolom) {
            if (Schema::hasColumn($tabel, $kolom)) {
                Schema::table($tabel, fn (Blueprint $t) => $t->dropColumn($kolom));
            }
        }
    }
};
