<?php

use App\Models\AnggotaGrup;
use App\Models\Grup;
use App\Support\BatasAksi;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Grup patungan')] class extends Component {
    public string $nama = '';

    /**
     * Grup yang diikuti user beserta saldo user di tiap grup.
     *
     * @return Collection<int, array{grup: Grup, saldo: int}>
     */
    #[Computed]
    public function daftarGrup(): Collection
    {
        return Auth::user()->anggotaGrup()
            ->with(['grup' => fn ($query) => $query->withCount(['anggota', 'tagihan'])])
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
            ['nama' => ['required', 'string', 'max:100']],
            [
                'nama.required' => __('Nama grup wajib diisi.'),
                'nama.max' => __('Nama grup maksimal 100 karakter.'),
            ],
        );

        if (! BatasAksi::boleh('buat-grup', 10)) {
            return;
        }

        $grup = Grup::buatUntuk(Auth::user(), $validated['nama']);

        $this->redirectRoute('grup.show', $grup, navigate: true);
    }
}; ?>

<section class="w-full">
    <x-tur nama="grup" :langkah="[
        ['elemen' => '[data-tur=buat-grup]', 'judul' => __('Buat grup'), 'isi' => __('Satu grup untuk satu acara atau lingkaran teman, misalnya liburan atau kos. Kamu otomatis jadi pengelolanya.'), 'sisi' => 'left'],
        ['elemen' => '[data-tur=ringkasan-grup]', 'judul' => __('Ringkasan semua grup'), 'isi' => __('Total yang harus kamu bayar dan yang akan kamu terima dari semua grup.')],
        ['judul' => __('Langkah berikutnya'), 'isi' => __('Buka salah satu grup untuk menambah anggota, membagikan link undangan, dan mencatat tagihan.')],
    ]" />

    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Grup patungan') }}</flux:heading>
            <flux:subheading size="lg">{{ __('Halo, :nama', ['nama' => auth()->user()->name]) }}</flux:subheading>
        </div>

        <flux:modal.trigger name="buat-grup">
            <flux:button variant="primary" icon="plus" data-test="buka-buat-grup" data-tur="buat-grup">{{ __('Buat grup baru') }}</flux:button>
        </flux:modal.trigger>
    </div>

    <div class="mb-8 grid gap-4 sm:grid-cols-2" data-tur="ringkasan-grup">
        <flux:card>
            <flux:text>{{ __('Harus kamu bayar') }}</flux:text>
            <x-rupiah :jumlah="$this->ringkasan['harusBayar']" class="text-2xl font-bold text-red-600 dark:text-red-400" />
        </flux:card>

        <flux:card>
            <flux:text>{{ __('Akan kamu terima') }}</flux:text>
            <x-rupiah :jumlah="$this->ringkasan['akanTerima']" class="text-2xl font-bold text-green-700 dark:text-green-400" />
        </flux:card>
    </div>

    <flux:heading size="lg" level="2" class="mb-4">{{ __('Daftar grup') }}</flux:heading>

    @if ($this->daftarGrup->isEmpty())
        <flux:card>
            <flux:text>{{ __('Kamu belum punya grup. Buat grup pertama untuk mulai mencatat patungan.') }}</flux:text>
        </flux:card>
    @else
        <div class="grid gap-4 md:grid-cols-2">
            @foreach ($this->daftarGrup as $baris)
                <a href="{{ route('grup.show', $baris['grup']) }}" wire:navigate wire:key="grup-{{ $baris['grup']->id }}" class="block">
                    <flux:card class="h-full hover:bg-zinc-50 dark:hover:bg-zinc-700">
                        <flux:heading size="lg" level="3">{{ $baris['grup']->nama }}</flux:heading>
                        <flux:text class="mb-3">{{ __(':orang orang, :tagihan tagihan', ['orang' => $baris['grup']->anggota_count, 'tagihan' => $baris['grup']->tagihan_count]) }}</flux:text>

                        @if ($baris['saldo'] < 0)
                            <flux:badge color="red">{{ __('Kamu utang :jumlah', ['jumlah' => \App\Actions\Urunan\FormatRupiah::rp($baris['saldo'])]) }}</flux:badge>
                        @elseif ($baris['saldo'] > 0)
                            <flux:badge color="green">{{ __('Ditunggu :jumlah', ['jumlah' => \App\Actions\Urunan\FormatRupiah::rp($baris['saldo'])]) }}</flux:badge>
                        @else
                            <flux:badge color="zinc">{{ __('Semua lunas') }}</flux:badge>
                        @endif
                    </flux:card>
                </a>
            @endforeach
        </div>
    @endif

    <flux:modal name="buat-grup" focusable class="max-w-lg">
        <form wire:submit="buatGrup" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Buat grup baru') }}</flux:heading>
                <flux:subheading>{{ __('Contoh: Liburan Pangandaran, Kos bulan November.') }}</flux:subheading>
            </div>

            <flux:input wire:model="nama" label="{{ __('Nama grup') }}" type="text" required autofocus />

            <div class="flex justify-end space-x-2 rtl:space-x-reverse">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Batal') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="primary" type="submit" data-test="simpan-grup">{{ __('Buat grup') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
