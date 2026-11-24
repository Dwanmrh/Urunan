<?php

namespace App\Support;

use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

class BatasAksi
{
    /**
     * Batasi aksi yang bisa disalahgunakan (spam grup, tagihan, unggahan) per user per menit.
     *
     * Mengembalikan false dan menampilkan peringatan kalau batasnya sudah tercapai.
     */
    public static function boleh(string $aksi, int $maksimalPerMenit): bool
    {
        $kunci = $aksi.':'.(Auth::id() ?? request()->ip());

        if (RateLimiter::tooManyAttempts($kunci, $maksimalPerMenit)) {
            Flux::toast(variant: 'warning', text: __('Terlalu banyak percobaan. Tunggu :detik detik lalu coba lagi.', [
                'detik' => RateLimiter::availableIn($kunci),
            ]));

            return false;
        }

        RateLimiter::hit($kunci, 60);

        return true;
    }
}
