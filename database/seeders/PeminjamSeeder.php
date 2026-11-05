<?php

// Ganti isi file: database/seeders/PeminjamSeeder.php
//
// Baca daftar nama dari 2 sumber di file excel:
// - Sheet "Master_Barang", kolom "Nama"
// - Sheet "Logbook", kolom "Pengambil"
// Angka yang "nyasar" (part number) otomatis dibuang.

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Peminjam;
use PhpOffice\PhpSpreadsheet\IOFactory;

class PeminjamSeeder extends Seeder
{
    public function run(): void
    {
        $fileName = 'Barcode Scanner Spare Part.xlsx';
        $path = database_path('seeders/' . $fileName);

        if (!file_exists($path)) {
            $this->command->warn("File \"$fileName\" tidak ditemukan di database/seeders/. Seeder dilewati.");
            return;
        }

        $spreadsheet = IOFactory::load($path);
        $namaSet = [];

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $rows = $sheet->toArray(null, true, true, false);
            if (empty($rows)) {
                continue;
            }

            $header = array_map(fn ($h) => strtolower(trim((string) $h)), $rows[0]);

            // cari kolom "nama" ATAU "pengambil"
            $colIndex = null;
            foreach ($header as $i => $col) {
                if ($col === 'nama' || $col === 'pengambil') {
                    $colIndex = $i;
                    break;
                }
            }

            if ($colIndex === null) {
                continue;
            }

            for ($i = 1; $i < count($rows); $i++) {
                $val = trim((string) ($rows[$i][$colIndex] ?? ''));

                if ($val === '' || is_numeric($val)) {
                    continue; // kosong atau ternyata part number nyasar, dibuang
                }

                // dedupe tanpa peduli huruf besar/kecil, simpan versi pertama yang ditemukan
                $key = strtoupper($val);
                if (!isset($namaSet[$key])) {
                    $namaSet[$key] = $val;
                }
            }
        }

        $found = 0;
        foreach ($namaSet as $nama) {
            Peminjam::firstOrCreate(['nama' => $nama]);
            $found++;
        }

        if ($found > 0) {
            $this->command->info("Import nama peminjam selesai. $found nama unik diproses.");
        } else {
            $this->command->warn('Tidak ada nama yang ditemukan di sheet Master_Barang (kolom Nama) atau Logbook (kolom Pengambil).');
        }
    }
}