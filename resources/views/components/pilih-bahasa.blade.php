<nav {{ $attributes->class('flex items-center gap-1 text-sm') }} aria-label="{{ __('Pilih bahasa') }}">
    @foreach (config('urunan.bahasa') as $kode => $nama)
        <a
            href="{{ route('bahasa.ganti', $kode) }}"
            title="{{ $nama }}"
            lang="{{ $kode }}"
            @if (app()->getLocale() === $kode) aria-current="true" @endif
            @class([
                'inline-flex min-h-8 min-w-9 items-center justify-center rounded-md px-2 font-semibold',
                'bg-accent text-accent-foreground' => app()->getLocale() === $kode,
                'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-700' => app()->getLocale() !== $kode,
            ])
        >
            {{ strtoupper($kode) }}
        </a>
    @endforeach
</nav>
