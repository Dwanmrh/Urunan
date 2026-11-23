<?php

namespace App\Actions\Telegram;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class KirimPesanTelegram
{
    /**
     * Apakah bot Telegram sudah diatur di .env.
     */
    public static function aktif(): bool
    {
        return filled(config('services.telegram.token')) && filled(config('services.telegram.username'));
    }

    /**
     * Kirim pesan teks biasa ke satu chat.
     */
    public static function kirim(string $chatId, string $teks): Response
    {
        return self::http()->timeout(10)->post(self::url('sendMessage'), [
            'chat_id' => $chatId,
            'text' => $teks,
            'link_preview_options' => ['is_disabled' => true],
        ]);
    }

    /**
     * Klien HTTP untuk Bot API.
     *
     * Dipaksa lewat IPv4 karena jalur IPv6 ke Telegram tidak selalu tersedia di jaringan rumah.
     */
    public static function http(): PendingRequest
    {
        return Http::withOptions(['force_ip_resolve' => 'v4']);
    }

    /**
     * Alamat lengkap sebuah method Bot API.
     */
    public static function url(string $method): string
    {
        return 'https://api.telegram.org/bot'.config('services.telegram.token').'/'.$method;
    }

    /**
     * Sembunyikan token bot dari teks, misalnya pesan error yang memuat alamat lengkap Bot API.
     */
    public static function samarkan(string $teks): string
    {
        $token = (string) config('services.telegram.token');

        return $token === '' ? $teks : str_replace($token, '[TOKEN]', $teks);
    }

    /**
     * Link yang membuka bot dan langsung mengirim kode penghubung saat Start ditekan.
     */
    public static function linkStart(string $token): string
    {
        return 'https://t.me/'.config('services.telegram.username').'?start='.$token;
    }
}
