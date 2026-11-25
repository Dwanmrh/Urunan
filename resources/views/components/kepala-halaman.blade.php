@props([
    'judul',
    'sub' => null,
    'kembali' => null,
    'kembaliLabel' => null,
])

{{-- Judul halaman: tombol kembali, judul besar, keterangan, dan tombol aksi di kanan. --}}
<header {{ $attributes->class('mb-6 motion-safe:animate-[muncul_0.4s_ease-out_both]') }}>
    @if ($kembali)
        <a href="{{ $kembali }}" wire:navigate class="mb-3 inline-flex max-w-full items-center gap-1.5 rounded-lg px-2 py-1 -ms-2 text-sm font-medium text-zinc-600 dark:text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-900 dark:hover:bg-white/5 dark:hover:text-white">
            <flux:icon.arrow-left variant="micro" class="shrink-0" />
            <span class="truncate">{{ $kembaliLabel }}</span>
        </a>
    @endif

    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="min-w-0">
            <h1 class="text-2xl font-bold tracking-tight break-words text-zinc-900 sm:text-3xl dark:text-white">{{ $judul }}</h1>
            @if ($sub)
                <p class="mt-1 text-zinc-600 dark:text-zinc-400">{{ $sub }}</p>
            @endif
        </div>

        @isset($aksi)
            <div class="flex flex-wrap gap-2">{{ $aksi }}</div>
        @endisset
    </div>
</header>
