<?php

namespace App\Notifications;

use App\Models\Tagihan;

class BuktiBayarMasuk extends NotifikasiUrunan
{
    public const JUDUL = 'Bukti bayar masuk';

    public const PESAN = ':nama mengirim bukti :jumlah untuk :tagihan. Cek lalu konfirmasi.';

    /**
     * Create a new notification instance.
     */
    public function __construct(Tagihan $tagihan, int $jumlah, public string $namaPengirim)
    {
        parent::__construct($tagihan, $jumlah);
    }

    protected function param(): array
    {
        return [
            'nama' => $this->namaPengirim,
            'tagihan' => $this->tagihan->judul,
        ];
    }
}
