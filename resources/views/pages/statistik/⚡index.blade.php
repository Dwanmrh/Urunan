<?php

use App\Enums\StatusPembayaran;
use App\Models\Grup;
use App\Models\Pembayaran;
use App\Models\BagianTagihan;
use App\Models\Tagihan;
use App\Models\TagihanRutin;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Statistik')] class extends Component {
    /**
     * Pengunjung dan pengguna yang aktif dalam 5 menit terakhir, dihitung dari tabel sessions.
     *
     * @return array{pengunjung: int, pengguna: int}
     */
    #[Computed]
    public function online(): array
    {
        $batas = now()->subMinutes(5)->getTimestamp();

        return [
            'pengunjung' => DB::table('sessions')->where('last_activity', '>=', $batas)->count(),
            'pengguna' => DB::table('sessions')->where('last_activity', '>=', $batas)->whereNotNull('user_id')->distinct()->count('user_id'),
        ];
    }

    /**
     * @return array{pengunjung: int, penggunaAktif: int, pendaftarBaru: int}
     */
    #[Computed]
    public function hariIni(): array
    {
        $hariIni = now()->toDateString();

        return [
            'pengunjung' => DB::table('kunjungan')->where('tanggal', $hariIni)->count(),
            'penggunaAktif' => DB::table('kunjungan')->where('tanggal', $hariIni)->whereNotNull('user_id')->distinct()->count('user_id'),
            'pendaftarBaru' => User::whereDate('created_at', $hariIni)->count(),
        ];
    }

    /**
     * @return array{pengguna: int, grup: int, tagihan: int, nilaiTagihan: int, sudahDibayar: int}
     */
    #[Computed]
    public function total(): array
    {
        return [
            'pengguna' => User::count(),
            'grup' => Grup::count(),
            'tagihan' => Tagihan::count(),
            'nilaiTagihan' => (int) Tagihan::sum('total'),
            'sudahDibayar' => (int) Pembayaran::where('status', StatusPembayaran::Diterima->value)->sum('jumlah'),
        ];
    }

    /**
     * Pengunjung unik per hari selama 14 hari terakhir.
     *
     * @return list<array{label: string, jumlah: int}>
     */
    #[Computed]
    public function grafik(): array
    {
        $mulai = now()->subDays(13)->startOfDay();

        $jumlah = DB::table('kunjungan')
            ->where('tanggal', '>=', $mulai->toDateString())
            ->selectRaw('tanggal, COUNT(*) as jumlah')
            ->groupBy('tanggal')
            ->pluck('jumlah', 'tanggal');

        return collect(range(0, 13))
            ->map(function (int $hari) use ($mulai, $jumlah): array {
                $tanggal = Carbon::parse($mulai)->addDays($hari);

                return [
                    'label' => $tanggal->format('d/m'),
                    'jumlah' => (int) ($jumlah[$tanggal->toDateString()] ?? 0),
                ];
            })
            ->all();
    }

    /**
     * Persentase bagian anggota (selain penalang) yang sudah dibayar lunas.
     */
    #[Computed]
    public function tingkatPelunasan(): int
    {
        $harusDibayar = (int) BagianTagihan::query()
            ->join('tagihan', 'tagihan.id', '=', 'bagian_tagihan.tagihan_id')
            ->whereColumn('bagian_tagihan.anggota_grup_id', '!=', 'tagihan.dibayar_oleh')
            ->sum('bagian_tagihan.jumlah');

        return $harusDibayar > 0 ? (int) min(100, round($this->total['sudahDibayar'] / $harusDibayar * 100)) : 0;
    }

    /**
     * Pemakaian fitur khas Udunan.
     *
     * @return array{rutinAktif: int, isiRekening: int, grupAktif: int}
     */
    #[Computed]
    public function fitur(): array
    {
        return [
            'rutinAktif' => TagihanRutin::where('aktif', true)->count(),
            'isiRekening' => User::whereNotNull('info_bayar')->where('info_bayar', '!=', '[]')->count(),
            'grupAktif' => Tagihan::where('created_at', '>=', now()->subDays(7))->distinct()->count('grup_id'),
        ];
    }

    /**
     * Lima pendaftar terbaru.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function pendaftarTerbaru(): Collection
    {
        return User::latest('id')->limit(5)->get(['id', 'name', 'google_id', 'created_at']);
    }

    /**
     * Lima grup terbaru.
     *
     * @return Collection<int, Grup>
     */
    #[Computed]
    public function grupTerbaru(): Collection
    {
        return Grup::latest('id')->withCount(['anggota', 'tagihan'])->limit(5)->get();
    }
}; ?>

@php
    $angka = fn (int $n): string => number_format($n, 0, ',', '.');
    $tertinggi = max(1, max(array_column($this->grafik, 'jumlah')));
    $total14 = array_sum(array_column($this->grafik, 'jumlah'));
@endphp

<section class="w-full space-y-6" wire:poll.10s>
    {{-- Sampul --}}
    <section class="relative overflow-hidden rounded-3xl bg-linear-to-br from-slate-800 via-blue-950 to-indigo-950 p-6 text-white shadow-lg shadow-blue-950/20 motion-safe:animate-[muncul_0.5s_ease-out_both] sm:p-8">
        <div aria-hidden="true" class="absolute -top-20 -right-16 size-64 rounded-full bg-indigo-500/20 blur-3xl"></div>
        <div aria-hidden="true" class="absolute -bottom-24 left-1/4 size-56 rounded-full bg-sky-500/10 blur-3xl"></div>

        <div class="relative flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-xs font-semibold ring-1 ring-white/15">
                    <flux:icon.shield-check variant="micro" />{{ __('Khusus admin') }}
                </p>
                <h1 class="mt-3 text-2xl font-bold tracking-tight sm:text-3xl">{{ __('Statistik Udunan') }}</h1>
                <p class="mt-1 text-white/90">{{ __('Diperbarui otomatis tiap 10 detik. Hanya admin yang bisa melihat halaman ini.') }}</p>
            </div>

            <div class="grid grid-cols-2 gap-3 sm:min-w-80">
                @foreach ([['label' => __('Online sekarang'), 'nilai' => $this->online['pengunjung']], ['label' => __('Pengguna login'), 'nilai' => $this->online['pengguna']]] as $kartu)
                    <div class="rounded-2xl bg-white/10 p-4 ring-1 ring-white/15 backdrop-blur">
                        <p class="flex items-center gap-2 text-sm font-medium text-white/90">
                            <span class="relative flex size-2"><span class="absolute inline-flex size-full rounded-full bg-emerald-400 opacity-75 motion-safe:animate-ping"></span><span class="relative inline-flex size-2 rounded-full bg-emerald-400"></span></span>
                            {{ $kartu['label'] }}
                        </p>
                        <p class="mt-1 text-3xl font-bold tabular-nums">{{ $angka($kartu['nilai']) }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Hari ini --}}
    <section class="grid gap-4 sm:grid-cols-3">
        @foreach ([
            ['label' => __('Pengunjung unik hari ini'), 'nilai' => $this->hariIni['pengunjung'], 'ikon' => 'eye', 'warna' => 'bg-sky-50 text-sky-600 dark:bg-sky-500/10 dark:text-sky-300'],
            ['label' => __('Pengguna aktif hari ini'), 'nilai' => $this->hariIni['penggunaAktif'], 'ikon' => 'bolt', 'warna' => 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-300'],
            ['label' => __('Pendaftar baru hari ini'), 'nilai' => $this->hariIni['pendaftarBaru'], 'ikon' => 'user-plus', 'warna' => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-300'],
        ] as $urutan => $kartu)
            <div class="rounded-2xl border border-zinc-200 bg-white p-5 transition hover:-translate-y-0.5 hover:shadow-md motion-safe:animate-[muncul_0.4s_ease-out_both] dark:border-zinc-700/70 dark:bg-zinc-900" style="animation-delay: {{ 80 + $urutan * 70 }}ms">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-medium text-zinc-600 dark:text-zinc-400">{{ $kartu['label'] }}</p>
                    <span class="flex size-8 items-center justify-center rounded-lg {{ $kartu['warna'] }}">
                        <flux:icon :icon="$kartu['ikon']" variant="mini" class="size-4" />
                    </span>
                </div>
                <p class="mt-3 text-3xl font-bold text-zinc-900 tabular-nums dark:text-white">{{ $angka($kartu['nilai']) }}</p>
            </div>
        @endforeach
    </section>

    <div class="grid gap-6 lg:grid-cols-[1fr_20rem]">
        <div class="min-w-0 space-y-6">
            {{-- Grafik --}}
            <x-kartu :judul="__('Pengunjung unik 14 hari terakhir')" ikon="chart-bar">
                <x-slot:aksi>
                    <span class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('Total') }} <span class="font-semibold text-zinc-900 dark:text-white">{{ $angka($total14) }}</span></span>
                </x-slot:aksi>

                <div role="img" aria-label="{{ __('Grafik pengunjung unik per hari selama 14 hari terakhir') }}" class="flex h-52 items-end gap-1.5 sm:gap-2">
                    @foreach ($this->grafik as $urutan => $hari)
                        <div class="group relative flex h-full flex-1 flex-col items-center justify-end gap-1.5" wire:key="grafik-{{ $hari['label'] }}">
                            <span class="pointer-events-none absolute -top-1 z-10 -translate-y-full rounded-lg bg-zinc-900 px-2 py-1 text-xs font-semibold whitespace-nowrap text-white opacity-0 shadow transition group-hover:opacity-100 dark:bg-white dark:text-zinc-900">{{ $hari['label'] }} · {{ $hari['jumlah'] }}</span>
                            <div
                                @class([
                                    'w-full rounded-t-lg transition-all duration-500 group-hover:brightness-110',
                                    'bg-linear-to-t from-indigo-600 to-violet-500' => $urutan === count($this->grafik) - 1,
                                    'bg-linear-to-t from-indigo-500/70 to-indigo-400/70 dark:from-indigo-500/60 dark:to-indigo-400/60' => $urutan !== count($this->grafik) - 1,
                                ])
                                style="height: {{ round($hari['jumlah'] / $tertinggi * 100) }}%; min-height: {{ $hari['jumlah'] ? '6px' : '2px' }}"
                            ></div>
                            <span class="hidden text-xs text-zinc-600 sm:block dark:text-zinc-400">{{ $hari['label'] }}</span>
                        </div>
                    @endforeach
                </div>
            </x-kartu>

            {{-- Sejak awal --}}
            <x-kartu :judul="__('Sejak awal')" ikon="sparkles">
                <div class="grid grid-cols-2 gap-4 lg:grid-cols-3">
                    @foreach ([
                        ['label' => __('Pengguna terdaftar'), 'nilai' => $angka($this->total['pengguna'])],
                        ['label' => __('Grup patungan'), 'nilai' => $angka($this->total['grup'])],
                        ['label' => __('Tagihan dicatat'), 'nilai' => $angka($this->total['tagihan'])],
                        ['label' => __('Nilai tagihan dicatat'), 'nilai' => \App\Actions\Urunan\FormatRupiah::rp($this->total['nilaiTagihan'])],
                        ['label' => __('Pembayaran yang sudah lunas'), 'nilai' => \App\Actions\Urunan\FormatRupiah::rp($this->total['sudahDibayar'])],
                    ] as $kartu)
                        <div class="rounded-xl bg-zinc-50 p-4 dark:bg-white/5">
                            <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ $kartu['label'] }}</p>
                            <p class="mt-1 text-xl font-bold break-words text-zinc-900 tabular-nums dark:text-white">{{ $kartu['nilai'] }}</p>
                        </div>
                    @endforeach
                    <div class="rounded-xl bg-emerald-50 p-4 dark:bg-emerald-500/10">
                        <p class="text-sm text-emerald-800 dark:text-emerald-300">{{ __('Tingkat pelunasan') }}</p>
                        <p class="mt-1 text-xl font-bold text-emerald-700 tabular-nums dark:text-emerald-300">{{ $this->tingkatPelunasan }}%</p>
                        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-emerald-200/60 dark:bg-emerald-500/20">
                            <div class="h-full rounded-full bg-emerald-500" style="width: {{ $this->tingkatPelunasan }}%"></div>
                        </div>
                    </div>
                </div>
            </x-kartu>
        </div>

        <div class="min-w-0 space-y-6">
            {{-- Fitur khas --}}
            <x-kartu :judul="__('Pemakaian fitur')" ikon="rocket-launch" padat>
                <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach ([
                        ['emoji' => '🔁', 'label' => __('Tagihan rutin aktif'), 'nilai' => $this->fitur['rutinAktif']],
                        ['emoji' => '💳', 'label' => __('Pengguna isi rekening'), 'nilai' => $this->fitur['isiRekening']],
                        ['emoji' => '🔥', 'label' => __('Grup aktif 7 hari'), 'nilai' => $this->fitur['grupAktif']],
                    ] as $baris)
                        <li class="flex items-center gap-3 px-5 py-3">
                            <span class="text-xl" aria-hidden="true">{{ $baris['emoji'] }}</span>
                            <span class="min-w-0 flex-1 text-sm text-zinc-700 dark:text-zinc-300">{{ $baris['label'] }}</span>
                            <span class="font-bold text-zinc-900 tabular-nums dark:text-white">{{ $angka($baris['nilai']) }}</span>
                        </li>
                    @endforeach
                </ul>
            </x-kartu>

            {{-- Pendaftar terbaru --}}
            <x-kartu :judul="__('Pendaftar terbaru')" ikon="user-plus" padat>
                <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($this->pendaftarTerbaru as $pengguna)
                        <li wire:key="pengguna-{{ $pengguna->id }}" class="flex items-center gap-3 px-5 py-3">
                            <x-inisial :nama="$pengguna->name" />
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-zinc-900 dark:text-white">{{ $pengguna->name }}</p>
                                <p class="text-xs text-zinc-600 dark:text-zinc-400">{{ $pengguna->created_at?->locale(app()->getLocale())->diffForHumans() }}</p>
                            </div>
                            <span class="shrink-0 rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-semibold text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">{{ $pengguna->google_id ? 'Google' : __('Email') }}</span>
                        </li>
                    @empty
                        <li class="px-5 py-4 text-sm text-zinc-600 dark:text-zinc-400">{{ __('Belum ada pengguna.') }}</li>
                    @endforelse
                </ul>
            </x-kartu>

            {{-- Grup terbaru --}}
            <x-kartu :judul="__('Grup terbaru')" ikon="user-group" padat>
                <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($this->grupTerbaru as $grup)
                        <li wire:key="grup-{{ $grup->id }}" class="flex items-center gap-3 px-5 py-3">
                            <x-emoji-grup :grup="$grup" ukuran="sm" />
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-zinc-900 dark:text-white">{{ $grup->nama }}</p>
                                <p class="text-xs text-zinc-600 dark:text-zinc-400">{{ __(':orang orang, :tagihan tagihan', ['orang' => $grup->anggota_count, 'tagihan' => $grup->tagihan_count]) }}</p>
                            </div>
                        </li>
                    @empty
                        <li class="px-5 py-4 text-sm text-zinc-600 dark:text-zinc-400">{{ __('Belum ada grup.') }}</li>
                    @endforelse
                </ul>
            </x-kartu>
        </div>
    </div>
</section>
