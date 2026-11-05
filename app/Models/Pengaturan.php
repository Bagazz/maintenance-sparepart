<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Setelan aplikasi sederhana (kunci -> nilai) yang diatur admin dari layar, bukan dari .env. */
class Pengaturan extends Model
{
    protected $table = 'pengaturan';
    protected $primaryKey = 'kunci';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['kunci', 'nilai'];

    public static function ambil(string $kunci, mixed $bawaan = null): mixed
    {
        return self::whereKey($kunci)->value('nilai') ?? $bawaan;
    }

    public static function simpan(string $kunci, mixed $nilai): void
    {
        self::updateOrCreate(['kunci' => $kunci], ['nilai' => $nilai]);
    }
}
