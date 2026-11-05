<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PermintaanAmbilItem extends Model
{
    protected $table = 'permintaan_ambil_items';

    protected $fillable = ['permintaan_id', 'inventory_id', 'part_no', 'deskripsi', 'lokasi', 'mesin', 'no_pm', 'jumlah', 'harga_satuan'];

    protected $casts = [
        'jumlah'       => 'integer',
        'harga_satuan' => 'float',
    ];

    public function permintaan()
    {
        return $this->belongsTo(PermintaanAmbil::class, 'permintaan_id');
    }

    public function getNilaiAttribute(): float
    {
        return $this->jumlah * $this->harga_satuan;
    }
}
