<?php

namespace App\Http\Controllers;

use App\Models\Grup;
use App\Models\Pembayaran;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BuktiPembayaranController extends Controller
{
    /**
     * Tampilkan foto bukti transfer, hanya untuk anggota grup yang bersangkutan.
     */
    public function __invoke(Request $request, Grup $grup, Pembayaran $pembayaran): StreamedResponse
    {
        abort_unless($pembayaran->grup_id === $grup->id && $grup->punyaAnggota($request->user()), 404);

        abort_if($pembayaran->bukti_path === null || ! Storage::disk('local')->exists($pembayaran->bukti_path), 404);

        return Storage::disk('local')->response($pembayaran->bukti_path);
    }
}
