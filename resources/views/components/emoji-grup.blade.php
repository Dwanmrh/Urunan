@props([
    'grup',
    'ukuran' => 'md',
])

{{-- Emoji grup di kotak membulat berwarna lembut sesuai warna grup. --}}
<span {{ $attributes->class([
    'flex shrink-0 items-center justify-center rounded-2xl',
    $grup->warna->lembut(),
    'size-10 text-xl' => $ukuran === 'md',
    'size-14 text-3xl' => $ukuran === 'lg',
    'size-8 text-base rounded-xl' => $ukuran === 'sm',
]) }} aria-hidden="true">{{ $grup->emoji }}</span>
