<?php

// Simpan file ini di: database/seeders/MesinSeeder.php
//
// Baca daftar nama mesin dari sheet "Logbook", kolom "Mesin", di file:
// database/seeders/Barcode Scanner Spare Part.xlsx
// Angka yang "nyasar" (part number) otomatis dibuang.

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Mesin;
use PhpOffice\PhpSpreadsheet\IOFactory;

class MesinSeeder extends Seeder
{
    public function run(): void
    {
        $fileName = 'Barcode Scanner Spare Part.xlsx';
        $path = database_path('seeders/' . $fileName);

        if (!file_exists($path)) {
            $this->command->warn("File \"$fileName\" tidak ditemukan di database/seeders/. Seeder MesinSeeder dilewati.");
            return;
        }

        $spreadsheet = IOFactory::load($path);
        $mesinSet = [];

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $rows = $sheet->toArray(null, true, true, false);
            if (empty($rows)) {
                continue;
            }

            $header = array_map(fn ($h) => strtolower(trim((string) $h)), $rows[0]);

            $colIndex = null;
            foreach ($header as $i => $col) {
                if ($col === 'mesin') {
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

                // buang juga kalau isinya gabungan angka + newline (mis. "12345678\n87654321")
                if (preg_match('/^[\d\s\n]+$/', $val)) {
                    continue;
                }

                $key = strtoupper($val);
                if (!isset($mesinSet[$key])) {
                    $mesinSet[$key] = $val;
                }
            }
        }

        $found = 0;
        foreach ($mesinSet as $nama) {
            Mesin::firstOrCreate(['nama' => $nama]);
            $found++;
        }

        if ($found > 0) {
            $this->command->info("Import nama mesin selesai. $found mesin unik diproses.");
        } else {
            $this->command->warn('Tidak ada nama mesin yang ditemukan di sheet Logbook (kolom Mesin).');
        }
    }
}