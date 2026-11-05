<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportBatch extends Model
{
    protected $guarded = ['id'];

    /** Siapa yang mengunggah file ini (admin atau manager yang diberi hak upload). */
    public function pengunggah()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function transactions()
    {
        return $this->hasMany(InventoryTransaction::class);
    }

    public function stockSnapshots()
    {
        return $this->hasMany(InventoryStockSnapshot::class);
    }
}
