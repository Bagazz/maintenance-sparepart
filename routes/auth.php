<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;

/*
| Aplikasi ini hanya punya 2 akun tetap (admin & kiosk) yang password-nya dipatenkan
| lewat .env + UserSeeder. Karena itu fitur bawaan Breeze yang bisa membuat akun baru
| atau mengganti password SENGAJA ditutup: register, lupa/reset password, ganti password,
| dan halaman profil (lihat routes/web.php). Mengganti password hanya lewat:
|   ubah ADMIN_PASSWORD / USER_PASSWORD di .env  ->  php artisan db:seed --class=UserSeeder
*/

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])
                ->name('login');

    // Maks. 5 percobaan per menit per email+IP (lihat LoginRequest) + batas kasar per IP.
    Route::post('login', [AuthenticatedSessionController::class, 'store'])
                ->middleware('throttle:20,1');
});

Route::middleware('auth')->group(function () {
    Route::get('verify-email', EmailVerificationPromptController::class)
                ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
                ->middleware(['signed', 'throttle:6,1'])
                ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
                ->middleware('throttle:6,1')
                ->name('verification.send');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
                ->name('logout');
});
