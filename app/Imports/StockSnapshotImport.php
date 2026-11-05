<?php

namespace App\Imports;

use App\Models\Inventory;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithBatchInserts;

class StockSnapshotImport implements ToCollection, WithBatchInserts
{
    public function collection($rows)
    {
        // Baris pertama (index 0) adalah header, dilewati
        foreach ($rows->skip(1) as $row) {
            $partNo    = $row[2] ?? null;   // kolom "Part"
            $deskripsi = $row[3] ?? null;   // kolom "Description"
            $stock     = $row[4] ?? null;   // kolom "On Hand"
            $lokasi    = $row[8] ?? null;   // kolom "Bin"

            if (!$partNo || !is_numeric($stock)) {
                continue;
            }

            Inventory::updateOrCreate(
                ['part_no' => (string) $partNo],
                [
                    'deskripsi'  => $deskripsi ?: '-',
                    'lokasi_rak' => $lokasi ?: '-',
                    'stock'      => (int) $stock,
                ]
            );
        }
    }

    public function batchSize(): int
    {
        return 200;
    }
}