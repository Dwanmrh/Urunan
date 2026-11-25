<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        @php($jumlahNotifikasiBaru = auth()->user()->unreadNotifications()->count())

        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Platform')" class="grid">
                    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="user-group" :href="route('grup.index')" :current="request()->routeIs('grup.*')" wire:navigate data-tur="menu-grup">
                        {{ __('Grup patungan') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item
                        icon="bell"
                        data-tur="menu-notifikasi"
                        :href="route('notifikasi.index')"
                        :current="request()->routeIs('notifikasi.*')"
                        :badge="$jumlahNotifikasiBaru ?: null"
                        badge:color="sky"
                        wire:navigate
                    >
                        {{ __('Notifikasi') }}
                    </flux:sidebar.item>
                    @can('lihat-statistik')
                        <flux:sidebar.item icon="chart-bar" :href="route('statistik.index')" :current="request()->routeIs('statistik.*')" wire:navigate>
                            {{ __('Statistik') }}
                        </flux:sidebar.item>
                    @endcan
                </flux:sidebar.group>
            </flux:sidebar.nav>

            <flux:spacer />

            <flux:sidebar.nav>
                <flux:sidebar.item icon="question-mark-circle" x-on:click="mulaiTur({{ \Illuminate\Support\Js::from(__('Panduan belum tersedia di halaman ini.')) }})" data-tur="tombol-panduan" data-test="buka-panduan">
                    {{ __('Panduan') }}
                </flux:sidebar.item>
            </flux:sidebar.nav>

            <x-pilih-bahasa class="px-1" data-tur="pilih-bahasa" />

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <button type="button" x-on:click="mulaiTur({{ \Illuminate\Support\Js::from(__('Panduan belum tersedia di halaman ini.')) }})" class="inline-flex size-10 items-center justify-center rounded-lg hover:bg-zinc-100 dark:hover:bg-zinc-700" aria-label="{{ __('Panduan') }}" data-tur="tombol-panduan-hp">
                <flux:icon.question-mark-circle class="size-5" />
            </button>

            <a href="{{ route('notifikasi.index') }}" wire:navigate data-tur="menu-notifikasi-hp" class="relative me-2 inline-flex size-10 items-center justify-center rounded-lg hover:bg-zinc-100 dark:hover:bg-zinc-700" aria-label="{{ $jumlahNotifikasiBaru ? __('Notifikasi, :jumlah belum dibaca', ['jumlah' => $jumlahNotifikasiBaru]) : __('Notifikasi') }}">
                <flux:icon.bell class="size-5" />
                @if ($jumlahNotifikasiBaru)
                    <span class="absolute end-1 top-1 inline-flex min-w-4 items-center justify-center rounded-full bg-sky-600 px-1 text-xs font-semibold text-white">{{ $jumlahNotifikasiBaru > 9 ? '9+' : $jumlahNotifikasiBaru }}</span>
                @endif
            </a>

            <flux:dropdown position="bottom" align="end">
                <button type="button" class="rounded-full p-0.5 transition hover:ring-2 hover:ring-indigo-500/40" aria-label="{{ __('Menu akun') }}" data-test="menu-akun-hp">
                    <x-inisial :nama="auth()->user()->name" />
                </button>

                @include('partials.menu-profil')
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <x-toast-urunan />
            <x-dialog-konfirmasi />
            <x-indikator-memuat />
        @endpersist

        @fluxScripts
    </body>
</html>
