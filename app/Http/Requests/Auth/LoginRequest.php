<?php

namespace App\Http\Requests\Auth;

use App\Models\Karyawan;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Satu halaman login untuk dua jenis pengguna:
 *  - admin / viewer  : email + password akun
 *  - teknisi         : NAMA karyawan terdaftar (huruf besar/kecil bebas) + password yang diatur Pak Nardi.
 *    Teknisi masuk memakai akun kiosk bersama, tetapi setiap pengambilan tercatat atas namanya sendiri.
 */
class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\Rule|array|string>
     */
    public function rules(): array
    {
        return [
            // Kolom tetap bernama "email" (dipakai form & throttle), isinya email ATAU nama karyawan.
            'email' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return ['email.required' => __('Enter your email or employee name.')];
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (!str_contains($this->string('email'), '@')) {
            $this->authenticateTeknisi();
            RateLimiter::clear($this->throttleKey());
            return;
        }

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /** Login teknisi: nama karyawan + password. Salah 5x berturut-turut -> akun terkunci sampai dibuka admin. */
    private function authenticateTeknisi(): void
    {
        $karyawan = Karyawan::cariDariNama($this->string('email'));
        $gagal = fn (string $pesan) => throw ValidationException::withMessages(['email' => $pesan]);

        if (!$karyawan || !$karyawan->aktif) {
            RateLimiter::hit($this->throttleKey());
            $gagal(__('Name or password is incorrect. Use the name registered by the spare part head.'));
        }
        if ($karyawan->terkunci) {
            $gagal(__('The account of :nama is locked after too many wrong passwords. Ask the spare part head to unlock it.', ['nama' => $karyawan->nama]));
        }
        if (!$karyawan->punya_pin) {
            $gagal(__(':nama has no password yet. Ask the spare part head to set one.', ['nama' => $karyawan->nama]));
        }

        if (!$karyawan->cekPin((string) $this->input('password'))) {
            RateLimiter::hit($this->throttleKey());
            $karyawan->refresh();
            $gagal($karyawan->terkunci
                ? __('Wrong password :n times — the account of :nama is now locked. Ask the spare part head to unlock it.', ['n' => Karyawan::MAKS_PIN_SALAH, 'nama' => $karyawan->nama])
                : __('Name or password is incorrect. :n attempts left before the account is locked.', ['n' => $karyawan->sisa_percobaan]));
        }

        $kiosk = User::where('role', 'user')->orderBy('id')->first();
        if (!$kiosk) {
            $gagal(__('The kiosk account is not set up. Contact the spare part head.'));
        }

        // "Ingat saya" sengaja tidak dipakai: PC kiosk dipakai bergantian.
        Auth::login($kiosk);
        session([
            'karyawan_id'    => $karyawan->id,
            'peminjam_nama'  => $karyawan->nama,
            'karyawan_sejak' => now()->toIso8601String(),
        ]);
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
