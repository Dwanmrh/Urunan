{{-- Isi menu profil: kartu akun, pintasan, pilihan tema, dan keluar. Dipakai di sidebar laptop dan header HP. --}}
@php($pengguna = auth()->user())

<flux:menu class="w-72 p-0!">
    <div class="relative overflow-hidden rounded-t-lg bg-linear-to-br from-indigo-600 via-indigo-600 to-violet-600 px-4 pt-4 pb-5 text-white">
        <div aria-hidden="true" class="absolute -top-10 -right-8 size-28 rounded-full bg-white/15 blur-xl"></div>
        <div class="relative flex items-center gap-3">
            <x-inisial :nama="$pengguna->name" class="size-11 text-base ring-2 ring-white/50" />
            <div class="min-w-0">
                <p class="truncate font-semibold">{{ $pengguna->name }}</p>
                <p class="truncate text-sm text-white/90">{{ $pengguna->email }}</p>
            </div>
        </div>
    </div>

    <div class="p-1.5">
        <flux:menu.item :href="route('profile.edit')" icon="user-circle" wire:navigate>{{ __('Profile') }}</flux:menu.item>
        <flux:menu.item :href="route('pembayaran.edit')" icon="credit-card" wire:navigate>
            {{ __('Info pembayaran') }}
            @if ($pengguna->infoBayar() === [])
                <span class="ms-auto rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800 dark:bg-amber-500/15 dark:text-amber-300">{{ __('Belum diisi') }}</span>
            @endif
        </flux:menu.item>
        <flux:menu.item :href="route('security.edit')" icon="shield-check" wire:navigate>{{ __('Security') }}</flux:menu.item>
        <x-pasang-aplikasi gaya="menu" />
        @can('lihat-statistik')
            <flux:menu.item :href="route('statistik.index')" icon="chart-bar" wire:navigate>{{ __('Statistik') }}</flux:menu.item>
        @endcan
    </div>

    <div class="border-t border-zinc-100 px-3 py-2.5 dark:border-zinc-700">
        <div class="mb-3 flex items-center justify-between gap-2">
            <p class="text-xs font-semibold text-zinc-600 dark:text-zinc-400">{{ __('Bahasa') }}</p>
            <x-pilih-bahasa class="text-xs" />
        </div>
        <p class="mb-2 text-xs font-semibold text-zinc-600 dark:text-zinc-400">{{ __('Tampilan') }}</p>
        <div class="grid grid-cols-3 gap-1 rounded-xl bg-zinc-100 p-1 dark:bg-zinc-900" role="radiogroup" aria-label="{{ __('Tampilan') }}">
            @foreach (['light' => ['sun', __('Light')], 'dark' => ['moon', __('Dark')], 'system' => ['computer-desktop', __('Otomatis')]] as $mode => [$ikon, $label])
                <button
                    type="button"
                    role="radio"
                    x-on:click="$flux.appearance = '{{ $mode }}'"
                    x-bind:aria-checked="$flux.appearance === '{{ $mode }}'"
                    x-bind:class="$flux.appearance === '{{ $mode }}' ? 'bg-white text-zinc-900 shadow-sm dark:bg-zinc-700 dark:text-white' : 'text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white'"
                    class="flex items-center justify-center gap-1.5 rounded-lg px-2 py-1.5 text-xs font-semibold transition"
                >
                    <flux:icon :icon="$ikon" variant="micro" />
                    {{ $label }}
                </button>
            @endforeach
        </div>
    </div>

    <div class="border-t border-zinc-100 p-1.5 dark:border-zinc-700">
        <form method="POST" action="{{ route('logout') }}" class="w-full">
            @csrf
            <flux:menu.item
                as="button"
                type="submit"
                icon="arrow-right-start-on-rectangle"
                variant="danger"
                class="w-full cursor-pointer"
                data-test="logout-button"
            >
                {{ __('Log out') }}
            </flux:menu.item>
        </form>
    </div>
</flux:menu>
