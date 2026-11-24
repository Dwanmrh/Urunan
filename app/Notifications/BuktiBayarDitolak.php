<?php

namespace App\Notifications;

class BuktiBayarDitolak extends NotifikasiUrunan
{
    public const JUDUL = 'Bukti bayar ditolak';

    public const PESAN = ':nama menolak bukti :jumlah untuk :tagihan. Cek lagi lalu kirim ulang.';

    protected function param(): array
    {
        return [
            'nama' => $this->tagihan->pembayar->nama,
            'tagihan' => $this->tagihan->judul,
        ];
    }
}
