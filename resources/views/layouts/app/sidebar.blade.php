<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        @php($jumlahNotifikasiBaru = auth()->user()->unreadNotifications()->count())

        <flux:sidebar sticky collapsible class="max-lg:hidden! border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse />
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

            <x-pilih-bahasa class="px-1 in-data-flux-sidebar-collapsed-desktop:hidden" data-tur="pilih-bahasa" />

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="sticky top-0 z-30 border-b border-zinc-200/80 bg-white/90 pt-[env(safe-area-inset-top)] backdrop-blur-lg lg:hidden dark:border-zinc-700/80 dark:bg-zinc-800/90">
            <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-2" aria-label="Udunan">
                <img src="/ikon/ikon-96.png" alt="" class="size-8 rounded-lg">
                <span class="font-bold tracking-tight text-zinc-900 dark:text-white">Udunan</span>
            </a>

            <flux:spacer />

            <button type="button" x-on:click="mulaiTur({{ \Illuminate\Support\Js::from(__('Panduan belum tersedia di halaman ini.')) }})" class="inline-flex size-10 items-center justify-center rounded-xl text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-700" aria-label="{{ __('Panduan') }}" data-tur="tombol-panduan-hp">
                <flux:icon.question-mark-circle class="size-6" />
            </button>

            <flux:dropdown position="bottom" align="end">
                <button type="button" class="rounded-full p-0.5 transition hover:ring-2 hover:ring-indigo-500/40" aria-label="{{ __('Menu akun') }}" data-test="menu-akun-hp">
                    <x-inisial :nama="auth()->user()->name" />
                </button>

                @include('partials.menu-profil')
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @include('partials.navigasi-bawah')

        @persist('toast')
            <x-toast-urunan />
            <x-dialog-konfirmasi />
            <x-indikator-memuat />
        @endpersist

        @fluxScripts
    </body>
</html>
