<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryTransaction extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'tanggal_transaksi' => 'date',
    ];

    public function inventory()
    {
        return $this->belongsTo(Inventory::class);
    }

    public function importBatch()
    {
        return $this->belongsTo(ImportBatch::class);
    }

    /**
     * Bikin natural key unik dari sumber Excel, dipakai untuk import idempotent.
     * Contoh: makeSourceRef('receipt', 'PI10040', '103699-4095', 1)
     */
    public static function makeSourceRef(string ...$parts): string
    {
        return implode('|', array_map('trim', $parts));
    }
}
