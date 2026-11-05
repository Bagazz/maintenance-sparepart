<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HistoryLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'part_no', 'item_name', 'machine', 'operator', 'peminjam',
        'aksi', 'jumlah', 'qty_returned', 'source_ref', 'karyawan_id', 'no_pm', 'kode_pengambilan',
        'created_at', 'updated_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'rekon_abaikan' => 'boolean',
    ];

    public function karyawan()
    {
        return $this->belongsTo(Karyawan::class);
    }

    public function getSisaAttribute(): int
    {
        return $this->jumlah - $this->qty_returned;
    }

    /** Semua pengambilan = dipakai. Retur hanya untuk barang yang tidak jadi dipakai. */
    public function getStatusAttribute(): string
    {
        if ($this->qty_returned <= 0) {
            return 'dipakai';
        }

        return $this->sisa <= 0 ? 'diretur_semua' : 'diretur_sebagian';
    }

    public function getShiftAttribute(): string
    {
        $jam = $this->created_at->format('H:i');

        if ($jam >= '06:30' && $jam < '14:30') {
            return 'Shift 1';
        }

        if ($jam >= '14:30' && $jam < '22:30') {
            return 'Shift 2';
        }

        return 'Shift 3';
    }
}
