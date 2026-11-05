<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Purchase Request yang dilacak sampai barangnya diterima. */
class PurchaseRequest extends Model
{
    /** Status yang barangnya masih ditunggu. */
    public const TERBUKA = ['diajukan', 'po', 'sebagian'];

    protected $fillable = ['kode', 'status', 'no_po', 'pemasok', 'perkiraan_datang', 'catatan', 'dibuat_oleh', 'po_at', 'selesai_at'];

    protected $casts = [
        'perkiraan_datang' => 'date',
        'po_at'            => 'datetime',
        'selesai_at'       => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(PurchaseRequestItem::class);
    }

    public function scopeTerbuka($query)
    {
        return $query->whereIn('status', self::TERBUKA);
    }

    /** PO sudah terbit tetapi perkiraan datang sudah lewat dan barang belum lengkap. */
    public function scopeTerlambat($query)
    {
        return $query->whereIn('status', ['po', 'sebagian'])->whereNotNull('perkiraan_datang')->whereDate('perkiraan_datang', '<', today());
    }

    public function getTerlambatAttribute(): bool
    {
        return in_array($this->status, ['po', 'sebagian'], true) && $this->perkiraan_datang && $this->perkiraan_datang->lt(today());
    }

    public function getLabelStatusAttribute(): string
    {
        return match ($this->status) {
            'diajukan'   => __('Submitted'),
            'po'         => __('PO issued'),
            'sebagian'   => __('Partly received'),
            'selesai'    => __('Completed'),
            'dibatalkan' => __('Cancelled'),
        };
    }

    public static function kodeBaru(): string
    {
        $awalan = 'PR-' . now()->format('ym') . '-';
        $terakhir = self::where('kode', 'like', $awalan . '%')->orderByDesc('kode')->value('kode');
        $urut = $terakhir ? (int) substr($terakhir, -3) + 1 : 1;

        return $awalan . str_pad((string) $urut, 3, '0', STR_PAD_LEFT);
    }
}
