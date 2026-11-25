@php
    $menu = [
        ['rute' => 'profile.edit', 'ikon' => 'user-circle', 'label' => __('Profile')],
        ['rute' => 'pembayaran.edit', 'ikon' => 'credit-card', 'label' => __('Info pembayaran')],
        ['rute' => 'security.edit', 'ikon' => 'shield-check', 'label' => __('Security')],
        ['rute' => 'appearance.edit', 'ikon' => 'swatch', 'label' => __('Appearance')],
        ['rute' => 'telegram.edit', 'ikon' => 'paper-airplane', 'label' => 'Telegram'],
    ];
@endphp

<div class="flex items-start gap-8 max-md:flex-col">
    <nav class="w-full md:w-56 md:shrink-0" aria-label="{{ __('Settings') }}">
        <ul class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 md:mx-0 md:flex-col md:gap-1 md:px-0">
            @foreach ($menu as $item)
                @php($aktif = request()->routeIs($item['rute']))
                <li class="shrink-0">
                    <a
                        href="{{ route($item['rute']) }}"
                        wire:navigate
                        @if ($aktif) aria-current="page" @endif
                        @class([
                            'flex items-center gap-2.5 rounded-xl px-3 py-2 text-sm font-medium whitespace-nowrap transition',
                            'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300' => $aktif,
                            'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-white/5 dark:hover:text-white' => ! $aktif,
                        ])
                    >
                        <flux:icon :icon="$item['ikon']" variant="mini" class="size-4" />
                        {{ $item['label'] }}
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>

    <div class="w-full min-w-0 flex-1 rounded-2xl border border-zinc-200 bg-white p-5 sm:p-6 dark:border-zinc-700/70 dark:bg-zinc-900">
        <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ $heading ?? '' }}</h2>
        @if (filled($subheading ?? null))
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">{{ $subheading }}</p>
        @endif

        <div class="mt-6 w-full max-w-lg">
            {{ $slot }}
        </div>
    </div>
</div>
