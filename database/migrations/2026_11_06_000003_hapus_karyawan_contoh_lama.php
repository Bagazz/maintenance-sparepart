<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Karyawan contoh lama (sebelum daftar resmi dari Userlist ERP) dihapus atas permintaan Kepala Spare Part.
 * Hanya yang tanpa No ID dan belum punya riwayat pengambilan / permintaan; yang sudah punya riwayat dibiarkan.
 */
return new class extends Migration
{
    private const LAMA = ['MAHENDRA', 'KANG HUSEN', 'MAHEN', 'NUR'];

    public function up(): void
    {
        foreach (DB::table('karyawans')->whereIn('nama', self::LAMA)->whereNull('nomor_kartu')->pluck('id') as $id) {
            $punyaRiwayat = DB::table('history_logs')->where('karyawan_id', $id)->exists()
                || DB::table('permintaan_ambil')->where('karyawan_id', $id)->exists();
            if (!$punyaRiwayat) {
                DB::table('karyawans')->where('id', $id)->delete();
            }
        }
    }

    public function down(): void
    {
        // Data contoh tidak dikembalikan.
    }
};
