<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockOpname extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'hitung_buta'  => 'boolean',
        'diposting_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(StockOpnameItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getBerjalanAttribute(): bool
    {
        return $this->status === 'berjalan';
    }

    /** Kode berikutnya: SO-YYMM-001, nomor urut per bulan. */
    public static function kodeBaru(): string
    {
        $awalan = 'SO-' . now()->format('ym') . '-';
        $terakhir = static::where('kode', 'like', $awalan . '%')->orderByDesc('kode')->value('kode');

        return $awalan . str_pad((string) ($terakhir ? (int) substr($terakhir, -3) + 1 : 1), 3, '0', STR_PAD_LEFT);
    }
}
