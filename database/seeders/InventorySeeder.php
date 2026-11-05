<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InventorySeeder extends Seeder
{
    public function run(): void
    {
        // Data ini saya ambil langsung dari file Excel Anda
        $data = [
            ['part_no' => '300109574', 'deskripsi' => 'flat washer M5', 'lokasi_rak' => 'Rak A-01', 'stock' => 12],
            ['part_no' => '300109618', 'deskripsi' => 'Hexagon Head screw Bolt M5x0.8 x 25', 'lokasi_rak' => 'Rak A-02', 'stock' => 14],
            ['part_no' => '300110714', 'deskripsi' => 'Relay LY2N 220 VAC', 'lokasi_rak' => 'Rak A-03', 'stock' => 7],
            ['part_no' => '87654321', 'deskripsi' => 'Seven Segment', 'lokasi_rak' => 'Rak A-04', 'stock' => 29],
            ['part_no' => '12345678', 'deskripsi' => 'Switch', 'lokasi_rak' => 'Rak A-05', 'stock' => 4],
            ['part_no' => '13245768', 'deskripsi' => 'Mur Baut', 'lokasi_rak' => 'Rak A-06', 'stock' => 4],
            ['part_no' => '12348765', 'deskripsi' => 'Baut', 'lokasi_rak' => 'Rak A-07', 'stock' => 11],
            ['part_no' => '12312312', 'deskripsi' => 'Ring', 'lokasi_rak' => 'Rak A-08', 'stock' => 99],
            ['part_no' => '45645645', 'deskripsi' => 'Cakram', 'lokasi_rak' => 'Rak A-09', 'stock' => 20],
            ['part_no' => '78978978', 'deskripsi' => 'Bearing', 'lokasi_rak' => 'Rak A-10', 'stock' => 99],
            ['part_no' => '8908908', 'deskripsi' => 'Plat', 'lokasi_rak' => '-', 'stock' => 0],
        ];

        foreach ($data as $item) {
            $item['created_at'] = now();
            $item['updated_at'] = now();
            
            DB::table('inventories')->updateOrInsert(
                ['part_no' => $item['part_no']], // Mencegah data dobel
                $item
            );
        }

        $this->command->info("🎉 Sukses! Data dari Excel telah berhasil dimasukkan ke Database.");
    }
}