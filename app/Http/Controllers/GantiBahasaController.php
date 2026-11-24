<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GantiBahasaController extends Controller
{
    /**
     * Simpan pilihan bahasa di sesi dan di akun (kalau sedang login), lalu kembali ke halaman sebelumnya.
     */
    public function __invoke(Request $request, string $bahasa): RedirectResponse
    {
        abort_unless(array_key_exists($bahasa, config('urunan.bahasa')), 404);

        $request->session()->put('bahasa', $bahasa);
        $request->user()?->forceFill(['locale' => $bahasa])->save();

        return redirect()->back(fallback: route('home'));
    }
}
