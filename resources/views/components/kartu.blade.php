@props([
    'judul' => null,
    'ikon' => null,
    'jumlah' => null,
    'padat' => false,
])

{{-- Kartu dengan gaya yang sama di semua halaman setelah login. --}}
<section {{ $attributes->class('rounded-2xl border border-zinc-200 bg-white motion-safe:animate-[muncul_0.4s_ease-out_both] dark:border-zinc-700/70 dark:bg-zinc-900') }}>
    @if ($judul || isset($aksi))
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-zinc-100 px-5 py-4 dark:border-zinc-800">
            <h2 class="flex min-w-0 items-center gap-2 font-semibold text-zinc-900 dark:text-white">
                @if ($ikon)
                    <span class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-300">
                        <flux:icon :icon="$ikon" variant="mini" class="size-4" />
                    </span>
                @endif
                <span class="truncate">{{ $judul }}</span>
                @if (! is_null($jumlah))
                    <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-semibold text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">{{ $jumlah }}</span>
                @endif
            </h2>

            {{ $aksi ?? '' }}
        </div>
    @endif

    <div @class(['p-5' => ! $padat])>
        {{ $slot }}
    </div>
</section>
