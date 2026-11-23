<?php

namespace App\Notifications;

use App\Models\Tagihan;
use Illuminate\Notifications\Notification;

abstract class NotifikasiUrunan extends Notification
{
    /**
     * Create a new notification instance.
     */
    public function __construct(public Tagihan $tagihan, public int $jumlah) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array{judul: string, pesan: string, url: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'judul' => $this->judul(),
            'pesan' => $this->pesan(),
            'url' => route('tagihan.show', ['grup' => $this->tagihan->grup_id, 'tagihan' => $this->tagihan->id], absolute: false),
        ];
    }

    /**
     * Judul singkat notifikasi.
     */
    abstract protected function judul(): string;

    /**
     * Isi notifikasi.
     */
    abstract protected function pesan(): string;

    /**
     * Nominal dengan titik ribuan, misalnya "Rp 50.000".
     */
    protected function rupiah(): string
    {
        return 'Rp '.number_format($this->jumlah, 0, ',', '.');
    }
}
