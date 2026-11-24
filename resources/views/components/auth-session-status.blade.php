@props([
    'status',
])

@if ($status)
    <div {{ $attributes->merge(['class' => 'flex items-start gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-start text-sm font-medium text-emerald-800 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300']) }} role="status">
        <flux:icon.check-circle variant="mini" class="mt-0.5 size-4 shrink-0" />
        <span>{{ $status }}</span>
    </div>
@endif
