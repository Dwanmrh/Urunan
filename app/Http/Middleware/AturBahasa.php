<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AturBahasa
{
    /**
     * Pakai bahasa pilihan user. Urutannya: pengaturan akun, pilihan di sesi, lalu bawaan aplikasi.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $bahasa = $request->user()?->locale ?? ($request->hasSession() ? $request->session()->get('bahasa') : null);

        if (is_string($bahasa) && array_key_exists($bahasa, config('urunan.bahasa'))) {
            app()->setLocale($bahasa);
        }

        return $next($request);
    }
}
