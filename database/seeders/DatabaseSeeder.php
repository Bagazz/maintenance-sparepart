<?php
// database/seeders/DatabaseSeeder.php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // updateOrCreate: kalau user sudah ada, password akan DIRESET ke nilai di bawah.
        // Jangan pakai Hash::make() di sini -- model User sudah punya
        // cast 'password' => 'hashed' yang otomatis hash saat disimpan.
        // Kalau di-Hash::make() manual DI SINI JUGA, password akan ter-hash dua kali
        // (double hashing) dan login jadi selalu gagal.
        User::updateOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'name' => 'Super Admin',
                'password' => 'admin12345',
                'role' => 'admin',
                'email_verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'user@gmail.com'],
            [
                'name' => 'User Biasa',
                'password' => 'user12345',
                'role' => 'user',
                'email_verified_at' => now(),
            ]
        );

        $this->call(InventorySeeder::class);
        $this->call(PeminjamSeeder::class);
        $this->call(MesinSeeder::class);

        $this->command->info("🎉 Data berhasil dibuat/diverifikasi!");
    }
}