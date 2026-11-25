<flux:dropdown position="top" align="start" {{ $attributes }}>
    <button type="button" class="group flex w-full items-center gap-3 rounded-xl p-2 text-start transition hover:bg-zinc-100 in-data-flux-sidebar-collapsed-desktop:justify-center in-data-flux-sidebar-collapsed-desktop:p-0.5 dark:hover:bg-white/5" data-test="sidebar-menu-button">
        <x-inisial :nama="auth()->user()->name" />
        <span class="min-w-0 flex-1 in-data-flux-sidebar-collapsed-desktop:hidden">
            <span class="block truncate text-sm font-semibold text-zinc-900 dark:text-white">{{ auth()->user()->name }}</span>
            <span class="block truncate text-xs text-zinc-600 dark:text-zinc-400">{{ auth()->user()->email }}</span>
        </span>
        <flux:icon.chevron-up-down variant="micro" class="shrink-0 in-data-flux-sidebar-collapsed-desktop:hidden text-zinc-500 transition group-hover:text-zinc-900 dark:text-zinc-400 dark:group-hover:text-white" />
    </button>

    @include('partials.menu-profil')
</flux:dropdown>
