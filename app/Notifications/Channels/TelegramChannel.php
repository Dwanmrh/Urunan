<?php

namespace App\Notifications\Channels;

use App\Actions\Telegram\KirimPesanTelegram;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Notifications\Notification;
use RuntimeException;

class TelegramChannel
{
    /**
     * Kirim notifikasi ke chat Telegram milik penerima.
     *
     * Pesan error disusun ulang tanpa alamat lengkap Bot API supaya token tidak ikut tercatat di log.
     */
    public function send(object $notifiable, Notification $notification): void
    {
        $chatId = $notifiable->routeNotificationFor('telegram', $notification);

        if (blank($chatId) || ! method_exists($notification, 'toTelegram') || ! KirimPesanTelegram::aktif()) {
            return;
        }

        try {
            $respons = KirimPesanTelegram::kirim((string) $chatId, $notification->toTelegram($notifiable));
        } catch (ConnectionException $e) {
            throw new RuntimeException('Tidak bisa menghubungi Telegram: '.KirimPesanTelegram::samarkan($e->getMessage()));
        }

        if ($respons->status() === 403 && $notifiable instanceof User) {
            $notifiable->forceFill(['telegram_chat_id' => null])->save();

            return;
        }

        if ($respons->failed()) {
            throw new RuntimeException('Telegram menolak pesan (status '.$respons->status().'): '.$respons->json('description', '-'));
        }
    }
}
