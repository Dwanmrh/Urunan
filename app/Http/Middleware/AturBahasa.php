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
        $bahasaSesi = $request->hasSession() ? $request->session()->get('bahasa') : null;
        $bahasa = $request->user()?->locale ?? $bahasaSesi;

        // Pilihan bahasa sebelum login disimpan ke akun, supaya email dan notifikasi ikut bahasa itu.
        if ($request->user() !== null && $request->user()->locale === null && is_string($bahasaSesi) && array_key_exists($bahasaSesi, config('urunan.bahasa'))) {
            $request->user()->forceFill(['locale' => $bahasaSesi])->save();
        }

        if (is_string($bahasa) && array_key_exists($bahasa, config('urunan.bahasa'))) {
            app()->setLocale($bahasa);
        }

        return $next($request);
    }
}
