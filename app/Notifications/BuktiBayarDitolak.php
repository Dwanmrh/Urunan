<?php

namespace App\Notifications;

class BuktiBayarDitolak extends NotifikasiUrunan
{
    protected function judul(): string
    {
        return 'Bukti bayar ditolak';
    }

    protected function pesan(): string
    {
        return "{$this->tagihan->pembayar->nama} menolak bukti {$this->rupiah()} untuk {$this->tagihan->judul}. Cek lagi lalu kirim ulang.";
    }
}
