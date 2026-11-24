<?php

namespace App\Notifications;

class PembayaranDikonfirmasi extends NotifikasiUrunan
{
    protected function judul(): string
    {
        return __('Pembayaran diterima');
    }

    protected function pesan(): string
    {
        return __('Pembayaranmu :jumlah untuk :tagihan sudah dikonfirmasi :nama. Status: lunas.', [
            'jumlah' => $this->rupiah(),
            'tagihan' => $this->tagihan->judul,
            'nama' => $this->tagihan->pembayar->nama,
        ]);
    }
}
