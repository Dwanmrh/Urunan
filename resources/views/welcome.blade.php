<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head', ['title' => 'Patungan tanpa ribet'])
    </head>
    <body class="min-h-screen bg-white antialiased dark:bg-linear-to-b dark:from-neutral-950 dark:to-neutral-900">
        <div class="mx-auto flex min-h-svh max-w-4xl flex-col px-6 py-8">
            <header class="flex items-center justify-between gap-4">
                <x-app-logo href="{{ route('home') }}" />

                <nav class="flex items-center gap-2">
                    @auth
                        <flux:button :href="route('dashboard')" variant="primary" size="sm">Buka Urunan</flux:button>
                    @else
                        <flux:button :href="route('login')" variant="ghost" size="sm">{{ __('Log in') }}</flux:button>
                        @if (Route::has('register'))
                            <flux:button :href="route('register')" variant="primary" size="sm">{{ __('Register') }}</flux:button>
                        @endif
                    @endauth
                </nav>
            </header>

            <main class="flex flex-1 flex-col justify-center gap-10 py-16">
                <div class="max-w-2xl space-y-4">
                    <flux:heading size="xl" level="1" class="!text-4xl !leading-tight sm:!text-5xl">
                        Patungan bareng teman, tanpa hitung manual.
                    </flux:heading>
                    <flux:text size="lg">
                        Catat tagihan makan, liburan, atau kos. Urunan menghitung bagian tiap orang,
                        mencatat siapa yang sudah bayar, dan mengingatkan yang belum.
                    </flux:text>

                    <div class="flex flex-wrap gap-3 pt-2">
                        @auth
                            <flux:button :href="route('dashboard')" variant="primary">Buka Urunan</flux:button>
                        @else
                            <flux:button :href="route('register')" variant="primary">Mulai gratis</flux:button>
                            <flux:button :href="route('login')">{{ __('Log in') }}</flux:button>
                        @endauth
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <flux:card>
                        <flux:heading level="2">Bagi rata atau per item</flux:heading>
                        <flux:text class="mt-1">Yang pesan daging bayar daging, yang ikut semua bayar bagian bersama.</flux:text>
                    </flux:card>
                    <flux:card>
                        <flux:heading level="2">Bukti bayar tercatat</flux:heading>
                        <flux:text class="mt-1">Kirim foto bukti transfer, yang menalangi tinggal konfirmasi.</flux:text>
                    </flux:card>
                    <flux:card>
                        <flux:heading level="2">Satu link untuk grup</flux:heading>
                        <flux:text class="mt-1">Bagikan link undangan ke grup WhatsApp, teman tinggal bergabung.</flux:text>
                    </flux:card>
                </div>
            </main>
        </div>

        @fluxScripts
    </body>
</html>
