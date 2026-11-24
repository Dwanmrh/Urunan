@props([
    'title',
    'description',
    'ikon' => null,
])

<div class="flex w-full flex-col items-center text-center">
    @if ($ikon)
        <span class="mb-4 flex size-12 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600 ring-1 ring-indigo-100 dark:bg-indigo-500/10 dark:text-indigo-300 dark:ring-indigo-500/20">
            <flux:icon :icon="$ikon" class="size-6" />
        </span>
    @endif

    <h1 class="text-2xl font-bold tracking-tight text-zinc-950 dark:text-white">{{ $title }}</h1>
    <p class="mt-1.5 text-sm text-zinc-500 dark:text-zinc-400">{{ $description }}</p>
</div>
