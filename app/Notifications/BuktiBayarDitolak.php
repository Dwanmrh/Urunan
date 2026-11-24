<?php

namespace App\Notifications;

class BuktiBayarDitolak extends NotifikasiUrunan
{
    protected function judul(): string
    {
        return __('Bukti bayar ditolak');
    }

    protected function pesan(): string
    {
        return __(':nama menolak bukti :jumlah untuk :tagihan. Cek lagi lalu kirim ulang.', [
            'nama' => $this->tagihan->pembayar->nama,
            'jumlah' => $this->rupiah(),
            'tagihan' => $this->tagihan->judul,
        ]);
    }
}
