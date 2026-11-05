<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PenerimaanBarang extends Model
{
    protected $table = 'penerimaan_barang';

    protected $guarded = ['id'];

    protected $casts = [
        'tanggal'       => 'date',
        'dibatalkan_at' => 'datetime',
    ];

    public function baris()
    {
        return $this->hasMany(InventoryTransaction::class, 'penerimaan_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Kode berikutnya: PB-YYMM-001, nomor urut per bulan. */
    public static function kodeBaru(): string
    {
        $awalan = 'PB-' . now()->format('ym') . '-';
        $terakhir = static::where('kode', 'like', $awalan . '%')->orderByDesc('kode')->value('kode');

        return $awalan . str_pad((string) ($terakhir ? (int) substr($terakhir, -3) + 1 : 1), 3, '0', STR_PAD_LEFT);
    }
}
