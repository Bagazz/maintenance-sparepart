<?php

namespace App\Imports;

use App\Models\Inventory;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class MasterBarangImport implements ToModel, WithHeadingRow
{
    public function model(array $row)
    {
        return Inventory::updateOrCreate(
            ['part_no' => $row['part_no']],
            [
                'deskripsi'  => $row['deskripsi'],
                'lokasi_rak' => $row['lokasi_rak'] ?? '-',
                'stock'      => $row['stock'] ?? 0,
            ]
        );
    }
}