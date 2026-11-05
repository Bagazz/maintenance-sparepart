<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 2 — identitas & jenis barang:
 * - karyawans          : pengganti daftar nama "peminjams". Teknisi dikenali dari
 *                        nomor kartu karyawan (scan/ketik), bukan pilih nama.
 * - history_logs       : karyawan_id (siapa persisnya) + no_pm (nomor Job PM,
 *                        kunci pencocokan dengan sheet Issue ERP).
 * - inventories.tipe   : 'habis_pakai' (dipakai, tidak kembali) atau 'alat'
 *                        (dipinjam, wajib kembali). Default Tooling Room = alat.
 * - inventories.area_rak : deskripsi bin dari ERP, mis. "Bearings", "Tooling Room".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('karyawans')) {
            Schema::create('karyawans', function (Blueprint $table) {
                $table->id();
                $table->string('nomor_kartu', 50)->nullable()->unique();
                $table->string('nama');
                $table->string('bagian', 100)->nullable();
                $table->string('shift', 20)->nullable();
                $table->boolean('aktif')->default(true);
                $table->timestamps();
            });

            // Nama lama dari uji coba logbook dipindahkan, tanpa nomor kartu.
            // Belum bisa dipakai scan sampai admin mengisi nomornya.
            if (Schema::hasTable('peminjams')) {
                foreach (DB::table('peminjams')->get() as $p) {
                    DB::table('karyawans')->insert([
                        'nama'       => $p->nama,
                        'bagian'     => $p->divisi,
                        'aktif'      => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        Schema::table('history_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('history_logs', 'karyawan_id')) {
                $table->unsignedBigInteger('karyawan_id')->nullable()->index();
            }
            if (!Schema::hasColumn('history_logs', 'no_pm')) {
                $table->string('no_pm', 50)->nullable()->index();
            }
        });

        Schema::table('inventories', function (Blueprint $table) {
            if (!Schema::hasColumn('inventories', 'tipe')) {
                $table->string('tipe', 20)->default('habis_pakai')->after('kategori')->index();
            }
            if (!Schema::hasColumn('inventories', 'area_rak')) {
                $table->string('area_rak', 100)->nullable()->after('lokasi_rak');
            }
        });

        DB::table('inventories')->where('lokasi_rak', 'M03-TR')->update(['tipe' => 'alat']);

        // Baris hasil import Issue: "machine" sebenarnya nomor Job PM, dan itu pemakaian (bukan pinjam).
        DB::table('history_logs')
            ->whereNotNull('source_ref')
            ->update(['aksi' => 'pakai', 'no_pm' => DB::raw('machine')]);
    }

    public function down(): void
    {
        Schema::table('inventories', function (Blueprint $table) {
            if (Schema::hasColumn('inventories', 'tipe')) {
                $table->dropIndex(['tipe']);
                $table->dropColumn('tipe');
            }
            if (Schema::hasColumn('inventories', 'area_rak')) {
                $table->dropColumn('area_rak');
            }
        });

        Schema::table('history_logs', function (Blueprint $table) {
            foreach (['karyawan_id', 'no_pm'] as $kolom) {
                if (Schema::hasColumn('history_logs', $kolom)) {
                    $table->dropIndex([$kolom]);
                    $table->dropColumn($kolom);
                }
            }
        });

        Schema::dropIfExists('karyawans');
    }
};
