<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class BarcodeScannerImport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            'Master_Barang' => new MasterBarangImport,
            'Logbook'       => new LogbookImport,
        ];
    }
}