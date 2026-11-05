<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Permintaan pengambilan teknisi yang menunggu persetujuan Kepala Spare Part. */
class PermintaanAmbil extends Model
{
    protected $table = 'permintaan_ambil';

    protected $fillable = ['kode', 'karyawan_id', 'peminjam', 'status', 'total_nilai', 'alasan_tolak', 'diputus_oleh', 'diputus_at'];

    protected $casts = [
        'total_nilai' => 'float',
        'diputus_at'  => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(PermintaanAmbilItem::class, 'permintaan_id');
    }

    public function karyawan()
    {
        return $this->belongsTo(Karyawan::class);
    }

    public function scopeMenunggu($query)
    {
        return $query->where('status', 'menunggu');
    }

    /** Nomor permintaan berikutnya, mis. PA-2610-007. */
    public static function kodeBaru(): string
    {
        $awalan = 'PA-' . now()->format('ym') . '-';
        $terakhir = self::where('kode', 'like', $awalan . '%')->orderByDesc('kode')->value('kode');
        $urut = $terakhir ? (int) substr($terakhir, -3) + 1 : 1;

        return $awalan . str_pad((string) $urut, 3, '0', STR_PAD_LEFT);
    }
}
