<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HistoryLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'part_no', 'item_name', 'machine', 'operator', 'peminjam',
        'aksi', 'jumlah', 'qty_returned', 'source_ref', 'karyawan_id', 'no_pm', 'kode_pengambilan', 'shift', 'shift_manual',
        'created_at', 'updated_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'rekon_abaikan' => 'boolean',
        'shift_manual' => 'boolean',
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

    /**
     * Shift saat pengambilan. Dicatat otomatis ketika barang diambil (Karyawan::labelShift);
     * catatan lama tanpa isian dihitung dari jam pengambilan.
     */
    public function getShiftAttribute(): string
    {
        return $this->attributes['shift'] ?? Karyawan::shiftMenurutJam($this->created_at);
    }

    /** Label shift untuk tampilan & ekspor; pilihan teknisi (berbeda dari bawaan Pak Nardi) ditandai. */
    public function getLabelShiftAttribute(): string
    {
        return __($this->shift) . ($this->shift_manual ? ' · ' . __('chosen by technician') : '');
    }
}
