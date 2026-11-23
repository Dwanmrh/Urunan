<?php

namespace App\Notifications;

use App\Models\Tagihan;

class BuktiBayarMasuk extends NotifikasiUrunan
{
    /**
     * Create a new notification instance.
     */
    public function __construct(Tagihan $tagihan, int $jumlah, public string $namaPengirim)
    {
        parent::__construct($tagihan, $jumlah);
    }

    protected function judul(): string
    {
        return 'Bukti bayar masuk';
    }

    protected function pesan(): string
    {
        return "{$this->namaPengirim} mengirim bukti {$this->rupiah()} untuk {$this->tagihan->judul}. Cek lalu konfirmasi.";
    }
}
