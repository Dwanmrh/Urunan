<?php

use App\Enums\StatusPembayaran;
use App\Models\AnggotaGrup;
use App\Models\BagianTagihan;
use App\Models\Pembayaran;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    /**
     * Keanggotaan user di semua grup beserta saldonya.
     *
     * @return Collection<int, array{anggota: AnggotaGrup, saldo: int}>
     */
    #[Computed]
    public function keanggotaan(): Collection
    {
        return Auth::user()->anggotaGrup()
            ->with('grup')
            ->latest('id')
            ->get()
            ->map(fn (AnggotaGrup $anggota): array => [
                'anggota' => $anggota,
                'saldo' => $anggota->grup->saldo()[$anggota->id] ?? 0,
            ]);
    }

    /**
     * Total yang harus dibayar dan yang akan diterima dari semua grup.
     *
     * @return array{harusBayar: int, akanTerima: int}
     */
    #[Computed]
    public function ringkasan(): array
    {
        $saldo = $this->keanggotaan->pluck('saldo');

        return [
            'harusBayar' => (int) abs($saldo->filter(fn (int $jumlah): bool => $jumlah < 0)->sum()),
            'akanTerima' => (int) $saldo->filter(fn (int $jumlah): bool => $jumlah > 0)->sum(),
        ];
    }

    /**
     * Tagihan yang bagian user masih belum lunas.
     *
     * @return Collection<int, array{tagihan: \App\Models\Tagihan, sisa: int, menunggu: bool}>
     */
    #[Computed]
    public function perluDibayar(): Collection
    {
        $idAnggota = $this->keanggotaan->pluck('anggota.id')->all();

        return BagianTagihan::query()
            ->whereIn('anggota_grup_id', $idAnggota)
            ->with(['tagihan.grup', 'tagihan.pembayar'])
            ->get()
            ->map(function (BagianTagihan $bagian): array {
                $status = $bagian->tagihan->statusPelunasan()
                    ->first(fn (array $baris): bool => $baris['anggota']->id === $bagian->anggota_grup_id);

                return [
                    'tagihan' => $bagian->tagihan,
                    'sisa' => $status['sisa'] ?? 0,
                    'pembayar' => $status['pembayar'] ?? false,
                    'menunggu' => ($status['menunggu'] ?? null) !== null,
                ];
            })
            ->filter(fn (array $baris): bool => ! $baris['pembayar'] && $baris['sisa'] > 0)
            ->sortByDesc(fn (array $baris): string => $baris['tagihan']->tanggal->toDateString())
            ->values();
    }

    /**
     * Bukti bayar yang menunggu konfirmasi user.
     *
     * @return Collection<int, Pembayaran>
     */
    #[Computed]
    public function menungguKonfirmasi(): Collection
    {
        return Pembayaran::query()
            ->where('status', StatusPembayaran::Menunggu->value)
            ->whereNotNull('tagihan_id')
            ->whereIn('grup_id', $this->keanggotaan->pluck('anggota.grup_id')->all())
            ->with(['dari', 'tagihan.pembayar', 'tagihan.grup'])
            ->latest()
            ->get()
            ->filter(fn (Pembayaran $pembayaran): bool => $pembayaran->tagihan->bisaDikelolaOleh(Auth::user()))
            ->values();
    }

    /**
     * Lima notifikasi terbaru sebagai daftar aktivitas.
     *
     * @return Collection<int, \Illuminate\Notifications\DatabaseNotification>
     */
    #[Computed]
    public function aktivitas(): Collection
    {
        return Auth::user()->notifications()->latest()->limit(5)->get();
    }
}; ?>

<div class="w-full space-y-6">
    <x-tur nama="beranda" :langkah="[
        ['judul' => __('Selamat datang di Urunan!'), 'isi' => __('Urunan membantu patungan bareng teman: catat tagihan, bagi otomatis, dan pantau siapa yang sudah bayar. Yuk, kenalan sebentar.')],
        ['elemen' => '[data-tur=mulai-patungan]', 'judul' => __('Mulai dari sini'), 'isi' => __('Belum punya grup? Buat grup pertama untuk acara atau teman kos, lalu ajak teman bergabung.')],
        ['elemen' => '[data-tur=ringkasan-saldo]', 'judul' => __('Ringkasan saldo'), 'isi' => __('Total yang harus kamu bayar dan yang akan kamu terima dari semua grup.')],
        ['elemen' => '[data-tur=perlu-dibayar]', 'judul' => __('Yang perlu kamu bayar'), 'isi' => __('Semua bagianmu yang belum lunas muncul di sini, lengkap dengan tombol untuk membuka tagihannya.')],
        ['elemen' => '[data-tur=grup-kamu]', 'judul' => __('Grup kamu'), 'isi' => __('Ringkasan grup yang kamu ikuti beserta saldo kamu di tiap grup.')],
        ['elemen' => '[data-tur=menu-grup]', 'judul' => __('Grup patungan'), 'isi' => __('Semua grup ada di menu ini. Buka grup untuk menambah anggota dan mencatat tagihan.'), 'sisi' => 'right'],
        ['elemen' => ['[data-tur=menu-notifikasi]', '[data-tur=menu-notifikasi-hp]'], 'judul' => __('Notifikasi'), 'isi' => __('Kabar tagihan baru, bukti bayar masuk, dan konfirmasi pembayaran muncul di sini.'), 'sisi' => 'right'],
        ['elemen' => '[data-tur=pilih-bahasa]', 'judul' => __('Pilih bahasa'), 'isi' => __('Ganti tampilan ke Bahasa Indonesia atau English kapan saja.'), 'sisi' => 'right'],
        ['elemen' => ['[data-tur=tombol-panduan]', '[data-tur=tombol-panduan-hp]'], 'judul' => __('Butuh panduan lagi?'), 'isi' => __('Tekan tombol ini untuk mengulang panduan di halaman mana pun.'), 'sisi' => 'right'],
    ]" />

    {{-- Sapaan --}}
    <section class="relative overflow-hidden rounded-3xl bg-linear-to-br from-indigo-600 via-indigo-600 to-violet-600 p-6 text-white shadow-lg shadow-indigo-600/20 motion-safe:animate-[muncul_0.5s_ease-out_both] sm:p-8">
        <div aria-hidden="true" class="absolute -top-20 -right-16 size-64 rounded-full bg-white/10 blur-2xl"></div>
        <div aria-hidden="true" class="absolute -bottom-24 left-1/3 size-56 rounded-full bg-fuchsia-400/20 blur-3xl"></div>

        <div class="relative flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-sm font-medium text-white/90">{{ now()->locale(app()->getLocale())->translatedFormat('l, j F Y') }}</p>
                <h1 class="mt-1 text-2xl font-bold tracking-tight sm:text-3xl">{{ __('Halo, :nama', ['nama' => auth()->user()->name]) }}</h1>
                <p class="mt-1 text-white/90">{{ __('Ini ringkasan patunganmu hari ini.') }}</p>
            </div>

            <div class="flex flex-wrap gap-2">
                <a href="{{ route('grup.index') }}" wire:navigate class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-indigo-700 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                    <flux:icon.plus variant="mini" class="size-4" />
                    {{ $this->keanggotaan->isEmpty() ? __('Buat grup pertama') : __('Buat grup') }}
                </a>
                @if ($this->keanggotaan->isNotEmpty())
                    <a href="{{ route('grup.show', $this->keanggotaan->first()['anggota']->grup_id) }}" wire:navigate class="inline-flex items-center gap-2 rounded-xl bg-white/15 px-4 py-2.5 text-sm font-semibold text-white ring-1 ring-white/25 backdrop-blur transition hover:bg-white/25">
                        <flux:icon.receipt-percent variant="mini" class="size-4" />
                        {{ __('Catat tagihan') }}
                    </a>
                @endif
            </div>
        </div>
    </section>

    @if ($this->keanggotaan->isEmpty())
        {{-- Belum punya grup --}}
        <section class="rounded-3xl border border-dashed border-zinc-300 p-6 sm:p-10 dark:border-zinc-700" data-tur="mulai-patungan">
            <div class="mx-auto max-w-2xl text-center">
                <span class="mx-auto flex size-14 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-300">
                    <flux:icon.user-group class="size-7" />
                </span>
                <h2 class="mt-4 text-xl font-semibold text-zinc-900 dark:text-white">{{ __('Mulai patungan pertamamu') }}</h2>
                <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                    {{ __('Buat grup untuk acara atau lingkaran pertemananmu, misalnya "Liburan Pangandaran" atau "Kos bulan ini", lalu catat tagihan dan bagikan link undangannya ke grup WhatsApp.') }}
                </p>
            </div>

            <ol class="mx-auto mt-8 grid max-w-3xl gap-3 sm:grid-cols-3">
                @foreach ([
                    ['ikon' => 'user-group', 'teks' => __('Buat grup')],
                    ['ikon' => 'link', 'teks' => __('Ajak teman')],
                    ['ikon' => 'receipt-percent', 'teks' => __('Catat tagihan')],
                ] as $urutan => $langkah)
                    <li class="flex items-center gap-3 rounded-2xl bg-zinc-50 p-4 dark:bg-white/5">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-white text-indigo-600 shadow-sm dark:bg-zinc-800 dark:text-indigo-300">
                            <flux:icon :icon="$langkah['ikon']" class="size-5" />
                        </span>
                        <span class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ $urutan + 1 }}. {{ $langkah['teks'] }}</span>
                    </li>
                @endforeach
            </ol>

            <div class="mt-8 flex flex-col items-center gap-3">
                <flux:button :href="route('grup.index')" variant="primary" icon="plus" wire:navigate>{{ __('Buat grup pertama') }}</flux:button>
                <p class="text-xs text-zinc-600 dark:text-zinc-400">{{ __('Kalau temanmu sudah mengirim link undangan, cukup buka link itu untuk bergabung.') }}</p>
            </div>
        </section>
    @else
        {{-- Ringkasan saldo --}}
        @php($saldoBersih = $this->ringkasan['akanTerima'] - $this->ringkasan['harusBayar'])
        <section class="grid gap-4 sm:grid-cols-3" data-tur="ringkasan-saldo">
            @foreach ([
                ['label' => __('Harus kamu bayar'), 'jumlah' => $this->ringkasan['harusBayar'], 'ikon' => 'arrow-up-right', 'warna' => 'bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-400', 'teks' => 'text-red-600 dark:text-red-400', 'awalan' => ''],
                ['label' => __('Akan kamu terima'), 'jumlah' => $this->ringkasan['akanTerima'], 'ikon' => 'arrow-down-left', 'warna' => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400', 'teks' => 'text-emerald-600 dark:text-emerald-400', 'awalan' => ''],
                ['label' => __('Saldo bersih'), 'jumlah' => $saldoBersih, 'ikon' => 'scale', 'warna' => 'bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-300', 'teks' => 'text-zinc-900 dark:text-white', 'awalan' => $saldoBersih < 0 ? '−' : ($saldoBersih > 0 ? '+' : '')],
            ] as $urutan => $kartu)
                <div class="rounded-2xl border border-zinc-200 bg-white p-5 transition hover:-translate-y-0.5 hover:shadow-md motion-safe:animate-[muncul_0.5s_ease-out_both] dark:border-zinc-700 dark:bg-zinc-900" style="animation-delay: {{ 80 + $urutan * 80 }}ms">
                    <div class="flex items-center justify-between">
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ $kartu['label'] }}</p>
                        <span class="flex size-8 items-center justify-center rounded-lg {{ $kartu['warna'] }}">
                            <flux:icon :icon="$kartu['ikon']" variant="mini" class="size-4" />
                        </span>
                    </div>
                    <p class="mt-3 text-2xl font-bold tabular-nums {{ $kartu['teks'] }}">{{ $kartu['awalan'] }}<x-rupiah :jumlah="$kartu['jumlah']" /></p>
                </div>
            @endforeach
        </section>

        <div class="grid gap-6 lg:grid-cols-[1fr_22rem]">
            <div class="space-y-6">
                {{-- Bukti bayar menunggu konfirmasi --}}
                @if ($this->menungguKonfirmasi->isNotEmpty())
                    <section class="rounded-2xl border border-amber-200 bg-amber-50/60 p-1 dark:border-amber-500/20 dark:bg-amber-500/5">
                        <div class="flex items-center gap-2 px-4 pt-3 pb-2">
                            <span class="relative flex size-2">
                                <span class="absolute inline-flex size-full rounded-full bg-amber-400 opacity-75 motion-safe:animate-ping"></span>
                                <span class="relative inline-flex size-2 rounded-full bg-amber-500"></span>
                            </span>
                            <h2 class="text-sm font-semibold text-amber-900 dark:text-amber-200">{{ __('Bukti bayar menunggu konfirmasimu') }}</h2>
                            <span class="ms-auto rounded-full bg-amber-200/70 px-2 py-0.5 text-xs font-semibold text-amber-900 dark:bg-amber-500/20 dark:text-amber-200">{{ $this->menungguKonfirmasi->count() }}</span>
                        </div>

                        <ul class="space-y-1">
                            @foreach ($this->menungguKonfirmasi as $pembayaran)
                                <li wire:key="menunggu-{{ $pembayaran->id }}">
                                    <a href="{{ route('tagihan.show', [$pembayaran->tagihan->grup_id, $pembayaran->tagihan_id]) }}" wire:navigate class="group flex items-center gap-3 rounded-xl bg-white px-4 py-3 transition hover:shadow-sm dark:bg-zinc-900">
                                        <x-inisial :nama="$pembayaran->dari->nama" />
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate font-medium text-zinc-900 dark:text-white">{{ __(':nama mengirim bukti', ['nama' => $pembayaran->dari->nama]) }}</p>
                                            <p class="truncate text-sm text-zinc-600 dark:text-zinc-400">{{ $pembayaran->tagihan->judul }} · {{ $pembayaran->tagihan->grup->nama }}</p>
                                        </div>
                                        <x-rupiah :jumlah="$pembayaran->jumlah" class="shrink-0 font-semibold text-zinc-900 tabular-nums dark:text-white" />
                                        <span class="hidden shrink-0 rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white transition group-hover:bg-indigo-500 sm:inline">{{ __('Cek') }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                {{-- Yang perlu dibayar --}}
                <section class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="flex items-center justify-between gap-3 border-b border-zinc-100 px-5 py-4 dark:border-zinc-800">
                        <h2 class="font-semibold text-zinc-900 dark:text-white" data-tur="perlu-dibayar">{{ __('Yang perlu kamu bayar') }}</h2>
                        @if ($this->perluDibayar->isNotEmpty())
                            <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-semibold text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">{{ $this->perluDibayar->count() }}</span>
                        @endif
                    </div>

                    @if ($this->perluDibayar->isEmpty())
                        <div class="flex flex-col items-center px-5 py-10 text-center">
                            <span class="flex size-12 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400">
                                <flux:icon.check-badge class="size-6" />
                            </span>
                            <p class="mt-3 font-medium text-zinc-900 dark:text-white">{{ __('Semua bagianmu sudah lunas.') }}</p>
                            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">{{ __('Tagihan baru akan muncul di sini.') }}</p>
                        </div>
                    @else
                        <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach ($this->perluDibayar as $baris)
                                <li wire:key="bayar-{{ $baris['tagihan']->id }}">
                                    <a href="{{ route('tagihan.show', [$baris['tagihan']->grup_id, $baris['tagihan']->id]) }}" wire:navigate class="group flex items-center gap-3 px-5 py-3.5 transition hover:bg-zinc-50 dark:hover:bg-white/5">
                                        <x-inisial :nama="$baris['tagihan']->pembayar->nama" />
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate font-medium text-zinc-900 dark:text-white">{{ $baris['tagihan']->judul }}</p>
                                            <p class="truncate text-sm text-zinc-600 dark:text-zinc-400">{{ __('Ke :nama, :grup', ['nama' => $baris['tagihan']->pembayar->nama, 'grup' => $baris['tagihan']->grup->nama]) }}</p>
                                        </div>
                                        <div class="flex shrink-0 flex-col items-end gap-1">
                                            <x-rupiah :jumlah="$baris['sisa']" class="font-semibold text-zinc-900 tabular-nums dark:text-white" />
                                            @if ($baris['menunggu'])
                                                <span class="rounded-full bg-sky-100 px-2 py-0.5 text-xs font-semibold text-sky-700 dark:bg-sky-500/15 dark:text-sky-300">{{ __('Menunggu') }}</span>
                                            @else
                                                <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-700 dark:bg-amber-500/15 dark:text-amber-300">{{ __('Belum') }}</span>
                                            @endif
                                        </div>
                                        <flux:icon.chevron-right variant="mini" class="size-4 shrink-0 text-zinc-300 transition group-hover:translate-x-0.5 group-hover:text-zinc-500" />
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            </div>

            <div class="space-y-6">
                {{-- Ajakan mengisi rekening --}}
                @if (auth()->user()->infoBayar() === [])
                    <a href="{{ route('pembayaran.edit') }}" wire:navigate class="group flex items-center gap-3 rounded-2xl border border-indigo-200 bg-indigo-50/60 p-4 transition hover:-translate-y-0.5 hover:shadow-md dark:border-indigo-500/30 dark:bg-indigo-500/10" data-test="ajakan-rekening">
                        <span class="text-2xl" aria-hidden="true">💳</span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-indigo-900 dark:text-white/90">{{ __('Isi rekening kamu') }}</p>
                            <p class="text-xs text-indigo-800 dark:text-indigo-200">{{ __('Biar teman nggak nanya "transfer ke mana?" lagi.') }}</p>
                        </div>
                        <flux:icon.chevron-right variant="micro" class="shrink-0 text-indigo-400 transition group-hover:translate-x-0.5" />
                    </a>
                @endif

                {{-- Grup kamu --}}
                <section class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="flex items-center justify-between gap-3 border-b border-zinc-100 px-5 py-4 dark:border-zinc-800">
                        <h2 class="font-semibold text-zinc-900 dark:text-white" data-tur="grup-kamu">{{ __('Grup kamu') }}</h2>
                        <a href="{{ route('grup.index') }}" wire:navigate class="text-sm font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-300">{{ __('Lihat semua') }}</a>
                    </div>

                    <ul class="p-2">
                        @foreach ($this->keanggotaan->take(5) as $baris)
                            <li wire:key="grup-{{ $baris['anggota']->grup_id }}">
                                <a href="{{ route('grup.show', $baris['anggota']->grup) }}" wire:navigate class="flex items-center gap-3 rounded-xl px-3 py-2.5 transition hover:bg-zinc-50 dark:hover:bg-white/5">
                                    <x-emoji-grup :grup="$baris['anggota']->grup" ukuran="sm" />
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-medium text-zinc-900 dark:text-white">{{ $baris['anggota']->grup->nama }}</p>
                                        @if ($baris['saldo'] < 0)
                                            <p class="text-xs text-red-600 dark:text-red-400">{{ __('Kamu utang :jumlah', ['jumlah' => \App\Actions\Urunan\FormatRupiah::rp($baris['saldo'])]) }}</p>
                                        @elseif ($baris['saldo'] > 0)
                                            <p class="text-xs text-emerald-600 dark:text-emerald-400">{{ __('Ditunggu :jumlah', ['jumlah' => \App\Actions\Urunan\FormatRupiah::rp($baris['saldo'])]) }}</p>
                                        @else
                                            <p class="text-xs text-zinc-600 dark:text-zinc-400">{{ __('Semua lunas') }}</p>
                                        @endif
                                    </div>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>

                {{-- Aktivitas terbaru --}}
                <section class="rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="flex items-center justify-between gap-3 border-b border-zinc-100 px-5 py-4 dark:border-zinc-800">
                        <h2 class="font-semibold text-zinc-900 dark:text-white">{{ __('Aktivitas terbaru') }}</h2>
                        <a href="{{ route('notifikasi.index') }}" wire:navigate class="text-sm font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-300">{{ __('Lihat semua') }}</a>
                    </div>

                    @if ($this->aktivitas->isEmpty())
                        <p class="px-5 py-6 text-sm text-zinc-600 dark:text-zinc-400">{{ __('Belum ada aktivitas.') }}</p>
                    @else
                        <ol class="relative space-y-4 px-5 py-4">
                            @foreach ($this->aktivitas as $notifikasi)
                                @php($teks = \App\Notifications\NotifikasiUrunan::teksUntuk($notifikasi))
                                <li wire:key="aktivitas-{{ $notifikasi->id }}" class="flex gap-3">
                                    <span @class([
                                        'mt-1.5 size-2 shrink-0 rounded-full',
                                        'bg-indigo-500' => $notifikasi->read_at === null,
                                        'bg-zinc-300 dark:bg-zinc-600' => $notifikasi->read_at !== null,
                                    ])></span>
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-zinc-900 dark:text-white">{{ $teks['judul'] }}</p>
                                        <p class="line-clamp-2 text-xs text-zinc-600 dark:text-zinc-400">{{ $teks['pesan'] }}</p>
                                        <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">{{ $notifikasi->created_at->locale(app()->getLocale())->diffForHumans() }}</p>
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </section>
            </div>
        </div>
    @endif
</div>
