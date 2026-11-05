<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Nomor bukti pengambilan (satu checkout keranjang = satu nomor), mis. AMB-261028-0915-07. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('history_logs', function (Blueprint $table) {
            $table->string('kode_pengambilan', 40)->nullable()->after('no_pm')->index();
        });
    }

    public function down(): void
    {
        Schema::table('history_logs', function (Blueprint $table) {
            $table->dropColumn('kode_pengambilan');
        });
    }
};
