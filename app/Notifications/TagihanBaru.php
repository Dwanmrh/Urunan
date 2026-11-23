<?php

namespace App\Notifications;

class TagihanBaru extends NotifikasiUrunan
{
    protected function judul(): string
    {
        return 'Tagihan baru';
    }

    protected function pesan(): string
    {
        return "{$this->tagihan->pembayar->nama} mencatat {$this->tagihan->judul}. Bagianmu {$this->rupiah()}.";
    }
}
