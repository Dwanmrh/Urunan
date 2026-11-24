@props([
    'nama' => '',
    'kotak' => false,
])

@php
    $warna = [
        'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300',
        'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
        'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
        'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300',
        'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300',
        'bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300',
    ][crc32((string) $nama) % 6];

    $huruf = \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr(trim((string) $nama), 0, 1)) ?: '?';
@endphp

{{-- Lingkaran (orang) atau kotak membulat (grup) berisi huruf pertama nama, warnanya tetap untuk nama yang sama. --}}
<span {{ $attributes->class([
    'flex size-9 shrink-0 items-center justify-center text-sm font-semibold',
    $kotak ? 'rounded-xl' : 'rounded-full',
    $warna,
]) }} aria-hidden="true">{{ $huruf }}</span>
