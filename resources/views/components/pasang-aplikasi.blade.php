@props([
    'gaya' => 'kartu',
])

{{--
    Ajakan memasang Udunan sebagai aplikasi.

    Android, Chrome, dan Edge: memunculkan dialog pemasangan bawaan browser.
    iPhone dan iPad: menampilkan langkah "Bagikan > Tambahkan ke Layar Utama", karena Safari tidak punya dialog pemasangan.
    Tidak tampil kalau Udunan sudah dibuka sebagai aplikasi, atau kartunya sudah ditutup.
--}}
<div
    x-data="{
        tampil: false,
        iOS: false,
        panduan: false,
        init() {
            const pasang = window.urunanPasang;
            if (! pasang || pasang.sudahTerpasang()) return;
            let ditutup = false;
            try { ditutup = localStorage.getItem('urunan:pasang-ditutup') === '1'; } catch {}
            if (ditutup && @js($gaya) === 'kartu') return;
            this.iOS = pasang.iOS();
            this.tampil = this.iOS || pasang.tunda !== null;
        },
        async pasang() {
            if (this.iOS) { this.panduan = true; return; }
            const tunda = window.urunanPasang?.tunda;
            if (! tunda) return;
            tunda.prompt();
            const hasil = await tunda.userChoice;
            if (hasil.outcome === 'accepted') this.tampil = false;
            window.urunanPasang.tunda = null;
        },
        tutup() {
            this.tampil = false;
            try { localStorage.setItem('urunan:pasang-ditutup', '1'); } catch {}
        },
    }"
    x-on:urunan-bisa-dipasang.window="init()"
    x-on:urunan-terpasang.window="tampil = false"
    {{ $attributes }}
>
    @if ($gaya === 'kartu')
        <div x-show="tampil" x-cloak x-transition class="relative flex items-center gap-4 overflow-hidden rounded-2xl border border-indigo-200 bg-linear-to-r from-indigo-50 to-violet-50 p-4 dark:border-indigo-500/30 dark:from-indigo-500/10 dark:to-violet-500/10" data-test="kartu-pasang-aplikasi">
            <img src="/ikon/ikon-192.png" alt="" class="size-12 shrink-0 rounded-xl shadow-sm">
            <div class="min-w-0 flex-1">
                <p class="font-semibold text-zinc-900 dark:text-white">{{ __('Pasang Udunan di HP-mu') }}</p>
                <p class="text-sm text-zinc-700 dark:text-zinc-300">{{ __('Buka langsung dari layar utama, layar penuh tanpa bar browser.') }}</p>
            </div>
            <div class="flex shrink-0 items-center gap-1">
                <button type="button" x-on:click="pasang()" class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-500">{{ __('Pasang') }}</button>
                <button type="button" x-on:click="tutup()" class="rounded-lg p-2 text-zinc-500 transition hover:bg-white/60 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-white/10 dark:hover:text-white" aria-label="{{ __('Tutup') }}">
                    <flux:icon.x-mark variant="micro" />
                </button>
            </div>
        </div>
    @else
        <button type="button" x-show="tampil" x-cloak x-on:click="pasang()" class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-start text-sm font-medium text-zinc-800 hover:bg-zinc-50 dark:text-white dark:hover:bg-zinc-600" data-test="menu-pasang-aplikasi">
            <flux:icon.arrow-down-tray variant="mini" class="size-5 text-zinc-500 dark:text-zinc-400" />
            {{ __('Pasang aplikasi') }}
        </button>
    @endif

    {{-- Langkah untuk iPhone dan iPad --}}
    <template x-teleport="body">
        <div x-show="panduan" x-cloak class="fixed inset-0 z-70 flex items-end justify-center p-4 sm:items-center" role="dialog" aria-modal="true" aria-labelledby="judul-pasang-ios">
            <div x-show="panduan" x-transition.opacity x-on:click="panduan = false" class="absolute inset-0 bg-zinc-950/50 backdrop-blur-sm"></div>
            <div x-show="panduan" x-transition class="relative w-full max-w-sm rounded-3xl bg-white p-6 shadow-xl dark:bg-zinc-800">
                <div class="flex items-center gap-3">
                    <img src="/ikon/ikon-192.png" alt="" class="size-12 rounded-xl">
                    <div>
                        <h2 id="judul-pasang-ios" class="font-semibold text-zinc-900 dark:text-white">{{ __('Pasang di iPhone / iPad') }}</h2>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('Cuma 3 langkah lewat Safari') }}</p>
                    </div>
                </div>
                <ol class="mt-5 space-y-3">
                    @foreach ([
                        ['arrow-up-on-square', __('Ketuk tombol Bagikan di bar bawah Safari')],
                        ['plus-circle', __('Pilih "Tambahkan ke Layar Utama"')],
                        ['check-circle', __('Ketuk "Tambah" di pojok kanan atas')],
                    ] as $urutan => [$ikon, $teks])
                        <li class="flex items-center gap-3 rounded-2xl bg-zinc-50 p-3 dark:bg-white/5">
                            <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-indigo-600 text-sm font-bold text-white">{{ $urutan + 1 }}</span>
                            <span class="min-w-0 flex-1 text-sm font-medium text-zinc-800 dark:text-zinc-100">{{ $teks }}</span>
                            <flux:icon :icon="$ikon" class="size-5 shrink-0 text-indigo-600 dark:text-indigo-300" />
                        </li>
                    @endforeach
                </ol>
                <button type="button" x-on:click="panduan = false" class="mt-5 w-full rounded-xl bg-zinc-900 py-2.5 text-sm font-semibold text-white dark:bg-white dark:text-zinc-900">{{ __('Mengerti') }}</button>
            </div>
        </div>
    </template>
</div>
