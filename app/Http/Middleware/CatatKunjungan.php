<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class CatatKunjungan
{
    /**
     * Catat satu kunjungan unik per pengunjung per hari.
     *
     * Yang disimpan hanya sidik acak dari ID session, bukan alamat IP atau data pribadi.
     * Permintaan Livewire, JSON, dan selain GET tidak dihitung.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->isMethod('GET') || $request->hasHeader('X-Livewire') || $request->expectsJson() || ! $request->hasSession()) {
            return $response;
        }

        $session = $request->session();
        $tanda = now()->toDateString().'|'.($request->user()?->id ?? 'tamu');

        if ($session->get('kunjungan_tercatat') === $tanda) {
            return $response;
        }

        DB::table('kunjungan')->insertOrIgnore([
            'tanggal' => now()->toDateString(),
            'pengunjung' => hash_hmac('sha256', $session->getId(), (string) config('app.key')),
            'user_id' => $request->user()?->id,
            'created_at' => now(),
        ]);

        $session->put('kunjungan_tercatat', $tanda);

        return $response;
    }
}
