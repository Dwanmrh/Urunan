<?php

namespace App\Notifications;

class PengingatBayar extends NotifikasiUrunan
{
    public const JUDUL = 'Pengingat bayar';

    public const PESAN = 'Bagianmu di :tagihan sebesar :jumlah belum lunas. Transfer ke :nama lalu kirim buktinya, ya.';

    protected function param(): array
    {
        return [
            'nama' => $this->tagihan->pembayar->nama,
            'tagihan' => $this->tagihan->judul,
        ];
    }
}
