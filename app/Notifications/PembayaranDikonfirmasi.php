<?php

namespace App\Notifications;

class PembayaranDikonfirmasi extends NotifikasiUrunan
{
    public const JUDUL = 'Pembayaran diterima';

    public const PESAN = 'Pembayaranmu :jumlah untuk :tagihan sudah dikonfirmasi :nama. Status: lunas.';

    protected function param(): array
    {
        return [
            'nama' => $this->tagihan->pembayar->nama,
            'tagihan' => $this->tagihan->judul,
        ];
    }
}
