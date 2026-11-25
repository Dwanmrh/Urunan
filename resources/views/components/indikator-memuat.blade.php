{{-- Tanda "Memuat..." saat pindah halaman, muncul kalau perpindahan terasa lambat. --}}
<div
    x-data="{ tampil: false, timer: null }"
    x-on:livewire:navigate.document="clearTimeout(timer); timer = setTimeout(() => tampil = true, 250)"
    x-on:livewire:navigated.document="clearTimeout(timer); tampil = false"
>
    <div
        x-show="tampil"
        x-cloak
        x-transition.opacity
        class="pointer-events-none fixed inset-x-0 bottom-24 z-65 lg:bottom-6 flex justify-center"
        role="status"
    >
        <div class="flex items-center gap-2 rounded-full bg-zinc-900/90 px-4 py-2 text-sm font-medium text-white shadow-lg backdrop-blur dark:bg-white/90 dark:text-zinc-900">
            <x-spinner class="size-4" />
            {{ __('Memuat...') }}
        </div>
    </div>
</div>
