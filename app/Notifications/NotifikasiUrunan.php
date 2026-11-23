<?php

namespace App\Notifications;

use App\Actions\Telegram\KirimPesanTelegram;
use App\Models\Tagihan;
use App\Notifications\Channels\TelegramChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

abstract class NotifikasiUrunan extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public Tagihan $tagihan, public int $jumlah) {}

    /**
     * Get the notification's delivery channels.
     *
     * Notifikasi di aplikasi selalu dikirim. Telegram hanya kalau penerima sudah menghubungkannya.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $saluran = ['database'];

        if (filled($notifiable->telegram_chat_id ?? null) && KirimPesanTelegram::aktif()) {
            $saluran[] = TelegramChannel::class;
        }

        return $saluran;
    }

    /**
     * Notifikasi di aplikasi langsung disimpan, sedangkan Telegram dikirim lewat antrean
     * supaya halaman tidak menunggu respons Telegram.
     *
     * @return array<string, string>
     */
    public function viaConnections(): array
    {
        return [
            'database' => 'sync',
        ];
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
            'url' => $this->urlTagihan(absolute: false),
        ];
    }

    /**
     * Isi pesan Telegram.
     */
    public function toTelegram(object $notifiable): string
    {
        return $this->judul()."\n\n".$this->pesan()."\n\nBuka: ".$this->urlTagihan(absolute: true);
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

    /**
     * Alamat halaman tagihan yang dibahas notifikasi ini.
     */
    protected function urlTagihan(bool $absolute): string
    {
        return route('tagihan.show', ['grup' => $this->tagihan->grup_id, 'tagihan' => $this->tagihan->id], absolute: $absolute);
    }
}
