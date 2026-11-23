<?php

namespace App\Notifications;

class PembayaranDikonfirmasi extends NotifikasiUrunan
{
    protected function judul(): string
    {
        return 'Pembayaran diterima';
    }

    protected function pesan(): string
    {
        return "Pembayaranmu {$this->rupiah()} untuk {$this->tagihan->judul} sudah dikonfirmasi {$this->tagihan->pembayar->nama}. Status: lunas.";
    }
}
