<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockOpnameItem extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'tambahan'    => 'boolean',
        'stok_sistem' => 'integer',
        'qty_fisik'   => 'integer',
        'harga_satuan' => 'float',
        'dihitung_at' => 'datetime',
    ];

    public function opname()
    {
        return $this->belongsTo(StockOpname::class, 'stock_opname_id');
    }

    public function inventory()
    {
        return $this->belongsTo(Inventory::class);
    }

    /** Fisik − sistem; null bila belum dihitung. Negatif = barang kurang (hilang). */
    public function getSelisihAttribute(): ?int
    {
        return $this->qty_fisik === null ? null : $this->qty_fisik - (int) $this->stok_sistem;
    }

    public function getSelisihRpAttribute(): float
    {
        return (float) ($this->selisih ?? 0) * (float) $this->harga_satuan;
    }
}
