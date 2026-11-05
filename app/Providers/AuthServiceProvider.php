<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        //
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        // "kelola" = boleh mengubah data (import, opname, padanan mesin, rekonsiliasi, dll). Viewer tidak.
        Gate::define('kelola', fn (User $user) => $user->role === 'admin');
        // Upload Excel ERP: admin, atau manager (viewer) yang diberi hak boleh_impor. Hanya itu yang bertambah.
        Gate::define('impor', fn (User $user) => $user->role === 'admin' || ($user->role === 'viewer' && $user->boleh_impor));

        // "ambil" = boleh mencatat pengambilan / retur barang (admin & kiosk teknisi).
        Gate::define('ambil', fn (User $user) => in_array($user->role, ['admin', 'user'], true));
    }
}
