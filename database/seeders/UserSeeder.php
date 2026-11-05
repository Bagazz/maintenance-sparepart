<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Akun tetap aplikasi (admin, kiosk, 2 viewer). Password diambil dari .env (ADMIN_/USER_/PRESDIR_/MANAGER_PASSWORD)
 * supaya tidak tertulis di kode. Aman dijalankan berulang (updateOrCreate):
 *   php artisan db:seed --class=UserSeeder
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $akun = [
            ['email' => env('ADMIN_EMAIL', 'admin@gmail.com'), 'name' => env('ADMIN_NAME', 'Super Admin'), 'role' => 'admin', 'password' => env('ADMIN_PASSWORD')],
            ['email' => env('USER_EMAIL', 'user@gmail.com'),   'name' => env('USER_NAME', 'User Biasa'),   'role' => 'user',  'password' => env('USER_PASSWORD')],
            // Akun lihat-saja (viewer): dashboard, analitik & laporan, tanpa bisa mengubah data.
            ['email' => env('PRESDIR_EMAIL', 'presdir@detpak.local'), 'name' => env('PRESDIR_NAME', 'Presiden Direktur'), 'role' => 'viewer', 'password' => env('PRESDIR_PASSWORD')],
            ['email' => env('MANAGER_EMAIL', 'anton@detpak.local'),   'name' => env('MANAGER_NAME', 'Pak Anton'),          'role' => 'viewer', 'password' => env('MANAGER_PASSWORD')],
        ];

        foreach ($akun as $a) {
            if (!$a['password']) {
                $this->command?->error("Password untuk {$a['email']} belum diisi di .env (ADMIN_/USER_/PRESDIR_/MANAGER_PASSWORD). Akun dilewati.");
                continue;
            }

            User::updateOrCreate(
                ['email' => $a['email']],
                [
                    'name'              => $a['name'],
                    'role'              => $a['role'],
                    'password'          => Hash::make($a['password']),
                    'email_verified_at' => now(),
                ]
            );
            $this->command?->info("Akun {$a['email']} ({$a['role']}) diset.");
        }
    }
}
