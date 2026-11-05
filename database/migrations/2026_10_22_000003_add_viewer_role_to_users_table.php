<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Peran baru "viewer": Presdir / manager bisa melihat dashboard & laporan tanpa bisa mengubah data. */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY role ENUM('admin', 'user', 'viewer') NOT NULL DEFAULT 'user'");
    }

    public function down(): void
    {
        DB::table('users')->where('role', 'viewer')->delete();
        DB::statement("ALTER TABLE users MODIFY role ENUM('admin', 'user') NOT NULL DEFAULT 'user'");
    }
};
