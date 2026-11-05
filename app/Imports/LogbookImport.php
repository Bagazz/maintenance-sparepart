<?php

namespace App\Imports;

use App\Models\HistoryLog;
use App\Models\Inventory;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Carbon\Carbon;

class LogbookImport implements ToModel, WithHeadingRow, WithBatchInserts
{
    public function model(array $row)
    {
        $partNo = $row['part_no'] ?? null;
        if (!$partNo) {
            return null;
        }

        // Selalu ambil nama barang dari tabel Inventory, bukan dari kolom
        // deskripsi di Logbook (soalnya di Excel isinya rumus VLOOKUP)
        $itemName = optional(
            Inventory::where('part_no', $partNo)->first()
        )->deskripsi ?? '-';

        $tanggal = $row['tanggal'] ?? null;
        $jam     = $row['jam'] ?? null;

        $datetime = $tanggal instanceof \DateTime
            ? Carbon::parse($tanggal->format('Y-m-d') . ' ' . ($jam instanceof \DateTime ? $jam->format('H:i:s') : $jam))
            : now();

        $jumlah = is_numeric($row['quantity'] ?? null) ? (int) $row['quantity'] : 0;
        $qtyReturned = is_numeric($row['qty_dikembalikan'] ?? null) ? (int) $row['qty_dikembalikan'] : 0;

        return new HistoryLog([
            'part_no'      => $partNo,
            'item_name'    => $itemName,
            'machine'      => is_string($row['mesin'] ?? null) ? $row['mesin'] : null,
            'operator'     => is_string($row['pengambil'] ?? null) ? $row['pengambil'] : null,
            'aksi'         => 'pakai',
            'jumlah'       => $jumlah,
            'qty_returned' => $qtyReturned,
            'created_at'   => $datetime,
            'updated_at'   => now(),
        ]);
    }

    public function batchSize(): int
    {
        return 200;
    }
}