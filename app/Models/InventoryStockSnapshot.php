<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryStockSnapshot extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'tanggal_snapshot' => 'date',
    ];

    public function inventory()
    {
        return $this->belongsTo(Inventory::class);
    }

    public function importBatch()
    {
        return $this->belongsTo(ImportBatch::class);
    }
}
