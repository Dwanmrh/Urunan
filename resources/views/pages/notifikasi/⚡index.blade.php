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

    /**
     * Hapus satu notifikasi milik user.
     */
    public function hapus(string $id): void
    {
        Auth::user()->notifications()->whereKey($id)->delete();

        unset($this->daftar);

        \Flux\Flux::toast(text: __('Notifikasi dihapus.'));
    }

    /**
     * Hapus semua notifikasi yang sudah dibaca.
     */
    public function hapusYangSudahDibaca(): void
    {
        $jumlah = Auth::user()->readNotifications()->delete();

        unset($this->daftar);

        \Flux\Flux::toast(variant: 'success', text: trans_choice(':jumlah notifikasi dihapus.', $jumlah, ['jumlah' => $jumlah]));
    }
}; ?>

<section class="w-full max-w-2xl" wire:poll.30s>
    <x-tur nama="notifikasi" :langkah="[
        ['judul' => __('Kotak notifikasi'), 'isi' => __('Semua kabar soal tagihan dan bukti bayar terkumpul di sini. Ketuk notifikasi untuk membuka tagihannya.')],
        ['judul' => __('Mau kabar lewat Telegram?'), 'isi' => __('Hubungkan Telegram di Pengaturan supaya notifikasi juga masuk ke HP-mu.')],
    ]" />

    <x-kepala-halaman :judul="__('Notifikasi')" :sub="__('Kabar tagihan, bukti bayar, dan pengingat.')">
        <x-slot:aksi>
            @if ($this->daftar->contains(fn ($notifikasi) => $notifikasi->read_at === null))
                <flux:button wire:click="tandaiSemuaDibaca" size="sm" icon="check" data-test="tandai-semua-dibaca">{{ __('Tandai semua dibaca') }}</flux:button>
            @endif

            @if ($this->daftar->contains(fn ($notifikasi) => $notifikasi->read_at !== null))
                <flux:button
                    size="sm"
                    icon="trash"
                    variant="ghost"
                    data-test="hapus-sudah-dibaca"
                    x-on:click="$dispatch('konfirmasi', {
                        judul: {{ \Illuminate\Support\Js::from(__('Hapus notifikasi yang sudah dibaca?')) }},
                        pesan: {{ \Illuminate\Support\Js::from(__('Notifikasi yang belum dibaca tetap disimpan. Tagihan dan pembayaranmu tidak ikut terhapus.')) }},
                        tombol: {{ \Illuminate\Support\Js::from(__('Hapus')) }},
                        bahaya: true,
                        aksi: () => $wire.hapusYangSudahDibaca(),
                    })"
                >{{ __('Hapus yang sudah dibaca') }}</flux:button>
            @endif
        </x-slot:aksi>
    </x-kepala-halaman>

    @if ($this->daftar->isEmpty())
        <div class="rounded-3xl border border-dashed border-zinc-300 px-6 py-14 text-center dark:border-zinc-700">
            <span class="text-5xl" aria-hidden="true">🔔</span>
            <p class="mt-4 font-semibold text-zinc-900 dark:text-white">{{ __('Masih sepi di sini') }}</p>
            <p class="mx-auto mt-1 max-w-sm text-sm text-zinc-600 dark:text-zinc-400">{{ __('Belum ada notifikasi. Kabar soal tagihan dan bukti bayar akan muncul di sini.') }}</p>
        </div>
    @else
        @php
            $ikon = [
                \App\Notifications\TagihanBaru::class => ['receipt-percent', 'bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-300'],
                \App\Notifications\BuktiBayarMasuk::class => ['photo', 'bg-sky-50 text-sky-600 dark:bg-sky-500/10 dark:text-sky-300'],
                \App\Notifications\PembayaranDikonfirmasi::class => ['check-badge', 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-300'],
                \App\Notifications\BuktiBayarDitolak::class => ['x-circle', 'bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-300'],
                \App\Notifications\PengingatBayar::class => ['bell-alert', 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-300'],
            ];
            $kelompok = $this->daftar->groupBy(fn ($notifikasi) => $notifikasi->created_at->isToday()
                ? __('Hari ini')
                : ($notifikasi->created_at->isYesterday() ? __('Kemarin') : __('Sebelumnya')));
        @endphp

        <div class="space-y-6">
            @foreach ($kelompok as $label => $isi)
                <div wire:key="kelompok-{{ $loop->index }}">
                    <h2 class="mb-2 px-1 text-xs font-semibold tracking-wide text-zinc-600 dark:text-zinc-400 uppercase">{{ $label }}</h2>
                    <ul class="overflow-hidden rounded-2xl border border-zinc-200 bg-white divide-y divide-zinc-100 dark:border-zinc-700/70 dark:bg-zinc-900 dark:divide-zinc-800">
                        @foreach ($isi as $notifikasi)
                            @php($teks = \App\Notifications\NotifikasiUrunan::teksUntuk($notifikasi))
                            @php([$namaIkon, $warnaIkon] = $ikon[$notifikasi->type] ?? ['bell', 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300'])
                            <li wire:key="notifikasi-{{ $notifikasi->id }}" @class(['group flex items-stretch transition', 'bg-indigo-50/40 dark:bg-indigo-500/5' => $notifikasi->read_at === null])>
                                <button
                                    type="button"
                                    wire:click="buka('{{ $notifikasi->id }}')"
                                    class="flex min-w-0 flex-1 items-start gap-3 py-3.5 ps-4 pe-2 text-start hover:bg-zinc-50 dark:hover:bg-white/5"
                                >
                                    <span class="relative flex size-10 shrink-0 items-center justify-center rounded-xl {{ $warnaIkon }}">
                                        <flux:icon :icon="$namaIkon" variant="mini" class="size-5" />
                                        @if ($notifikasi->read_at === null)
                                            <span class="absolute -top-0.5 -right-0.5 size-2.5 rounded-full bg-indigo-500 ring-2 ring-white dark:ring-zinc-900"></span>
                                        @endif
                                    </span>

                                    <span class="min-w-0 flex-1">
                                        <span @class(['block text-zinc-900 dark:text-white', 'font-semibold' => $notifikasi->read_at === null, 'font-medium' => $notifikasi->read_at !== null])>{{ $teks['judul'] }}</span>
                                        <span class="mt-0.5 block text-sm text-zinc-600 dark:text-zinc-400">{{ $teks['pesan'] }}</span>
                                        <span class="mt-1 block text-xs text-zinc-500 dark:text-zinc-400">{{ $notifikasi->created_at->locale(app()->getLocale())->diffForHumans() }}</span>
                                    </span>

                                    @if ($notifikasi->read_at === null)
                                        <span class="sr-only">{{ __('Belum dibaca') }}</span>
                                    @endif
                                </button>

                                <button
                                    type="button"
                                    wire:click="hapus('{{ $notifikasi->id }}')"
                                    wire:loading.attr="disabled"
                                    class="flex shrink-0 items-center px-3 text-zinc-500 dark:text-zinc-400 hover:bg-red-50 hover:text-red-600 focus-visible:text-red-600 sm:opacity-0 sm:group-hover:opacity-100 sm:focus-visible:opacity-100 dark:hover:bg-red-950/40"
                                    aria-label="{{ __('Hapus notifikasi') }}"
                                    title="{{ __('Hapus notifikasi') }}"
                                    data-test="hapus-notifikasi"
                                >
                                    <flux:icon.trash variant="micro" />
                                </button>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    @endif
</section>
