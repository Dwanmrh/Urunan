<?php

namespace App\Notifications;

class TagihanBaru extends NotifikasiUrunan
{
    protected function judul(): string
    {
        return __('Tagihan baru');
    }

    protected function pesan(): string
    {
        return __(':nama mencatat :tagihan. Bagianmu :jumlah.', [
            'nama' => $this->tagihan->pembayar->nama,
            'tagihan' => $this->tagihan->judul,
            'jumlah' => $this->rupiah(),
        ]);
    }
}
