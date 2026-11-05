<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseRequestItem extends Model
{
    protected $fillable = ['purchase_request_id', 'inventory_id', 'part_no', 'deskripsi', 'qty_minta', 'qty_diterima', 'harga_perkiraan'];

    protected $casts = [
        'qty_minta'       => 'integer',
        'qty_diterima'    => 'integer',
        'harga_perkiraan' => 'float',
    ];

    public function purchaseRequest()
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function inventory()
    {
        return $this->belongsTo(Inventory::class);
    }

    public function getSisaAttribute(): int
    {
        return max(0, $this->qty_minta - $this->qty_diterima);
    }
}
