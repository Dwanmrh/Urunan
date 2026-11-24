<?php

namespace App\Notifications;

class TagihanBaru extends NotifikasiUrunan
{
    public const JUDUL = 'Tagihan baru';

    public const PESAN = ':nama mencatat :tagihan. Bagianmu :jumlah.';

    protected function param(): array
    {
        return [
            'nama' => $this->tagihan->pembayar->nama,
            'tagihan' => $this->tagihan->judul,
        ];
    }
}
