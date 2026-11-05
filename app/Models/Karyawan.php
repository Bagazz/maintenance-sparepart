<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

/**
 * Karyawan maintenance yang boleh mengambil spare part di kiosk.
 * Login di halaman masuk dengan NAMA (huruf besar/kecil bebas) + password yang diatur admin.
 * Kolom pin_hash menyimpan password itu (nama kolom lama dipertahankan; PIN lama tetap berlaku sebagai password).
 */
class Karyawan extends Model
{
    /** Password salah berturut-turut sebanyak ini -> akun terkunci sampai dibuka admin. */
    public const MAKS_PIN_SALAH = 5;

    // Kolom PIN sengaja tidak fillable: hanya diubah lewat aturPin() / bukaKunci().
    protected $fillable = ['nomor_kartu', 'nama', 'bagian', 'shift', 'aktif'];

    protected $hidden = ['pin_hash'];

    protected $casts = [
        'aktif'           => 'boolean',
        'pin_terkunci_at' => 'datetime',
        'pin_diatur_at'   => 'datetime',
    ];

    public function historyLogs()
    {
        return $this->hasMany(HistoryLog::class);
    }

    /** Nomor kartu dibandingkan tanpa spasi & tanpa beda huruf besar/kecil. */
    public static function normalNomor(?string $nomor): string
    {
        return strtoupper(preg_replace('/\s+/', '', (string) $nomor));
    }

    /** Nama dibandingkan tanpa beda huruf besar/kecil & spasi berlebih (nama disimpan HURUF BESAR). */
    public static function normalNama(?string $nama): string
    {
        return mb_strtoupper(trim(preg_replace('/\s+/u', ' ', (string) $nama)));
    }

    public static function cariDariNama(?string $nama): ?self
    {
        $nama = self::normalNama($nama);

        return $nama === '' ? null : self::where('nama', $nama)->first();
    }

    /** Password teknisi: 4–50 karakter (angka saja boleh, mis. PIN lama). */
    public static function passwordValid(string $password): bool
    {
        $n = mb_strlen($password);
        return $n >= 4 && $n <= 50;
    }

    public static function cariDariKartu(?string $nomor): ?self
    {
        $nomor = self::normalNomor($nomor);

        return $nomor === '' ? null : self::where('nomor_kartu', $nomor)->first();
    }

    public function getPunyaPinAttribute(): bool
    {
        return $this->pin_hash !== null;
    }

    public function getTerkunciAttribute(): bool
    {
        return $this->pin_terkunci_at !== null;
    }

    /** Siap login: aktif, punya password, tidak terkunci. */
    public function getSiapKioskAttribute(): bool
    {
        return $this->aktif && $this->punya_pin && !$this->terkunci;
    }

    public static function pinValid(string $pin): bool
    {
        return (bool) preg_match('/^\d{4,6}$/', $pin);
    }

    /** PIN acak 6 angka (tidak berurutan / berulang semua). */
    public static function pinAcak(): string
    {
        do {
            $pin = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (count(array_unique(str_split($pin))) < 3 || str_contains('0123456789', $pin) || str_contains('9876543210', $pin));

        return $pin;
    }

    public function aturPin(string $pin): void
    {
        $this->forceFill([
            'pin_hash'        => Hash::make($pin),
            'pin_gagal'       => 0,
            'pin_terkunci_at' => null,
            'pin_diatur_at'   => now(),
        ])->save();
    }

    public function bukaKunci(): void
    {
        $this->forceFill(['pin_gagal' => 0, 'pin_terkunci_at' => null])->save();
    }

    /**
     * Cek PIN di kiosk. Benar -> hitungan gagal direset. Salah -> hitungan naik; pada batas -> kartu terkunci.
     * @return bool PIN benar
     */
    public function cekPin(string $pin): bool
    {
        if (!$this->punya_pin || $this->terkunci) {
            return false;
        }
        if (Hash::check($pin, $this->pin_hash)) {
            if ($this->pin_gagal) {
                $this->forceFill(['pin_gagal' => 0])->save();
            }
            return true;
        }

        $gagal = $this->pin_gagal + 1;
        $this->forceFill([
            'pin_gagal'       => $gagal,
            'pin_terkunci_at' => $gagal >= self::MAKS_PIN_SALAH ? now() : null,
        ])->save();

        return false;
    }

    public function getSisaPercobaanAttribute(): int
    {
        return max(0, self::MAKS_PIN_SALAH - $this->pin_gagal);
    }
}
