{{-- Navigasi bawah ala aplikasi untuk HP dan tablet (di bawah ukuran laptop). --}}
@php
    $grupSaya = auth()->user()->anggotaGrup()->with('grup')->latest('id')->get()->pluck('grup')->filter();
    $menuBawah = [
        ['rute' => 'dashboard', 'aktif' => 'dashboard', 'ikon' => 'home', 'label' => __('Beranda'), 'tur' => null],
        ['rute' => 'grup.index', 'aktif' => 'grup.*', 'ikon' => 'user-group', 'label' => __('Grup'), 'tur' => 'menu-grup-hp'],
        null,
        ['rute' => 'notifikasi.index', 'aktif' => 'notifikasi.*', 'ikon' => 'bell', 'label' => __('Notifikasi'), 'tur' => 'menu-notifikasi-hp'],
        ['rute' => 'profile.edit', 'aktif' => ['profile.*', 'pembayaran.*', 'security.*', 'appearance.*', 'telegram.*'], 'ikon' => 'user-circle', 'label' => __('Akun'), 'tur' => null],
    ];
@endphp

<nav class="fixed inset-x-0 bottom-0 z-40 border-t border-zinc-200/80 bg-white/90 pb-[env(safe-area-inset-bottom)] backdrop-blur-lg lg:hidden dark:border-zinc-700/80 dark:bg-zinc-900/90" aria-label="{{ __('Navigasi utama') }}" data-test="navigasi-bawah">
    <ul class="mx-auto grid h-16 max-w-xl grid-cols-5 items-center px-2">
        @foreach ($menuBawah as $item)
            @if ($item === null)
                <li class="flex justify-center">
                    <button
                        type="button"
                        x-on:click="$flux.modal('catat-cepat').show()"
                        class="-mt-7 flex size-14 items-center justify-center rounded-2xl bg-linear-to-br from-indigo-600 to-violet-600 text-white shadow-lg shadow-indigo-600/40 ring-4 ring-white transition active:scale-95 dark:ring-zinc-900"
                        aria-label="{{ __('Catat tagihan') }}"
                        data-test="tombol-catat-cepat"
                    >
                        <flux:icon.plus class="size-7" />
                    </button>
                </li>
            @else
                @php($aktif = request()->routeIs(...(array) $item['aktif']))
                <li>
                    <a
                        href="{{ route($item['rute']) }}"
                        wire:navigate
                        @if ($item['tur']) data-tur="{{ $item['tur'] }}" @endif
                        @if ($aktif) aria-current="page" @endif
                        @class([
                            'relative flex flex-col items-center gap-0.5 rounded-xl py-1.5 text-xs font-semibold transition active:scale-95',
                            'text-indigo-600 dark:text-indigo-300' => $aktif,
                            'text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white' => ! $aktif,
                        ])
                    >
                        <span @class(['relative flex h-7 w-12 items-center justify-center rounded-full transition', 'bg-indigo-100 dark:bg-indigo-500/20' => $aktif])>
                            <flux:icon :icon="$item['ikon']" :variant="$aktif ? 'solid' : 'outline'" class="size-5" />
                            @if ($item['rute'] === 'notifikasi.index' && $jumlahNotifikasiBaru)
                                <span class="absolute -top-0.5 right-1.5 flex min-w-4 items-center justify-center rounded-full bg-rose-500 px-1 text-[0.6875rem] leading-4 font-bold text-white ring-2 ring-white dark:ring-zinc-900">{{ $jumlahNotifikasiBaru > 9 ? '9+' : $jumlahNotifikasiBaru }}</span>
                            @endif
                        </span>
                        {{ $item['label'] }}
                    </a>
                </li>
            @endif
        @endforeach
    </ul>
</nav>

<flux:modal name="catat-cepat" variant="flyout" position="bottom" class="lg:hidden">
    <div class="space-y-4 pb-[env(safe-area-inset-bottom)]">
        <div>
            <flux:heading size="lg">{{ __('Catat tagihan') }} 🧾</flux:heading>
            <flux:subheading>{{ __('Pilih grupnya dulu.') }}</flux:subheading>
        </div>

        @if ($grupSaya->isEmpty())
            <div class="rounded-2xl border border-dashed border-zinc-300 p-6 text-center dark:border-zinc-700">
                <p class="text-sm text-zinc-700 dark:text-zinc-300">{{ __('Kamu belum punya grup. Buat dulu, yuk.') }}</p>
            </div>
        @else
            <ul class="max-h-[50vh] space-y-2 overflow-y-auto">
                @foreach ($grupSaya as $grup)
                    <li wire:key="catat-{{ $grup->id }}">
                        <a href="{{ route('tagihan.create', $grup) }}" wire:navigate class="flex items-center gap-3 rounded-2xl border border-zinc-200 p-3 transition active:scale-[0.98] hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-white/5">
                            <x-emoji-grup :grup="$grup" />
                            <span class="min-w-0 flex-1 truncate font-semibold text-zinc-900 dark:text-white">{{ $grup->nama }}</span>
                            <flux:icon.chevron-right variant="mini" class="text-zinc-500 dark:text-zinc-400" />
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif

        <flux:button :href="route('grup.index')" icon="plus" class="w-full" wire:navigate>{{ __('Buat grup baru') }}</flux:button>
    </div>
</flux:modal>
