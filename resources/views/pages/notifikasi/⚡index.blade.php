<?php

use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Notifikasi')] class extends Component {
    /**
     * Lima puluh notifikasi terbaru milik user.
     *
     * @return Collection<int, DatabaseNotification>
     */
    #[Computed]
    public function daftar(): Collection
    {
        return Auth::user()->notifications()->latest()->limit(50)->get();
    }

    /**
     * Tandai notifikasi sudah dibaca lalu buka halaman terkait.
     */
    public function buka(string $id): void
    {
        $notifikasi = Auth::user()->notifications()->findOrFail($id);
        $notifikasi->markAsRead();

        $url = $notifikasi->data['url'] ?? null;

        if (! is_string($url) || ! str_starts_with($url, '/') || str_starts_with($url, '//')) {
            unset($this->daftar);

            return;
        }

        if (preg_match('#^/grup/(\d+)/tagihan/(\d+)#', $url, $cocok) === 1
            && ! \App\Models\Tagihan::whereKey($cocok[2])->where('grup_id', $cocok[1])->exists()) {
            unset($this->daftar);

            \Flux\Flux::toast(variant: 'warning', text: __('Tagihan ini sudah dihapus.'));

            return;
        }

        $this->redirect($url, navigate: true);

        unset($this->daftar);
    }

    /**
     * Tandai semua notifikasi sudah dibaca.
     */
    public function tandaiSemuaDibaca(): void
    {
        Auth::user()->unreadNotifications()->update(['read_at' => now()]);

        unset($this->daftar);
    }
}; ?>

<section class="w-full max-w-2xl" wire:poll.30s>
    <x-tur nama="notifikasi" :langkah="[
        ['judul' => __('Kotak notifikasi'), 'isi' => __('Semua kabar soal tagihan dan bukti bayar terkumpul di sini. Ketuk notifikasi untuk membuka tagihannya.')],
        ['judul' => __('Mau kabar lewat Telegram?'), 'isi' => __('Hubungkan Telegram di Pengaturan supaya notifikasi juga masuk ke HP-mu.')],
    ]" />

    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <flux:heading size="xl" level="1">{{ __('Notifikasi') }}</flux:heading>

        @if ($this->daftar->contains(fn ($notifikasi) => $notifikasi->read_at === null))
            <flux:button wire:click="tandaiSemuaDibaca" size="sm" icon="check" data-test="tandai-semua-dibaca">{{ __('Tandai semua dibaca') }}</flux:button>
        @endif
    </div>

    @if ($this->daftar->isEmpty())
        <flux:card>
            <flux:text>{{ __('Belum ada notifikasi. Kabar soal tagihan dan bukti bayar akan muncul di sini.') }}</flux:text>
        </flux:card>
    @else
        <flux:card class="p-0">
            <ul class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @foreach ($this->daftar as $notifikasi)
                    @php($teks = \App\Notifications\NotifikasiUrunan::teksUntuk($notifikasi))
                    <li wire:key="notifikasi-{{ $notifikasi->id }}">
                        <button
                            type="button"
                            wire:click="buka('{{ $notifikasi->id }}')"
                            class="flex w-full items-start gap-3 px-4 py-3 text-start hover:bg-zinc-50 dark:hover:bg-zinc-700"
                        >
                            <span @class([
                                'mt-2 size-2 shrink-0 rounded-full',
                                'bg-sky-500' => $notifikasi->read_at === null,
                                'bg-transparent' => $notifikasi->read_at !== null,
                            ])></span>

                            <span class="flex-1">
                                <flux:heading @class(['font-semibold' => $notifikasi->read_at === null])>{{ $teks['judul'] }}</flux:heading>
                                <flux:text>{{ $teks['pesan'] }}</flux:text>
                                <flux:text size="sm" class="mt-1">{{ $notifikasi->created_at->locale(app()->getLocale())->diffForHumans() }}</flux:text>
                            </span>

                            @if ($notifikasi->read_at === null)
                                <span class="sr-only">{{ __('Belum dibaca') }}</span>
                            @endif
                        </button>
                    </li>
                @endforeach
            </ul>
        </flux:card>
    @endif
</section>
