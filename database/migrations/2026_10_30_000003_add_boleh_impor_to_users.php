<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hak tambahan "upload Excel ERP" untuk akun viewer tertentu (Pak Anton, manager).
 * Hanya Import ERP — persetujuan, penerimaan, master data tetap khusus admin (Pak Nardi).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('boleh_impor')->default(false)->after('role');
        });

        if ($email = env('MANAGER_EMAIL')) {
            DB::table('users')->where('email', $email)->where('role', 'viewer')->update(['boleh_impor' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('boleh_impor');
        });
    }
};
