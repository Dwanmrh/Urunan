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
        return __('Bukti bayar masuk');
    }

    protected function pesan(): string
    {
        return __(':nama mengirim bukti :jumlah untuk :tagihan. Cek lalu konfirmasi.', [
            'nama' => $this->namaPengirim,
            'jumlah' => $this->rupiah(),
            'tagihan' => $this->tagihan->judul,
        ]);
    }
}
