<?php

use App\Actions\Telegram\KirimPesanTelegram;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Telegram')] class extends Component {
    /**
     * Apakah bot Telegram sudah diatur di .env.
     */
    #[Computed]
    public function aktif(): bool
    {
        return KirimPesanTelegram::aktif();
    }

    /**
     * Buat kode penghubung lalu buka bot di Telegram.
     */
    public function hubungkan(): void
    {
        abort_unless($this->aktif, 404);

        $token = Str::random(32);

        Auth::user()->forceFill(['telegram_token' => $token])->save();

        $this->redirect(KirimPesanTelegram::linkStart($token));
    }

    /**
     * Kirim pesan percobaan ke Telegram user.
     */
    public function kirimUji(): void
    {
        $user = Auth::user();

        if (! $this->aktif || blank($user->telegram_chat_id)) {
            return;
        }

        $berhasil = rescue(
            fn (): bool => KirimPesanTelegram::kirim($user->telegram_chat_id, __('Pesan uji dari Urunan. Kalau ini sampai, notifikasi Telegram-mu sudah jalan.'))->successful(),
            false,
            report: false,
        );

        $berhasil
            ? Flux::toast(variant: 'success', text: __('Pesan uji terkirim. Cek Telegram-mu.'))
            : Flux::toast(variant: 'danger', text: __('Pesan uji gagal terkirim. Coba hubungkan ulang.'));
    }

    /**
     * Berhenti menerima notifikasi lewat Telegram.
     */
    public function putuskan(): void
    {
        Auth::user()->forceFill([
            'telegram_chat_id' => null,
            'telegram_token' => null,
        ])->save();

        Flux::toast(text: __('Telegram diputuskan.'));
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">Telegram</flux:heading>

    <x-pages::settings.layout heading="{{ __('Telegram') }}" subheading="{{ __('Terima kabar tagihan dan bukti bayar langsung di Telegram') }}">
        @if (! $this->aktif)
            <flux:callout icon="information-circle" variant="warning" heading="{{ __('Bot Telegram belum diatur') }}" text="{{ __('Isi TELEGRAM_BOT_TOKEN dan TELEGRAM_BOT_USERNAME di file .env, lalu muat ulang halaman ini.') }}" />
        @elseif (filled(auth()->user()->telegram_chat_id))
            <div class="space-y-6">
                <div class="flex items-center gap-3">
                    <flux:badge color="green" icon="check-circle">{{ __('Terhubung') }}</flux:badge>
                    <flux:text>{{ __('Notifikasi Urunan juga dikirim ke Telegram-mu.') }}</flux:text>
                </div>

                <div class="flex flex-wrap gap-2">
                    <flux:button wire:click="kirimUji" icon="paper-airplane" data-test="kirim-uji-telegram">{{ __('Kirim pesan uji') }}</flux:button>
                    <flux:button
                        x-on:click="$dispatch('konfirmasi', {
                            judul: {{ \Illuminate\Support\Js::from(__('Putuskan Telegram?')) }},
                            pesan: {{ \Illuminate\Support\Js::from(__('Kamu berhenti menerima notifikasi Urunan di Telegram. Bisa dihubungkan lagi kapan saja.')) }},
                            tombol: {{ \Illuminate\Support\Js::from(__('Putuskan')) }},
                            bahaya: true,
                            aksi: () => $wire.putuskan(),
                        })"
                        variant="danger"
                        data-test="putuskan-telegram"
                    >{{ __('Putuskan') }}</flux:button>
                </div>
            </div>
        @else
            <div class="space-y-6" @if (filled(auth()->user()->telegram_token)) wire:poll.5s @endif>
                <flux:text>
                    {!! __('Tekan tombol di bawah. Telegram akan terbuka di bot Urunan, lalu tekan <strong>Start</strong> sekali. Setelah itu kembali ke sini, statusnya berubah sendiri.') !!}
                </flux:text>

                <flux:button wire:click="hubungkan" variant="primary" icon="paper-airplane" data-test="hubungkan-telegram">{{ __('Hubungkan Telegram') }}</flux:button>

                @if (filled(auth()->user()->telegram_token))
                    <flux:text size="sm">{{ __('Menunggu kamu menekan Start di Telegram...') }}</flux:text>
                @endif
            </div>
        @endif
    </x-pages::settings.layout>
</section>
