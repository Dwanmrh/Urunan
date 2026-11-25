<?php

use App\Enums\WarnaGrup;
use App\Models\AnggotaGrup;
use App\Models\Grup;
use App\Support\BatasAksi;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Grup patungan')] class extends Component {
    public string $nama = '';

    public string $emoji = '🎉';

    public string $warna = 'indigo';

    /**
     * Grup yang diikuti user beserta saldo user di tiap grup.
     *
     * @return Collection<int, array{grup: Grup, saldo: int}>
     */
    #[Computed]
    public function daftarGrup(): Collection
    {
        return Auth::user()->anggotaGrup()
            ->with(['grup' => fn ($query) => $query->withCount(['anggota', 'tagihan'])->with(['anggota' => fn ($anggota) => $anggota->orderBy('id')])])
            ->latest('id')
            ->get()
            ->map(fn (AnggotaGrup $anggota): array => [
                'grup' => $anggota->grup,
                'saldo' => $anggota->grup->saldo()[$anggota->id] ?? 0,
            ]);
    }

    /**
     * Total yang harus dibayar dan yang akan diterima user dari semua grup.
     *
     * @return array{harusBayar: int, akanTerima: int}
     */
    #[Computed]
    public function ringkasan(): array
    {
        $saldo = $this->daftarGrup->pluck('saldo');

        return [
            'harusBayar' => (int) abs($saldo->filter(fn (int $jumlah): bool => $jumlah < 0)->sum()),
            'akanTerima' => (int) $saldo->filter(fn (int $jumlah): bool => $jumlah > 0)->sum(),
        ];
    }

    /**
     * Buat grup baru lalu buka halamannya.
     */
    public function buatGrup(): void
    {
        $validated = $this->validate(
            [
                'nama' => ['required', 'string', 'max:100'],
                'emoji' => ['required', Rule::in(WarnaGrup::emoji())],
                'warna' => ['required', Rule::enum(WarnaGrup::class)],
            ],
            [
                'nama.required' => __('Nama grup wajib diisi.'),
                'nama.max' => __('Nama grup maksimal 100 karakter.'),
                'emoji.in' => __('Pilih emoji dari daftar.'),
                'warna.enum' => __('Pilih warna dari daftar.'),
            ],
        );

        if (! BatasAksi::boleh('buat-grup', 10)) {
            return;
        }

        $grup = Grup::buatUntuk(Auth::user(), $validated['nama'], $validated['emoji'], WarnaGrup::from($validated['warna']));

        $this->redirectRoute('grup.show', $grup, navigate: true);
    }
}; ?>

<section class="w-full">
    <x-tur nama="grup" :langkah="[
        ['elemen' => '[data-tur=buat-grup]', 'judul' => __('Buat grup'), 'isi' => __('Satu grup untuk satu acara atau lingkaran teman, misalnya liburan atau kos. Kamu otomatis jadi pengelolanya.'), 'sisi' => 'left'],
        ['elemen' => '[data-tur=ringkasan-grup]', 'judul' => __('Ringkasan semua grup'), 'isi' => __('Total yang harus kamu bayar dan yang akan kamu terima dari semua grup.')],
        ['judul' => __('Langkah berikutnya'), 'isi' => __('Buka salah satu grup untuk menambah anggota, membagikan link undangan, dan mencatat tagihan.')],
    ]" />

    <x-kepala-halaman :judul="__('Grup patungan')" :sub="__('Satu grup untuk satu circle: kos, trip, nongkrong, atau langganan bareng.')">
        <x-slot:aksi>
            <flux:modal.trigger name="buat-grup">
                <flux:button variant="primary" icon="plus" data-test="buka-buat-grup" data-tur="buat-grup">{{ __('Buat grup baru') }}</flux:button>
            </flux:modal.trigger>
        </x-slot:aksi>
    </x-kepala-halaman>

    @if ($this->daftarGrup->isNotEmpty())
        <div class="mb-6 grid grid-cols-2 gap-3 sm:gap-4" data-tur="ringkasan-grup">
            <div class="rounded-2xl border border-zinc-200 bg-white p-4 sm:p-5 dark:border-zinc-700/70 dark:bg-zinc-900">
                <p class="flex items-center gap-1.5 text-sm text-zinc-600 dark:text-zinc-400"><flux:icon.arrow-up-right variant="micro" class="text-red-500" />{{ __('Harus kamu bayar') }}</p>
                <x-rupiah :jumlah="$this->ringkasan['harusBayar']" class="mt-2 block text-xl font-bold text-red-600 tabular-nums sm:text-2xl dark:text-red-400" />
            </div>
            <div class="rounded-2xl border border-zinc-200 bg-white p-4 sm:p-5 dark:border-zinc-700/70 dark:bg-zinc-900">
                <p class="flex items-center gap-1.5 text-sm text-zinc-600 dark:text-zinc-400"><flux:icon.arrow-down-left variant="micro" class="text-emerald-500" />{{ __('Akan kamu terima') }}</p>
                <x-rupiah :jumlah="$this->ringkasan['akanTerima']" class="mt-2 block text-xl font-bold text-emerald-600 tabular-nums sm:text-2xl dark:text-emerald-400" />
            </div>
        </div>
    @endif

    @if ($this->daftarGrup->isEmpty())
        <div class="rounded-3xl border border-dashed border-zinc-300 px-6 py-14 text-center dark:border-zinc-700">
            <div class="mx-auto flex w-fit gap-2 text-4xl" aria-hidden="true">
                <span class="motion-safe:animate-bounce">🍜</span>
                <span class="motion-safe:animate-bounce [animation-delay:150ms]">🏠</span>
                <span class="motion-safe:animate-bounce [animation-delay:300ms]">✈️</span>
            </div>
            <h2 class="mt-5 text-xl font-semibold text-zinc-900 dark:text-white">{{ __('Belum ada grup') }}</h2>
            <p class="mx-auto mt-2 max-w-md text-sm text-zinc-600 dark:text-zinc-400">{{ __('Bikin grup pertamamu, lalu kirim link undangannya ke grup WhatsApp. Kalau temanmu sudah kirim link, cukup buka link itu.') }}</p>
            <flux:modal.trigger name="buat-grup">
                <flux:button variant="primary" icon="plus" class="mt-6">{{ __('Buat grup pertama') }}</flux:button>
            </flux:modal.trigger>
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($this->daftarGrup as $urutan => $baris)
                @php($grup = $baris['grup'])
                <a
                    href="{{ route('grup.show', $grup) }}"
                    wire:navigate
                    wire:key="grup-{{ $grup->id }}"
                    class="group relative flex flex-col overflow-hidden rounded-2xl border border-zinc-200 bg-white transition duration-200 hover:-translate-y-1 hover:shadow-xl hover:shadow-zinc-900/5 motion-safe:animate-[muncul_0.4s_ease-out_both] dark:border-zinc-700/70 dark:bg-zinc-900 dark:hover:shadow-black/30"
                    style="animation-delay: {{ min($urutan, 8) * 50 }}ms"
                    data-test="kartu-grup"
                >
                    <div class="relative h-16 bg-linear-to-br {{ $grup->warna->gradasi() }}">
                        <div aria-hidden="true" class="absolute -top-8 -right-6 size-24 rounded-full bg-white/15 blur-xl"></div>
                    </div>

                    <div class="flex flex-1 flex-col px-5 pb-5">
                        <span class="relative z-10 -mt-7 flex size-14 items-center justify-center rounded-2xl bg-white text-3xl shadow-md ring-4 ring-white transition group-hover:scale-110 group-hover:-rotate-6 dark:bg-zinc-800 dark:ring-zinc-900" aria-hidden="true">{{ $grup->emoji }}</span>

                        <h3 class="mt-3 truncate text-lg font-semibold text-zinc-900 dark:text-white">{{ $grup->nama }}</h3>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ __(':orang orang, :tagihan tagihan', ['orang' => $grup->anggota_count, 'tagihan' => $grup->tagihan_count]) }}</p>

                        <div class="mt-4 flex items-center justify-between gap-3">
                            <div class="flex -space-x-2">
                                @foreach ($grup->anggota->take(4) as $orang)
                                    <x-inisial :nama="$orang->nama" class="size-7 text-xs ring-2 ring-white dark:ring-zinc-900" />
                                @endforeach
                                @if ($grup->anggota_count > 4)
                                    <span class="flex size-7 items-center justify-center rounded-full bg-zinc-100 text-xs font-semibold text-zinc-600 ring-2 ring-white dark:bg-zinc-800 dark:text-zinc-300 dark:ring-zinc-900">+{{ $grup->anggota_count - 4 }}</span>
                                @endif
                            </div>

                            @if ($baris['saldo'] < 0)
                                <span class="rounded-full bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-700 dark:bg-red-500/10 dark:text-red-300">{{ __('Kamu utang :jumlah', ['jumlah' => \App\Actions\Urunan\FormatRupiah::rp($baris['saldo'])]) }}</span>
                            @elseif ($baris['saldo'] > 0)
                                <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">{{ __('Ditunggu :jumlah', ['jumlah' => \App\Actions\Urunan\FormatRupiah::rp($baris['saldo'])]) }}</span>
                            @else
                                <span class="rounded-full bg-zinc-100 px-2.5 py-1 text-xs font-semibold text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">{{ __('Semua lunas') }} ✨</span>
                            @endif
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    @endif

    <flux:modal name="buat-grup" focusable class="w-full max-w-lg">
        <form wire:submit="buatGrup" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Buat grup baru') }}</flux:heading>
                <flux:subheading>{{ __('Kasih nama, pilih emoji dan warna biar gampang dikenali.') }}</flux:subheading>
            </div>

            @include('partials.pilih-tampilan-grup')

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Batal') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="primary" type="submit" data-test="simpan-grup">{{ __('Buat grup') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
