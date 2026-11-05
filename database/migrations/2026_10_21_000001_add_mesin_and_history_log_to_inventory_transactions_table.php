<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - mesin: supaya biaya per mesin bisa dihitung langsung dari transaksi
 *   (sebelumnya nama mesin hanya tersimpan di teks keterangan).
 * - history_log_id: menghubungkan transaksi OUT dari aplikasi (scan/ambil manual)
 *   dengan baris history_logs-nya, dipakai saat pengembalian.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('inventory_transactions', 'mesin')) {
                $table->string('mesin')->nullable()->after('qty')->index();
            }
            if (!Schema::hasColumn('inventory_transactions', 'history_log_id')) {
                $table->unsignedBigInteger('history_log_id')->nullable()->after('import_batch_id')->index();
            }
        });

        // Isi kolom mesin untuk data import lama dari teks keterangan.
        DB::table('inventory_transactions')
            ->whereNull('mesin')
            ->where('keterangan', 'like', 'Maintenance Mesin: %')
            ->update(['mesin' => DB::raw("TRIM(SUBSTRING(keterangan, LENGTH('Maintenance Mesin: ') + 1))")]);

        DB::table('inventory_transactions')
            ->whereNull('mesin')
            ->where('keterangan', 'like', '%- Mesin/Job: %')
            ->update(['mesin' => DB::raw("TRIM(SUBSTRING_INDEX(keterangan, '- Mesin/Job: ', -1))")]);
    }

    public function down(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table) {
            foreach (['mesin', 'history_log_id'] as $kolom) {
                if (Schema::hasColumn('inventory_transactions', $kolom)) {
                    $table->dropIndex([$kolom]);
                    $table->dropColumn($kolom);
                }
            }
        });
    }
};
