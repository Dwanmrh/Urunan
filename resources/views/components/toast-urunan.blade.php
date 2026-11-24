{{--
    Notifikasi singkat (toast). Menangkap event "toast-show" dari Flux::toast() di PHP,
    atau dari JavaScript: $dispatch('toast-show', { slots: { text: '...' }, dataset: { variant: 'success' } }).
--}}
<div
    x-data="{
        daftar: [],
        tambah(isi) {
            const toast = {
                id: Date.now() + Math.random(),
                teks: isi.slots?.text ?? '',
                judul: isi.slots?.heading ?? '',
                varian: isi.dataset?.variant ?? 'info',
                durasi: isi.duration ?? 5000,
                tampil: false,
            };
            this.daftar.push(toast);
            if (this.daftar.length > 4) this.daftar.shift();
            requestAnimationFrame(() => {
                const ada = this.daftar.find((item) => item.id === toast.id);
                if (ada) ada.tampil = true;
            });
        },
        hapus(id) {
            const toast = this.daftar.find((item) => item.id === id);
            if (! toast) return;
            toast.tampil = false;
            setTimeout(() => this.daftar = this.daftar.filter((item) => item.id !== id), 200);
        },
    }"
    x-on:toast-show.window="tambah($event.detail)"
    class="pointer-events-none fixed inset-x-4 top-4 z-70 flex flex-col items-center gap-2 sm:inset-x-auto sm:inset-e-4 sm:top-auto sm:bottom-4 sm:items-end"
    aria-live="polite"
>
    <template x-for="toast in daftar" :key="toast.id">
        <div
            x-show="toast.tampil"
            x-transition:enter="transition duration-300 ease-out"
            x-transition:enter-start="-translate-y-2 opacity-0 sm:translate-x-4 sm:translate-y-0"
            x-transition:enter-end="translate-x-0 translate-y-0 opacity-100"
            x-transition:leave="transition duration-200 ease-in"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0 scale-95"
            role="status"
            data-test="toast"
            class="group pointer-events-auto relative w-full overflow-hidden rounded-xl bg-white shadow-lg ring-1 ring-zinc-900/10 sm:w-96 dark:bg-zinc-800 dark:ring-white/10"
        >
            <div class="flex items-start gap-3 p-3.5">
                <div
                    class="flex size-8 shrink-0 items-center justify-center rounded-full"
                    :class="{
                        'bg-emerald-100 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400': toast.varian === 'success',
                        'bg-amber-100 text-amber-600 dark:bg-amber-500/15 dark:text-amber-400': toast.varian === 'warning',
                        'bg-red-100 text-red-600 dark:bg-red-500/15 dark:text-red-400': toast.varian === 'danger',
                        'bg-indigo-100 text-indigo-600 dark:bg-indigo-500/15 dark:text-indigo-300': ! ['success', 'warning', 'danger'].includes(toast.varian),
                    }"
                >
                    <flux:icon.check-circle x-show="toast.varian === 'success'" variant="mini" class="size-5" />
                    <flux:icon.exclamation-triangle x-show="toast.varian === 'warning'" variant="mini" class="size-5" />
                    <flux:icon.x-circle x-show="toast.varian === 'danger'" variant="mini" class="size-5" />
                    <flux:icon.information-circle x-show="! ['success', 'warning', 'danger'].includes(toast.varian)" variant="mini" class="size-5" />
                </div>

                <div class="min-w-0 flex-1 pt-1">
                    <p class="text-sm font-semibold text-zinc-900 dark:text-white" x-show="toast.judul" x-text="toast.judul"></p>
                    <p class="text-sm text-zinc-700 dark:text-zinc-200" x-text="toast.teks"></p>
                </div>

                <button
                    type="button"
                    x-on:click="hapus(toast.id)"
                    class="-m-1 rounded-md p-1 text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-700 dark:hover:text-white"
                    aria-label="{{ __('Tutup') }}"
                >
                    <flux:icon.x-mark variant="mini" class="size-4" />
                </button>
            </div>

            <div
                x-show="toast.durasi > 0"
                x-on:animationend="hapus(toast.id)"
                :style="`animation-duration: ${toast.durasi}ms`"
                class="absolute inset-x-0 bottom-0 h-1 origin-left animate-susut group-hover:[animation-play-state:paused]"
                :class="{
                    'bg-emerald-500': toast.varian === 'success',
                    'bg-amber-500': toast.varian === 'warning',
                    'bg-red-500': toast.varian === 'danger',
                    'bg-indigo-500': ! ['success', 'warning', 'danger'].includes(toast.varian),
                }"
            ></div>
        </div>
    </template>
</div>
