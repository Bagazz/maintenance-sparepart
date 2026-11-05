<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Padanan nomor Job PM dari ERP ke nama mesin. */
class JobMesin extends Model
{
    protected $fillable = ['no_job', 'mesin', 'keterangan'];

    public static function normalJob(?string $job): string
    {
        return strtoupper(preg_replace('/\s+/', '', (string) $job));
    }

    public static function normalMesin(?string $mesin): string
    {
        return mb_strtoupper(trim(preg_replace('/\s+/', ' ', (string) $mesin)));
    }

    /**
     * Simpan / perbarui satu padanan. Mesin baru otomatis ditambahkan ke daftar
     * mesin (dipakai dropdown "Mesin tujuan" di kiosk).
     */
    public static function petakan(string $job, string $mesin, ?string $keterangan = null): self
    {
        $mesin = self::normalMesin($mesin);
        Mesin::firstOrCreate(['nama' => $mesin]);

        return self::updateOrCreate(
            ['no_job' => self::normalJob($job)],
            ['mesin' => $mesin, 'keterangan' => $keterangan ?: null]
        );
    }
}
