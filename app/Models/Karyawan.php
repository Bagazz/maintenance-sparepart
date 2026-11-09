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
    /** Pola kerja karyawan (diatur Kepala Spare Part). Kosong dianggap "Shift". */
    public const POLA = ['Shift', 'Non-shift'];

    /** Jam kerja normal karyawan non-shift (Senin–Sabtu). */
    public const JAM_NONSHIFT = ['08:00', '17:00'];

    /** Label shift yang dicatat pada pengambilan (teks sumber; ditampilkan lewat terjemahan). */
    public const LABEL_DI_LUAR_JAM = 'Non-shift (outside working hours)';

    /** Password salah berturut-turut sebanyak ini -> akun terkunci sampai dibuka admin. */
    public const MAKS_PIN_SALAH = 5;

    // Kolom PIN sengaja tidak fillable: hanya diubah lewat aturPin() / bukaKunci().
    protected $fillable = ['nomor_kartu', 'nama', 'bagian', 'shift', 'boleh_ganti_pola', 'aktif'];

    protected $hidden = ['pin_hash'];

    protected $casts = [
        'aktif'           => 'boolean',
        'boleh_ganti_pola' => 'boolean',
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

    public function getNonShiftAttribute(): bool
    {
        return $this->shift === 'Non-shift';
    }

    /** Pola kerja untuk tampilan ("Shift" bila belum diatur). */
    public function getPolaKerjaAttribute(): string
    {
        return $this->non_shift ? 'Non-shift' : 'Shift';
    }

    /**
     * Pola kerja yang berlaku untuk satu sesi kiosk: pilihan teknisi (bila Pak Nardi mengizinkan),
     * selain itu bawaan dari menu Karyawan.
     */
    public function polaEfektif(?string $pilihan): string
    {
        return $this->boleh_ganti_pola !== false && in_array($pilihan, self::POLA, true) ? $pilihan : $this->pola_kerja;
    }

    /** Shift menurut jam: Shift 1 06:30–14:30, Shift 2 14:30–22:30, Shift 3 22:30–06:30. */
    public static function shiftMenurutJam(\DateTimeInterface $waktu): string
    {
        $jam = $waktu->format('H:i');

        return match (true) {
            $jam >= '06:30' && $jam < '14:30' => 'Shift 1',
            $jam >= '14:30' && $jam < '22:30' => 'Shift 2',
            default                           => 'Shift 3',
        };
    }

    /**
     * Label shift untuk satu pengambilan — otomatis, teknisi tidak perlu memilih:
     * karyawan shift -> Shift 1/2/3 menurut jam; non-shift -> "Non-shift", atau
     * "Non-shift (di luar jam kerja)" bila di luar 08:00–17:00 / hari Minggu (indikasi lembur).
     */
    public static function labelShift(?self $karyawan, \DateTimeInterface $waktu, ?string $pola = null): string
    {
        if (($pola ?? $karyawan?->pola_kerja) !== 'Non-shift') {
            return self::shiftMenurutJam($waktu);
        }

        $jam = $waktu->format('H:i');
        $diLuar = $waktu->format('N') === '7' || $jam < self::JAM_NONSHIFT[0] || $jam >= self::JAM_NONSHIFT[1];

        return $diLuar ? self::LABEL_DI_LUAR_JAM : 'Non-shift';
    }

    /** Nilai pola kerja dari Excel / form: 1, 2, 3, "Shift 2", "shift" -> Shift; "non shift", "NS" -> Non-shift. */
    public static function normalPola(?string $nilai): ?string
    {
        $v = strtolower(preg_replace('/[\s_-]+/', '', (string) $nilai));

        return match (true) {
            $v === ''                                              => null,
            in_array($v, ['nonshift', 'ns', 'normal', 'office'], true) => 'Non-shift',
            (bool) preg_match('/^(shift)?[123]?$/', $v)            => 'Shift',
            default                                                => null,
        };
    }

    public function getSisaPercobaanAttribute(): int
    {
        return max(0, self::MAKS_PIN_SALAH - $this->pin_gagal);
    }
}
