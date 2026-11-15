<?php

use App\Models\AnggotaGrup;
use App\Models\Grup;
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
                'nama.required' => 'Nama grup wajib diisi.',
                'nama.max' => 'Nama grup maksimal 100 karakter.',
            ],
        );

        $grup = Grup::buatUntuk(Auth::user(), $validated['nama']);

        $this->redirectRoute('grup.show', $grup, navigate: true);
    }
}; ?>

<section class="w-full">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">Grup patungan</flux:heading>
            <flux:subheading size="lg">Halo, {{ auth()->user()->name }}</flux:subheading>
        </div>

        <flux:modal.trigger name="buat-grup">
            <flux:button variant="primary" icon="plus" data-test="buka-buat-grup">Buat grup baru</flux:button>
        </flux:modal.trigger>
    </div>

    <div class="mb-8 grid gap-4 sm:grid-cols-2">
        <flux:card>
            <flux:text>Harus kamu bayar</flux:text>
            <x-rupiah :jumlah="$this->ringkasan['harusBayar']" class="text-2xl font-bold text-red-600 dark:text-red-400" />
        </flux:card>

        <flux:card>
            <flux:text>Akan kamu terima</flux:text>
            <x-rupiah :jumlah="$this->ringkasan['akanTerima']" class="text-2xl font-bold text-green-700 dark:text-green-400" />
        </flux:card>
    </div>

    <flux:heading size="lg" level="2" class="mb-4">Daftar grup</flux:heading>

    @if ($this->daftarGrup->isEmpty())
        <flux:card>
            <flux:text>Kamu belum punya grup. Buat grup pertama untuk mulai mencatat patungan.</flux:text>
        </flux:card>
    @else
        <div class="grid gap-4 md:grid-cols-2">
            @foreach ($this->daftarGrup as $baris)
                <a href="{{ route('grup.show', $baris['grup']) }}" wire:navigate wire:key="grup-{{ $baris['grup']->id }}" class="block">
                    <flux:card class="h-full hover:bg-zinc-50 dark:hover:bg-zinc-700">
                        <flux:heading size="lg" level="3">{{ $baris['grup']->nama }}</flux:heading>
                        <flux:text class="mb-3">{{ $baris['grup']->anggota_count }} orang, {{ $baris['grup']->tagihan_count }} tagihan</flux:text>

                        @if ($baris['saldo'] < 0)
                            <flux:badge color="red">Kamu utang <x-rupiah :jumlah="$baris['saldo']" class="ms-1" /></flux:badge>
                        @elseif ($baris['saldo'] > 0)
                            <flux:badge color="green">Ditunggu <x-rupiah :jumlah="$baris['saldo']" class="ms-1" /></flux:badge>
                        @else
                            <flux:badge color="zinc">Semua lunas</flux:badge>
                        @endif
                    </flux:card>
                </a>
            @endforeach
        </div>
    @endif

    <flux:modal name="buat-grup" focusable class="max-w-lg">
        <form wire:submit="buatGrup" class="space-y-6">
            <div>
                <flux:heading size="lg">Buat grup baru</flux:heading>
                <flux:subheading>Contoh: Liburan Pangandaran, Kos bulan November.</flux:subheading>
            </div>

            <flux:input wire:model="nama" label="Nama grup" type="text" required autofocus />

            <div class="flex justify-end space-x-2 rtl:space-x-reverse">
                <flux:modal.close>
                    <flux:button variant="filled">Batal</flux:button>
                </flux:modal.close>

                <flux:button variant="primary" type="submit" data-test="simpan-grup">Buat grup</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
