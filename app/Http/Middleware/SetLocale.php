<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;

/**
 * Bahasa tampilan. Default (dipatenkan) = English; pengguna bisa memilih Bahasa Indonesia.
 * Pilihan disimpan di session + cookie 1 tahun, jadi tetap diingat di perangkat (kiosk/PC admin)
 * walaupun logout atau browser ditutup.
 */
class SetLocale
{
    public const DIDUKUNG = ['en', 'id'];
    public const DEFAULT = 'en';

    public function handle(Request $request, Closure $next)
    {
        $locale = $request->session()->get('locale', $request->cookie('locale'));

        if (!in_array($locale, self::DIDUKUNG, true)) {
            $locale = self::DEFAULT;
        }

        App::setLocale($locale);
        Carbon::setLocale($locale);

        return $next($request);
    }
}
