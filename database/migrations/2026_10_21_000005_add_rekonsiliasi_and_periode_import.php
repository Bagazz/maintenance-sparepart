<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 3 — import data ERP historis & rekonsiliasi scan vs ERP.
 *
 * import_batches:
 *   - tanggal_stok     : tanggal posisi stok di sheet master (ditentukan admin saat upload)
 *   - perbarui_master  : false bila file lebih lama dari data stok terakhir -> stok saat ini tidak ditimpa
 *   - periode_awal/akhir : rentang tanggal transaksi Receipt/Issue di file
 *
 * history_logs (pasangan scan aplikasi <-> baris Issue ERP):
 *   - rekon_pasangan_id : id baris pasangannya (dua arah)
 *   - rekon_metode      : 'otomatis' / 'manual'
 *   - rekon_abaikan     : dikecualikan dari laporan (mis. sudah dijelaskan), + rekon_catatan
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_batches', function (Blueprint $table) {
            if (!Schema::hasColumn('import_batches', 'tanggal_stok')) {
                $table->date('tanggal_stok')->nullable()->after('status');
            }
            if (!Schema::hasColumn('import_batches', 'perbarui_master')) {
                $table->boolean('perbarui_master')->default(true)->after('tanggal_stok');
            }
            if (!Schema::hasColumn('import_batches', 'periode_awal')) {
                $table->date('periode_awal')->nullable()->after('perbarui_master');
                $table->date('periode_akhir')->nullable()->after('periode_awal');
            }
            if (!Schema::hasColumn('import_batches', 'part_baru')) {
                $table->unsignedInteger('part_baru')->default(0)->after('failed_rows');
            }
        });

        Schema::table('history_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('history_logs', 'rekon_pasangan_id')) {
                $table->unsignedBigInteger('rekon_pasangan_id')->nullable()->index();
                $table->string('rekon_metode', 10)->nullable();
                $table->boolean('rekon_abaikan')->default(false);
                $table->string('rekon_catatan')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('history_logs', function (Blueprint $table) {
            if (Schema::hasColumn('history_logs', 'rekon_pasangan_id')) {
                $table->dropIndex(['rekon_pasangan_id']);
                $table->dropColumn(['rekon_pasangan_id', 'rekon_metode', 'rekon_abaikan', 'rekon_catatan']);
            }
        });

        Schema::table('import_batches', function (Blueprint $table) {
            foreach (['tanggal_stok', 'perbarui_master', 'periode_awal', 'periode_akhir', 'part_baru'] as $kolom) {
                if (Schema::hasColumn('import_batches', $kolom)) {
                    $table->dropColumn($kolom);
                }
            }
        });
    }
};
