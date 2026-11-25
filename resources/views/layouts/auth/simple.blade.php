<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
        <meta name="robots" content="noindex">
    </head>
    <body class="min-h-screen bg-white text-zinc-700 antialiased dark:bg-zinc-950 dark:text-zinc-300">
        {{-- Cahaya latar, sama dengan halaman depan --}}
        <div aria-hidden="true" class="pointer-events-none fixed inset-x-0 top-0 -z-10 h-[34rem] overflow-hidden">
            <div class="absolute -top-48 left-1/2 h-[34rem] w-[56rem] -translate-x-1/2 rounded-full bg-indigo-500/20 blur-3xl dark:bg-indigo-600/25"></div>
            <div class="absolute -top-24 right-[-8rem] h-72 w-72 rounded-full bg-fuchsia-400/10 blur-3xl"></div>
        </div>

        <div class="flex min-h-svh flex-col">
            <header class="mx-auto flex w-full max-w-6xl items-center justify-between gap-4 px-5 py-5 sm:px-8">
                <x-app-logo href="{{ route('home') }}" />
                <x-pilih-bahasa />
            </header>

            <main class="flex flex-1 items-center justify-center px-5 py-8">
                <div class="w-full max-w-md motion-safe:animate-[muncul_0.5s_ease-out_both]">
                    <div class="rounded-3xl border border-zinc-200 bg-white/90 p-6 shadow-xl shadow-indigo-500/5 backdrop-blur sm:p-8 dark:border-white/10 dark:bg-zinc-900/80">
                        {{ $slot }}
                    </div>
                </div>
            </main>

            <footer class="px-5 py-6 text-center text-xs text-zinc-600 dark:text-zinc-400">
                &copy; {{ now()->year }} {{ config('app.name') }}. {{ __('Patungan tanpa ribet') }}.
            </footer>
        </div>

        @persist('toast')
            <x-toast-urunan />
            <x-dialog-konfirmasi />
            <x-indikator-memuat />
        @endpersist

        @fluxScripts
    </body>
</html>
