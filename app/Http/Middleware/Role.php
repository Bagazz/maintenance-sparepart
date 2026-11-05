<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Batasi rute ke satu atau beberapa peran, mis. role:admin atau role:admin,viewer.
 * Peran: admin (kepala sparepart), user (kiosk teknisi), viewer (Presdir / manager — hanya melihat).
 */
class Role
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        if (!in_array(auth()->user()->role, $roles, true)) {
            abort(403, __('This page is for admin / spare part head only. Sign in with the admin account if you need it.'));
        }

        return $next($request);
    }
}
