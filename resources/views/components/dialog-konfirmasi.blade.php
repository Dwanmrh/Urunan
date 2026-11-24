{{--
    Dialog konfirmasi pengganti confirm() bawaan browser.

    Pakai dari tombol mana saja di dalam komponen Livewire:
    x-on:click="$dispatch('konfirmasi', { judul: '...', pesan: '...', tombol: '...', bahaya: true, aksi: () => $wire.hapus() })"
--}}
<div
    x-data="{
        buka: false,
        sibuk: false,
        judul: '',
        pesan: '',
        tombol: '',
        bahaya: false,
        aksi: null,
        tampilkan(isi) {
            this.judul = isi.judul ?? '';
            this.pesan = isi.pesan ?? '';
            this.tombol = isi.tombol ?? @js(__('Ya, lanjutkan'));
            this.bahaya = Boolean(isi.bahaya);
            this.aksi = isi.aksi ?? null;
            this.sibuk = false;
            this.buka = true;
        },
        async setuju() {
            if (this.sibuk) return;
            this.sibuk = true;
            try {
                await this.aksi?.();
            } finally {
                this.sibuk = false;
                this.buka = false;
            }
        },
        tutup() {
            if (! this.sibuk) this.buka = false;
        },
    }"
    x-on:konfirmasi.window="tampilkan($event.detail)"
    x-on:keydown.escape.window="buka && tutup()"
>
    <div
        x-show="buka"
        x-cloak
        class="fixed inset-0 z-[60] flex items-end justify-center p-4 sm:items-center"
        role="alertdialog"
        aria-modal="true"
        aria-labelledby="judul-konfirmasi"
        aria-describedby="pesan-konfirmasi"
    >
        <div
            x-show="buka"
            x-transition.opacity.duration.200ms
            x-on:click="tutup()"
            class="absolute inset-0 bg-zinc-950/50 backdrop-blur-sm"
        ></div>

        <div
            x-show="buka"
            x-trap.noscroll="buka"
            x-transition:enter="transition duration-200 ease-out"
            x-transition:enter-start="translate-y-4 scale-95 opacity-0"
            x-transition:enter-end="translate-y-0 scale-100 opacity-100"
            x-transition:leave="transition duration-150 ease-in"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="relative w-full max-w-sm rounded-2xl bg-white p-6 text-center shadow-2xl ring-1 ring-zinc-900/5 dark:bg-zinc-800 dark:ring-white/10"
        >
            <div
                class="mx-auto mb-4 flex size-12 items-center justify-center rounded-full"
                :class="bahaya ? 'bg-red-100 text-red-600 dark:bg-red-500/15 dark:text-red-400' : 'bg-indigo-100 text-indigo-600 dark:bg-indigo-500/15 dark:text-indigo-300'"
            >
                <flux:icon.exclamation-triangle x-show="bahaya" class="size-6" />
                <flux:icon.question-mark-circle x-show="! bahaya" class="size-6" />
            </div>

            <h2 id="judul-konfirmasi" class="text-lg font-semibold text-zinc-900 dark:text-white" x-text="judul"></h2>
            <p id="pesan-konfirmasi" class="mt-2 text-sm text-zinc-600 dark:text-zinc-300" x-show="pesan" x-text="pesan"></p>

            <div class="mt-6 grid grid-cols-2 gap-2">
                <button
                    type="button"
                    x-on:click="tutup()"
                    x-bind:disabled="sibuk"
                    class="inline-flex h-10 items-center justify-center rounded-lg bg-zinc-100 px-4 text-sm font-medium text-zinc-800 transition hover:bg-zinc-200 disabled:opacity-50 dark:bg-zinc-700 dark:text-white dark:hover:bg-zinc-600"
                >
                    {{ __('Batal') }}
                </button>

                <button
                    type="button"
                    x-on:click="setuju()"
                    x-bind:disabled="sibuk"
                    data-test="setuju-konfirmasi"
                    class="inline-flex h-10 items-center justify-center gap-2 rounded-lg px-4 text-sm font-medium text-white transition active:scale-[0.98] disabled:cursor-wait disabled:opacity-80"
                    :class="bahaya ? 'bg-red-600 hover:bg-red-500' : 'bg-indigo-600 hover:bg-indigo-500'"
                >
                    <x-spinner x-show="sibuk" x-cloak class="size-4" />
                    <span x-text="tombol"></span>
                </button>
            </div>
        </div>
    </div>
</div>
