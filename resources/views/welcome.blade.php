<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark scroll-smooth">
    <head>
        @include('partials.head', ['title' => 'Patungan tanpa ribet'])
        <meta name="description" content="{{ __('Catat tagihan makan, liburan, atau kos. Urunan menghitung bagian tiap orang, mencatat siapa yang sudah bayar, dan mengingatkan yang belum.') }}">
    </head>
    <body class="min-h-screen bg-white text-zinc-700 antialiased dark:bg-zinc-950 dark:text-zinc-300">
        {{-- Cahaya latar --}}
        <div aria-hidden="true" class="pointer-events-none fixed inset-x-0 top-0 -z-10 h-[38rem] overflow-hidden">
            <div class="absolute -top-40 left-1/2 h-[36rem] w-[60rem] -translate-x-1/2 rounded-full bg-indigo-500/20 blur-3xl dark:bg-indigo-600/25"></div>
            <div class="absolute -top-20 right-[-10rem] h-80 w-80 rounded-full bg-fuchsia-400/10 blur-3xl"></div>
        </div>

        {{-- Header --}}
        <header
            x-data="{ menempel: false }"
            x-on:scroll.window.passive="menempel = window.scrollY > 8"
            class="sticky top-0 z-40 transition"
            :class="menempel ? 'border-b border-zinc-200/70 bg-white/80 backdrop-blur-lg dark:border-white/10 dark:bg-zinc-950/70' : 'border-b border-transparent'"
        >
            <div class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-5 sm:px-8">
                <x-app-logo href="{{ route('home') }}" />

                <nav class="hidden items-center gap-7 text-sm font-medium md:flex" aria-label="{{ __('Navigasi halaman') }}">
                    <a href="#cara-kerja" class="transition hover:text-zinc-950 dark:hover:text-white">{{ __('Cara kerja') }}</a>
                    <a href="#fitur" class="transition hover:text-zinc-950 dark:hover:text-white">{{ __('Fitur') }}</a>
                    <a href="#tanya-jawab" class="transition hover:text-zinc-950 dark:hover:text-white">{{ __('Tanya jawab') }}</a>
                </nav>

                <div class="flex items-center gap-2">
                    <x-pilih-bahasa class="me-1 hidden sm:flex" />
                    @auth
                        <flux:button :href="route('dashboard')" variant="primary" size="sm">{{ __('Buka Urunan') }}</flux:button>
                    @else
                        <flux:button :href="route('login')" variant="ghost" size="sm">{{ __('Log in') }}</flux:button>
                        @if (Route::has('register'))
                            <flux:button :href="route('register')" variant="primary" size="sm">{{ __('Daftar') }}</flux:button>
                        @endif
                    @endauth
                </div>
            </div>
        </header>

        <main>
            {{-- Hero --}}
            <section class="mx-auto grid max-w-6xl items-center gap-12 px-5 pt-12 pb-20 sm:px-8 lg:grid-cols-[1.1fr_1fr] lg:pt-20 lg:pb-28">
                <div class="motion-safe:animate-[muncul_0.6s_ease-out_both]">
                    <span class="inline-flex items-center gap-2 rounded-full border border-indigo-200 bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700 dark:border-indigo-400/20 dark:bg-indigo-400/10 dark:text-indigo-300">
                        <span class="size-1.5 rounded-full bg-indigo-500 motion-safe:animate-pulse"></span>
                        {{ __('Gratis, tanpa iklan') }}
                    </span>

                    <h1 class="mt-5 text-4xl font-bold tracking-tight text-balance text-zinc-950 sm:text-5xl lg:text-6xl dark:text-white">
                        {{ __('Patungan bareng teman,') }}
                        <span class="bg-linear-to-r from-indigo-500 via-violet-500 to-fuchsia-500 bg-clip-text text-transparent">{{ __('tanpa hitung manual.') }}</span>
                    </h1>

                    <p class="mt-5 max-w-xl text-lg text-pretty">
                        {{ __('Catat tagihan makan, liburan, atau kos. Urunan menghitung bagian tiap orang, mencatat siapa yang sudah bayar, dan mengingatkan yang belum.') }}
                    </p>

                    <div class="mt-8 flex flex-wrap gap-3">
                        @auth
                            <flux:button :href="route('dashboard')" variant="primary" icon:trailing="arrow-right">{{ __('Buka Urunan') }}</flux:button>
                        @else
                            <flux:button :href="route('register')" variant="primary" icon:trailing="arrow-right">{{ __('Mulai gratis') }}</flux:button>
                            <flux:button href="#cara-kerja" variant="ghost">{{ __('Lihat cara kerjanya') }}</flux:button>
                        @endauth
                    </div>

                    <ul class="mt-8 flex flex-wrap gap-x-6 gap-y-2 text-sm">
                        @foreach ([__('Bisa masuk dengan Google'), __('Teman tanpa akun tetap bisa dicatat'), __('Bahasa Indonesia & English')] as $poin)
                            <li class="flex items-center gap-2">
                                <flux:icon.check-circle variant="mini" class="size-4 text-emerald-500" />
                                {{ $poin }}
                            </li>
                        @endforeach
                    </ul>
                </div>

                {{-- Kalkulator mini --}}
                <div
                    x-data="{
                        total: 360000,
                        orang: 4,
                        get dasar() { return Math.floor(this.total / this.orang) },
                        get sisa() { return this.total % this.orang },
                        bagian(urutan) { return this.dasar + (urutan < this.sisa ? 1 : 0) },
                        rupiah(angka) { return 'Rp ' + new Intl.NumberFormat('id-ID').format(angka) },
                        ubahTotal(teks) {
                            const angka = parseInt(String(teks).replace(/\D/g, '') || '0', 10);
                            this.total = Math.min(angka, 1000000000);
                        },
                    }"
                    class="relative motion-safe:animate-[muncul_0.7s_0.1s_ease-out_both]"
                >
                    <div aria-hidden="true" class="absolute -inset-4 -z-10 rounded-[2rem] bg-linear-to-br from-indigo-500/30 via-violet-500/20 to-transparent blur-2xl"></div>

                    <div class="rounded-3xl border border-zinc-200 bg-white/90 p-6 shadow-xl backdrop-blur sm:p-7 dark:border-white/10 dark:bg-zinc-900/80">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-semibold text-zinc-950 dark:text-white">{{ __('Coba hitung sekarang') }}</p>
                            <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-300">{{ __('Langsung') }}</span>
                        </div>

                        <label for="coba-total" class="mt-5 block text-xs font-medium tracking-wide text-zinc-600 dark:text-zinc-400 uppercase">{{ __('Total tagihan') }}</label>
                        <div class="mt-2 flex items-center rounded-xl border border-zinc-200 bg-zinc-50 px-4 focus-within:ring-2 focus-within:ring-indigo-500 dark:border-white/10 dark:bg-zinc-950/50">
                            <span class="text-sm text-zinc-600 dark:text-zinc-400">Rp</span>
                            <input
                                id="coba-total"
                                type="text"
                                inputmode="numeric"
                                :value="new Intl.NumberFormat('id-ID').format(total)"
                                x-on:input="ubahTotal($event.target.value); $event.target.value = new Intl.NumberFormat('id-ID').format(total)"
                                class="w-full bg-transparent px-2 py-3 text-2xl font-bold text-zinc-950 outline-none dark:text-white"
                            >
                        </div>

                        <div class="mt-5 flex items-center justify-between">
                            <label for="coba-orang" class="text-xs font-medium tracking-wide text-zinc-600 dark:text-zinc-400 uppercase">{{ __('Jumlah orang') }}</label>
                            <div class="flex items-center gap-2">
                                <button type="button" x-on:click="orang = Math.max(2, orang - 1)" class="flex size-8 items-center justify-center rounded-lg border border-zinc-200 transition hover:bg-zinc-100 dark:border-white/10 dark:hover:bg-white/5" aria-label="{{ __('Kurangi') }}">−</button>
                                <span class="w-8 text-center text-lg font-bold text-zinc-950 tabular-nums dark:text-white" x-text="orang"></span>
                                <button type="button" x-on:click="orang = Math.min(20, orang + 1)" class="flex size-8 items-center justify-center rounded-lg border border-zinc-200 transition hover:bg-zinc-100 dark:border-white/10 dark:hover:bg-white/5" aria-label="{{ __('Tambah') }}">+</button>
                            </div>
                        </div>
                        <input id="coba-orang" type="range" min="2" max="20" x-model.number="orang" class="mt-3 w-full accent-indigo-600">

                        <div class="mt-6 rounded-2xl bg-indigo-600 p-5 text-white">
                            <p class="text-sm text-white/90">{{ __('Bagian tiap orang') }}</p>
                            <p class="mt-1 text-3xl font-bold tabular-nums" x-text="rupiah(dasar) + (sisa ? '+' : '')"></p>
                            <p class="mt-1 text-xs text-white/90" x-show="sisa" x-cloak x-text="@js(__(':sisa orang pertama bayar Rp 1 lebih supaya pas.')).replace(':sisa', sisa)"></p>
                        </div>

                        <ul class="mt-4 space-y-1.5 text-sm">
                            <template x-for="urutan in Math.min(orang, 4)" :key="urutan">
                                <li class="flex items-center justify-between">
                                    <span class="flex items-center gap-2">
                                        <span class="flex size-6 items-center justify-center rounded-full bg-zinc-100 text-xs font-semibold dark:bg-white/10" x-text="urutan"></span>
                                        <span x-text="urutan === 1 ? @js(__('Kamu')) : @js(__('Teman')) + ' ' + (urutan - 1)"></span>
                                    </span>
                                    <span class="font-medium tabular-nums text-zinc-950 dark:text-white" x-text="rupiah(bagian(urutan - 1))"></span>
                                </li>
                            </template>
                            <li x-show="orang > 4" x-cloak class="text-xs text-zinc-600 dark:text-zinc-400" x-text="@js(__('dan :jumlah orang lainnya')).replace(':jumlah', orang - 4)"></li>
                        </ul>
                    </div>
                </div>
            </section>

            {{-- Cara kerja --}}
            <section id="cara-kerja" class="scroll-mt-20 border-y border-zinc-200/70 bg-zinc-50/70 py-20 dark:border-white/5 dark:bg-white/[0.02]">
                <div class="mx-auto max-w-6xl px-5 sm:px-8">
                    <div class="max-w-2xl">
                        <p class="text-sm font-semibold text-indigo-600 dark:text-indigo-400">{{ __('Cara kerja') }}</p>
                        <h2 class="mt-2 text-3xl font-bold tracking-tight text-zinc-950 sm:text-4xl dark:text-white">{{ __('Tiga langkah, semua beres.') }}</h2>
                    </div>

                    <ol class="mt-12 grid gap-6 md:grid-cols-3">
                        @foreach ([
                            ['ikon' => 'user-group', 'judul' => __('Buat grup'), 'isi' => __('Satu grup untuk satu acara atau lingkaran teman. Bagikan link undangan ke grup WhatsApp.')],
                            ['ikon' => 'receipt-percent', 'judul' => __('Catat tagihan'), 'isi' => __('Yang menalangi mencatat total atau rincian item. Bagian tiap orang langsung dihitung.')],
                            ['ikon' => 'check-badge', 'judul' => __('Bayar dan konfirmasi'), 'isi' => __('Teman transfer lalu kirim bukti. Yang menalangi tinggal konfirmasi, statusnya jadi lunas.')],
                        ] as $urutan => $langkah)
                            <li
                                x-data="{ tampil: false }"
                                x-intersect.once="tampil = true"
                                :class="tampil ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4'"
                                style="transition-delay: {{ $urutan * 120 }}ms"
                                class="relative rounded-2xl border border-zinc-200 bg-white p-6 transition duration-700 dark:border-white/10 dark:bg-zinc-900"
                            >
                                <div class="flex items-center gap-3">
                                    <span class="flex size-10 items-center justify-center rounded-xl bg-indigo-600 text-white shadow-lg shadow-indigo-600/30">
                                        <flux:icon :icon="$langkah['ikon']" class="size-5" />
                                    </span>
                                    <span class="text-sm font-semibold text-zinc-500 dark:text-zinc-400">0{{ $urutan + 1 }}</span>
                                </div>
                                <h3 class="mt-5 text-lg font-semibold text-zinc-950 dark:text-white">{{ $langkah['judul'] }}</h3>
                                <p class="mt-2 text-sm leading-relaxed">{{ $langkah['isi'] }}</p>
                            </li>
                        @endforeach
                    </ol>
                </div>
            </section>

            {{-- Fitur --}}
            <section id="fitur" class="mx-auto max-w-6xl scroll-mt-20 px-5 py-20 sm:px-8">
                <div class="max-w-2xl">
                    <p class="text-sm font-semibold text-indigo-600 dark:text-indigo-400">{{ __('Fitur') }}</p>
                    <h2 class="mt-2 text-3xl font-bold tracking-tight text-zinc-950 sm:text-4xl dark:text-white">{{ __('Dibuat untuk patungan sehari-hari.') }}</h2>
                </div>

                <div class="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ([
                        ['ikon' => 'calculator', 'judul' => __('Bagi rata atau per item'), 'isi' => __('Yang pesan daging bayar daging, yang ikut semua bayar bagian bersama.')],
                        ['ikon' => 'photo', 'judul' => __('Bukti bayar tercatat'), 'isi' => __('Kirim foto bukti transfer, yang menalangi tinggal konfirmasi.')],
                        ['ikon' => 'link', 'judul' => __('Satu link untuk grup'), 'isi' => __('Bagikan link undangan ke grup WhatsApp, teman tinggal bergabung.')],
                        ['ikon' => 'arrows-right-left', 'judul' => __('Siapa bayar ke siapa'), 'isi' => __('Urunan meringkas utang semua anggota jadi transfer sesedikit mungkin.')],
                        ['ikon' => 'bell-alert', 'judul' => __('Notifikasi'), 'isi' => __('Kabar tagihan baru, bukti bayar masuk, dan konfirmasi pembayaran muncul di aplikasi dan Telegram.')],
                        ['ikon' => 'language', 'judul' => __('Dua bahasa'), 'isi' => __('Ganti tampilan ke Bahasa Indonesia atau English kapan saja.')],
                    ] as $urutan => $fitur)
                        <div
                            x-data="{ tampil: false }"
                            x-intersect.once="tampil = true"
                            :class="tampil ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4'"
                            style="transition-delay: {{ ($urutan % 3) * 100 }}ms"
                            class="group rounded-2xl border border-zinc-200 p-6 transition duration-700 hover:-translate-y-1 hover:border-indigo-300 hover:shadow-lg hover:shadow-indigo-500/5 dark:border-white/10 dark:hover:border-indigo-400/40"
                        >
                            <span class="flex size-10 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 transition group-hover:bg-indigo-600 group-hover:text-white dark:bg-indigo-400/10 dark:text-indigo-300">
                                <flux:icon :icon="$fitur['ikon']" class="size-5" />
                            </span>
                            <h3 class="mt-4 font-semibold text-zinc-950 dark:text-white">{{ $fitur['judul'] }}</h3>
                            <p class="mt-1.5 text-sm leading-relaxed">{{ $fitur['isi'] }}</p>
                        </div>
                    @endforeach
                </div>
            </section>

            {{-- Tanya jawab --}}
            <section id="tanya-jawab" class="mx-auto max-w-3xl scroll-mt-20 px-5 pb-20 sm:px-8">
                <h2 class="text-center text-3xl font-bold tracking-tight text-zinc-950 dark:text-white">{{ __('Tanya jawab') }}</h2>

                <div x-data="{ buka: 0 }" class="mt-10 divide-y divide-zinc-200 rounded-2xl border border-zinc-200 dark:divide-white/10 dark:border-white/10">
                    @foreach ([
                        [__('Apakah Urunan gratis?'), __('Ya, gratis dan tanpa iklan.')],
                        [__('Apakah uangnya lewat Urunan?'), __('Tidak. Urunan hanya mencatat dan menghitung. Transfer tetap langsung ke rekening atau dompet digital teman yang menalangi.')],
                        [__('Apakah semua teman harus daftar?'), __('Tidak. Teman tanpa akun tetap bisa dicatat di grup. Kalau nanti mereka bergabung lewat link undangan, tagihannya ikut tersambung.')],
                        [__('Siapa yang bisa melihat bukti bayar?'), __('Hanya anggota grup itu. Foto bukti disimpan privat dan tidak bisa dibuka lewat link publik.')],
                    ] as $urutan => [$tanya, $jawab])
                        <div>
                            <button
                                type="button"
                                x-on:click="buka = buka === {{ $urutan }} ? null : {{ $urutan }}"
                                :aria-expanded="buka === {{ $urutan }}"
                                class="flex w-full items-center justify-between gap-4 px-5 py-4 text-start font-medium text-zinc-950 dark:text-white"
                            >
                                {{ $tanya }}
                                <flux:icon.chevron-down class="size-5 shrink-0 text-zinc-500 dark:text-zinc-400 transition" x-bind:class="buka === {{ $urutan }} && 'rotate-180'" />
                            </button>
                            <div x-show="buka === {{ $urutan }}" x-collapse x-cloak>
                                <p class="px-5 pb-5 text-sm leading-relaxed">{{ $jawab }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

            {{-- Ajakan --}}
            <section class="mx-auto max-w-6xl px-5 pb-20 sm:px-8">
                <div class="relative overflow-hidden rounded-3xl bg-linear-to-br from-indigo-600 via-indigo-600 to-violet-600 px-6 py-14 text-center sm:px-12">
                    <div aria-hidden="true" class="absolute -top-24 -right-24 size-72 rounded-full bg-white/10 blur-2xl"></div>
                    <h2 class="relative text-3xl font-bold tracking-tight text-white sm:text-4xl">{{ __('Patungan berikutnya, biar Urunan yang hitung.') }}</h2>
                    <p class="relative mx-auto mt-3 max-w-xl text-white/90">{{ __('Buat grup pertamamu dalam satu menit.') }}</p>
                    <div class="relative mt-8 flex justify-center">
                        <a href="{{ auth()->check() ? route('dashboard') : route('register') }}" class="inline-flex items-center gap-2 rounded-xl bg-white px-6 py-3 font-semibold text-indigo-700 shadow-lg transition hover:-translate-y-0.5 hover:shadow-xl">
                            {{ auth()->check() ? __('Buka Urunan') : __('Mulai gratis') }}
                            <flux:icon.arrow-right variant="mini" class="size-4" />
                        </a>
                    </div>
                </div>
            </section>
        </main>

        <footer class="border-t border-zinc-200/70 dark:border-white/5">
            <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-4 px-5 py-8 text-sm sm:flex-row sm:px-8">
                <p>&copy; {{ now()->year }} {{ config('app.name') }}. {{ __('Patungan tanpa ribet') }}.</p>
                <x-pilih-bahasa />
            </div>
        </footer>

        @livewireScripts
        @fluxScripts
    </body>
</html>
