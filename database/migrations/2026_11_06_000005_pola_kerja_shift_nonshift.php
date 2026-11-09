<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Konfirmasi shift mingguan oleh teknisi dihapus (permintaan Pak Nardi). Penggantinya:
 * - karyawans.shift = pola kerja tetap: "Shift" atau "Non-shift" (diatur Kepala Spare Part);
 * - history_logs.shift = label shift yang dicatat otomatis saat pengambilan (Shift 1/2/3 dari jam,
 *   atau Non-shift / Non-shift di luar jam kerja).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('history_logs', 'shift')) {
            Schema::table('history_logs', function (Blueprint $table) {
                $table->string('shift', 40)->nullable()->after('karyawan_id');
            });
        }

        // Pengambilan lama dari aplikasi: isi dari jam pengambilan (sama seperti yang selama ini ditampilkan).
        DB::table('history_logs')->whereNull('source_ref')->whereNull('shift')->update(['shift' => DB::raw(
            "CASE WHEN TIME(created_at) >= '06:30' AND TIME(created_at) < '14:30' THEN 'Shift 1'
                  WHEN TIME(created_at) >= '14:30' AND TIME(created_at) < '22:30' THEN 'Shift 2'
                  ELSE 'Shift 3' END"
        )]);

        if (Schema::hasColumn('karyawans', 'shift_dikonfirmasi_at')) {
            Schema::table('karyawans', function (Blueprint $table) {
                $table->dropColumn('shift_dikonfirmasi_at');
            });
        }

        // Pola kerja: "Shift 1/2/3" -> Shift. Yang belum diatur: teknisi = Shift, jabatan lain = Non-shift
        // (bisa diubah kapan saja di menu Karyawan).
        DB::table('karyawans')->whereIn('shift', ['Shift 1', 'Shift 2', 'Shift 3'])->update(['shift' => 'Shift']);
        DB::table('karyawans')->whereNull('shift')->where('bagian', 'like', '%Technician%')->update(['shift' => 'Shift']);
        DB::table('karyawans')->whereNull('shift')->whereNotNull('bagian')->update(['shift' => 'Non-shift']);
        DB::table('karyawans')->whereNull('shift')->update(['shift' => 'Shift']);
    }

    public function down(): void
    {
        if (Schema::hasColumn('history_logs', 'shift')) {
            Schema::table('history_logs', fn (Blueprint $table) => $table->dropColumn('shift'));
        }
        if (!Schema::hasColumn('karyawans', 'shift_dikonfirmasi_at')) {
            Schema::table('karyawans', fn (Blueprint $table) => $table->timestamp('shift_dikonfirmasi_at')->nullable()->after('shift'));
        }
    }
};
