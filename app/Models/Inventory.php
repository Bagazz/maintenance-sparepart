<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    use HasFactory;

    // $guarded = [] dipertahankan seperti sebelumnya (dipakai proses import).
    protected $guarded = [];

    protected $casts = ['label_dicetak_at' => 'datetime', 'lokasi_lain' => 'array'];

    /** Bin barang yang akan dibuang (keputusan Kepala Spare Part): stoknya tidak dihitung sama sekali. */
    public static function binBuang(?string $bin): bool
    {
        return str_ends_with(strtoupper(trim((string) $bin)), '-WASTE');
    }

    /** Part gudang maintenance yang lokasinya aktif (bukan bin buang). */
    public function scopeGudangAktif($query)
    {
        return $query->where('lokasi_rak', 'like', \App\Services\StockOpnameService::GUDANG . '-%')
            ->where('lokasi_rak', 'not like', '%-WASTE');
    }

    /**
     * Part gudang maintenance yang labelnya belum ditempel: part baru (belum pernah dicetak)
     * atau part yang pindah laci (kode bin di label lama sudah tidak sama dengan lokasi sekarang).
     */
    public function scopePerluLabel($query)
    {
        return $query->gudangAktif()
            ->where(fn ($q) => $q->whereNull('label_dicetak_at')->orWhereColumn('label_lokasi', '!=', 'lokasi_rak'));
    }

    /** Hari sebuah part dianggap "baru" untuk pengingat foto. */
    public const HARI_PART_BARU = 30;

    /** Part yang baru masuk (impor ERP / Penerimaan Barang, 30 hari terakhir) dan belum punya foto barang. */
    public function scopeBaruTanpaFoto($query)
    {
        // Part dari pemuatan data awal (impor pertama) bukan "baru"; hanya yang masuk sesudahnya.
        $awal = \App\Models\ImportBatch::where('status', 'success')->min('created_at');
        $batas = now()->subDays(self::HARI_PART_BARU);
        if ($awal && \Illuminate\Support\Carbon::parse($awal)->addHour()->gt($batas)) {
            $batas = \Illuminate\Support\Carbon::parse($awal)->addHour();
        }

        return $query->whereNull('foto_barang')->where('inventories.created_at', '>', $batas);
    }

    /** Kode laci gudang maintenance yang belum punya label (laci baru). */
    public static function laciPerluLabel(): \Illuminate\Support\Collection
    {
        return self::gudangAktif()
            ->whereNotIn('lokasi_rak', \Illuminate\Support\Facades\DB::table('label_laci')->select('kode'))
            ->distinct()->pluck('lokasi_rak')->sort(SORT_NATURAL)->values();
    }

    public function transactions()
    {
        return $this->hasMany(InventoryTransaction::class);
    }

    public function stockSnapshots()
    {
        return $this->hasMany(InventoryStockSnapshot::class)->orderByDesc('tanggal_snapshot');
    }

    /**
     * Pencarian yang "memaafkan lupa nama": setiap kata harus cocok di salah satu
     * kolom (part no, deskripsi, kata kunci, kategori, lokasi), urutan kata bebas.
     * Contoh: "omron relay" tetap menemukan "RELAY LY2N 220VAC-OMRON".
     */
    public function scopeCari($query, string $q)
    {
        $kataKata = preg_split('/\s+/', trim($q), -1, PREG_SPLIT_NO_EMPTY);

        foreach ($kataKata as $kata) {
            $query->where(function ($sub) use ($kata) {
                $sub->where('part_no', 'like', "%$kata%")
                    ->orWhere('deskripsi', 'like', "%$kata%")
                    ->orWhere('kata_kunci', 'like', "%$kata%")
                    ->orWhere('kategori', 'like', "%$kata%")
                    ->orWhere('lokasi_rak', 'like', "%$kata%");
            });
        }

        return $query;
    }

    /** Part no persis di atas, lalu deskripsi yang diawali kata kunci, lalu sisanya. */
    public function scopeUrutRelevan($query, string $q)
    {
        $q = trim($q);

        return $query->orderByRaw(
            'CASE WHEN part_no = ? THEN 0 WHEN deskripsi LIKE ? THEN 1 WHEN foto_barang IS NOT NULL THEN 2 ELSE 3 END',
            [$q, $q . '%']
        )->orderBy('deskripsi');
    }

    /** Part yang tersimpan di satu rak: bin persis "M03-B5" atau lacinya "M03-B5D1", "M03-B5D12". */
    public function scopeDiRak($query, string $kodeRak)
    {
        // Kode rak sudah divalidasi ^[A-Z]\d{2}-[A-Z]+\d*$ sehingga aman dipakai di pola REGEXP.
        return $query->where(function ($q) use ($kodeRak) {
            $q->where('lokasi_rak', $kodeRak)
              ->orWhereRaw('lokasi_rak REGEXP ?', ['^' . $kodeRak . 'D[0-9]+$']);
        });
    }

    /** Nama gudang ERP (kode warehouse di depan bin). */
    public const NAMA_GUDANG = [
        'M03' => 'Maintenance Store',
        'F03' => 'Paper Production F03',
        'F05' => 'Sacks Production F05',
    ];

    /**
     * Urai kode bin ERP menjadi gudang / rak / laci.
     *   M03-D4D1  -> gudang M03, rak D4,  laci D1
     *   M03-E04D5 -> gudang M03, rak E04, laci D5
     *   M03-G3    -> gudang M03, rak G3   (tanpa laci)
     *   M03-TR    -> gudang M03, area TR  (Tooling Room)
     */
    public static function uraiLokasi(?string $kode): array
    {
        $kode = strtoupper(trim((string) $kode));
        $hasil = ['kode' => $kode, 'gudang' => null, 'nama_gudang' => null, 'rak' => null, 'laci' => null, 'kode_rak' => null];

        if (preg_match('/^([A-Z]\d{2})-([A-Z]+\d*)(D\d+)?$/', $kode, $m)) {
            $hasil['gudang'] = $m[1];
            $hasil['nama_gudang'] = self::NAMA_GUDANG[$m[1]] ?? $m[1];
            $hasil['rak'] = $m[2];
            $hasil['laci'] = $m[3] ?? null;
            $hasil['kode_rak'] = $m[1] . '-' . $m[2];
        }

        return $hasil;
    }

    public function getLokasiAttribute(): array
    {
        return self::uraiLokasi($this->lokasi_rak);
    }

    public function getFotoBarangUrlAttribute(): ?string
    {
        return $this->foto_barang ? asset('storage/' . $this->foto_barang) : null;
    }

    public function getFotoLokasiUrlAttribute(): ?string
    {
        return $this->foto_lokasi ? asset('storage/' . $this->foto_lokasi) : null;
    }
}
