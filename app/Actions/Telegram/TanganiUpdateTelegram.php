<?php

namespace App\Actions\Telegram;

use App\Models\User;

class TanganiUpdateTelegram
{
    /**
     * Proses satu update dari Telegram.
     *
     * Saat ini bot hanya menangani "/start <kode>" untuk menghubungkan akun.
     *
     * @param  array<string, mixed>  $update
     */
    public static function tangani(array $update): void
    {
        $pesan = $update['message'] ?? null;

        if (! is_array($pesan)) {
            return;
        }

        $chatId = (string) data_get($pesan, 'chat.id', '');
        $teks = trim((string) data_get($pesan, 'text', ''));

        if ($chatId === '') {
            return;
        }

        if (! str_starts_with($teks, '/start')) {
            KirimPesanTelegram::kirim($chatId, __('Bot ini hanya mengirim kabar dari Urunan. Buka Urunan untuk melihat dan membayar tagihanmu.'));

            return;
        }

        $token = trim(substr($teks, strlen('/start')));
        $user = $token !== '' ? User::where('telegram_token', $token)->first() : null;

        if ($user === null) {
            KirimPesanTelegram::kirim($chatId, __('Kode penghubung tidak dikenal atau sudah dipakai. Buka Urunan, masuk ke Pengaturan > Telegram, lalu tekan Hubungkan Telegram lagi.'));

            return;
        }

        User::where('telegram_chat_id', $chatId)->whereKeyNot($user->id)->update(['telegram_chat_id' => null]);

        $user->forceFill([
            'telegram_chat_id' => $chatId,
            'telegram_token' => null,
        ])->save();

        KirimPesanTelegram::kirim($chatId, __('Halo :nama, Telegram-mu sudah terhubung dengan Urunan. Kabar tagihan dan bukti bayar akan dikirim ke sini.', ['nama' => $user->name], $user->preferredLocale()));
    }
}
