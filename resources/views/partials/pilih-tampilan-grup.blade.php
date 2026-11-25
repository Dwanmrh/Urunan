{{-- Isian nama, emoji, dan warna grup beserta pratinjau. Dipakai saat membuat dan mengubah grup. --}}
@php
    $temaPilihan = \App\Enums\WarnaGrup::tryFrom($warna) ?? \App\Enums\WarnaGrup::Indigo;
    $kategoriEmoji = \App\Enums\WarnaGrup::kategoriEmoji();
    $kategoriAwal = collect($kategoriEmoji)->search(fn ($daftar) => in_array($emoji, $daftar, true)) ?: array_key_first($kategoriEmoji);
@endphp

<div x-data class="overflow-hidden rounded-2xl border border-zinc-200 dark:border-zinc-700">
    <div @class(['flex items-center gap-3 bg-linear-to-br p-4 transition-colors', $temaPilihan->gradasi(), $temaPilihan->teks()])>
        <span @class(['flex size-12 items-center justify-center rounded-2xl text-2xl ring-1 backdrop-blur', $temaPilihan->kaca()]) aria-hidden="true">{{ $emoji }}</span>
        <div class="min-w-0">
            <p @class(['text-xs font-semibold', $temaPilihan->teksRedup()])>{{ __('Pratinjau') }} · {{ $temaPilihan->label() }}</p>
            <p class="truncate text-lg font-bold" x-text="$wire.nama || @js(__('Nama grup'))">{{ $nama ?: __('Nama grup') }}</p>
        </div>
    </div>
</div>

<flux:input wire:model="nama" label="{{ __('Nama grup') }}" type="text" placeholder="{{ __('Contoh: Kos Melati, Trip Bali, Spotify Family') }}" required autofocus />

<div x-data="{ kategori: @js($kategoriAwal) }">
    <flux:label>{{ __('Emoji') }}</flux:label>

    <div class="-mx-1 mt-2 flex gap-1.5 overflow-x-auto px-1 pb-1" role="tablist" aria-label="{{ __('Kategori emoji') }}">
        @foreach ($kategoriEmoji as $namaKategori => $daftar)
            <button
                type="button"
                role="tab"
                x-on:click="kategori = @js($namaKategori)"
                x-bind:aria-selected="kategori === @js($namaKategori)"
                x-bind:class="kategori === @js($namaKategori) ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'bg-zinc-100 text-zinc-700 hover:bg-zinc-200 dark:bg-white/10 dark:text-zinc-200 dark:hover:bg-white/15'"
                class="shrink-0 rounded-full px-3 py-1.5 text-xs font-semibold whitespace-nowrap transition"
            >{{ $daftar[0] }} {{ $namaKategori }}</button>
        @endforeach
    </div>

    @foreach ($kategoriEmoji as $namaKategori => $daftar)
        <div x-show="kategori === @js($namaKategori)" x-cloak class="mt-2 grid grid-cols-6 gap-1.5 sm:grid-cols-8" role="radiogroup" aria-label="{{ $namaKategori }}">
            @foreach ($daftar as $pilihan)
                <button
                    type="button"
                    wire:click="$set('emoji', @js($pilihan))"
                    role="radio"
                    aria-checked="{{ $emoji === $pilihan ? 'true' : 'false' }}"
                    aria-label="{{ $pilihan }}"
                    @class([
                        'flex aspect-square items-center justify-center rounded-xl text-2xl transition hover:scale-110 hover:bg-zinc-100 dark:hover:bg-white/10',
                        'bg-indigo-50 ring-2 ring-indigo-600 dark:bg-indigo-500/20 dark:ring-indigo-400' => $emoji === $pilihan,
                    ])
                >{{ $pilihan }}</button>
            @endforeach
        </div>
    @endforeach
    <flux:error name="emoji" />
</div>

<div class="space-y-3">
    <div class="flex items-baseline justify-between gap-2">
        <flux:label>{{ __('Warna') }}</flux:label>
        <span class="text-sm font-semibold text-zinc-900 dark:text-white">{{ $temaPilihan->label() }}</span>
    </div>

    @foreach (\App\Enums\WarnaGrup::kelompok() as $namaKelompok => $daftar)
        <div>
            <p class="mb-1.5 text-xs font-semibold text-zinc-700 dark:text-zinc-300">{{ $namaKelompok }}</p>
            <div class="flex flex-wrap gap-2" role="radiogroup" aria-label="{{ $namaKelompok }}">
                @foreach ($daftar as $pilihan)
                    <button
                        type="button"
                        wire:click="$set('warna', '{{ $pilihan->value }}')"
                        role="radio"
                        aria-checked="{{ $warna === $pilihan->value ? 'true' : 'false' }}"
                        aria-label="{{ $pilihan->label() }}"
                        title="{{ $pilihan->label() }}"
                        data-test="warna-{{ $pilihan->value }}"
                        @class([
                            'relative flex size-9 items-center justify-center rounded-full bg-linear-to-br ring-1 ring-zinc-900/10 transition hover:scale-110 dark:ring-white/15',
                            $pilihan->gradasi(),
                            'ring-2 ring-zinc-900 ring-offset-2 dark:ring-white dark:ring-offset-zinc-900' => $warna === $pilihan->value,
                        ])
                    >
                        @if ($warna === $pilihan->value)
                            <flux:icon.check variant="micro" @class(['size-4', $pilihan->teks()]) />
                        @endif
                    </button>
                @endforeach
            </div>
        </div>
    @endforeach
    <flux:error name="warna" />
</div>
