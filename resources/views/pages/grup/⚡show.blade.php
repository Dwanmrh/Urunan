<?php

use App\Actions\Urunan\SederhanakanUtang;
use App\Models\AnggotaGrup;
use App\Models\Grup;
use App\Models\Tagihan;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Detail grup')] class extends Component {
    public Grup $grup;

    public string $namaAnggota = '';

    /**
     * Mount the component.
     */
    public function mount(Grup $grup): void
    {
        $this->grup = $grup;

        $this->pastikanAnggota();
    }

    /**
     * Anggota grup beserta saldonya.
     *
     * @return Collection<int, array{anggota: AnggotaGrup, saldo: int}>
     */
    #[Computed]
    public function anggotaDenganSaldo(): Collection
    {
        $saldo = $this->grup->saldo();

        return $this->grup->anggota()->orderBy('id')->get()
            ->map(fn (AnggotaGrup $anggota): array => [
                'anggota' => $anggota,
                'saldo' => $saldo[$anggota->id] ?? 0,
            ]);
    }

    /**
     * Daftar siapa perlu membayar ke siapa supaya semua lunas.
     *
     * @return list<array{dari: string, ke: string, jumlah: int}>
     */
    #[Computed]
    public function rencanaPelunasan(): array
    {
        $nama = $this->grup->anggota()->pluck('nama', 'id');

        return array_map(
            fn (array $pembayaran): array => [
                'dari' => $nama[$pembayaran['dari']],
                'ke' => $nama[$pembayaran['ke']],
                'jumlah' => $pembayaran['jumlah'],
            ],
            SederhanakanUtang::dariSaldo($this->grup->saldo()),
        );
    }

    /**
     * @return Collection<int, Tagihan>
     */
    #[Computed]
    public function daftarTagihan(): Collection
    {
        return $this->grup->tagihan()->with('pembayar')->latest('tanggal')->latest('id')->get();
    }

    /**
     * Tambah anggota tanpa akun ke grup ini.
     */
    public function tambahAnggota(): void
    {
        $this->pastikanAnggota();

        $validated = $this->validate(
            ['namaAnggota' => ['required', 'string', 'max:60']],
            [
                'namaAnggota.required' => __('Nama anggota wajib diisi.'),
                'namaAnggota.max' => __('Nama anggota maksimal 60 karakter.'),
            ],
        );

        $this->grup->anggota()->create(['nama' => $validated['namaAnggota']]);

        $this->reset('namaAnggota');

        unset($this->anggotaDenganSaldo, $this->rencanaPelunasan);

        Flux::toast(variant: 'success', text: __('Anggota ditambahkan.'));
    }

    /**
     * Tolak akses kalau user bukan anggota grup ini.
     */
    private function pastikanAnggota(): void
    {
        abort_unless($this->grup->punyaAnggota(Auth::user()), 403);
    }
}; ?>

<section class="w-full">
    <div class="mb-6">
        <flux:button :href="route('grup.index')" variant="subtle" icon="arrow-left" size="sm" wire:navigate class="mb-3">{{ __('Semua grup') }}</flux:button>
        <flux:heading size="xl" level="1">{{ $grup->nama }}</flux:heading>
        <flux:subheading size="lg" class="mb-4">{{ __(':jumlah anggota', ['jumlah' => $this->anggotaDenganSaldo->count()]) }}</flux:subheading>

        <div class="max-w-xl">
            <flux:input
                :value="route('grup.gabung', $grup->kode_undangan)"
                label="{{ __('Link undangan') }}"
                description="{{ __('Bagikan ke grup WhatsApp. Teman yang membuka link ini bisa daftar lalu bergabung.') }}"
                readonly
                copyable
            />
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <flux:card class="space-y-4">
            <flux:heading size="lg" level="2">{{ __('Anggota') }}</flux:heading>

            <ul class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @foreach ($this->anggotaDenganSaldo as $baris)
                    <li wire:key="anggota-{{ $baris['anggota']->id }}" class="flex items-center justify-between gap-3 py-3 [&>*:first-child]:min-w-0 [&>*:last-child]:shrink-0">
                        <div>
                            <flux:heading>{{ $baris['anggota']->nama }}</flux:heading>
                            <flux:text>{{ $baris['anggota']->user_id ? __('Punya akun') : __('Tanpa akun') }}</flux:text>
                        </div>

                        @if ($baris['saldo'] < 0)
                            <flux:badge color="red">{{ __('Utang :jumlah', ['jumlah' => \App\Actions\Urunan\FormatRupiah::rp($baris['saldo'])]) }}</flux:badge>
                        @elseif ($baris['saldo'] > 0)
                            <flux:badge color="green">{{ __('Menerima :jumlah', ['jumlah' => \App\Actions\Urunan\FormatRupiah::rp($baris['saldo'])]) }}</flux:badge>
                        @else
                            <flux:badge color="zinc">{{ __('Lunas') }}</flux:badge>
                        @endif
                    </li>
                @endforeach
            </ul>

            <form wire:submit="tambahAnggota" class="flex items-end gap-3">
                <div class="flex-1">
                    <flux:input wire:model="namaAnggota" label="{{ __('Tambah anggota') }}" type="text" placeholder="{{ __('Nama, misalnya Sinta') }}" />
                </div>

                <flux:button variant="primary" type="submit" data-test="tambah-anggota">{{ __('Tambah') }}</flux:button>
            </form>
        </flux:card>

        <div class="space-y-6">
            <flux:card class="space-y-4">
                <flux:heading size="lg" level="2">{{ __('Siapa bayar ke siapa') }}</flux:heading>

                @if ($this->rencanaPelunasan === [])
                    <flux:text>{{ __('Tidak ada yang perlu dibayar.') }}</flux:text>
                @else
                    <ul class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @foreach ($this->rencanaPelunasan as $urutan => $pembayaran)
                            <li wire:key="pelunasan-{{ $urutan }}" class="flex items-center justify-between gap-3 py-3 [&>*:first-child]:min-w-0 [&>*:last-child]:shrink-0">
                                <flux:text>{{ __(':dari membayar ke :ke', ['dari' => $pembayaran['dari'], 'ke' => $pembayaran['ke']]) }}</flux:text>
                                <x-rupiah :jumlah="$pembayaran['jumlah']" class="font-semibold" />
                            </li>
                        @endforeach
                    </ul>
                @endif
            </flux:card>

            <flux:card class="space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <flux:heading size="lg" level="2">{{ __('Tagihan') }}</flux:heading>
                    <flux:button :href="route('tagihan.create', $grup)" variant="primary" size="sm" icon="plus" wire:navigate data-test="tambah-tagihan">{{ __('Tambah tagihan') }}</flux:button>
                </div>

                @if ($this->daftarTagihan->isEmpty())
                    <flux:text>{{ __('Belum ada tagihan di grup ini.') }}</flux:text>
                @else
                    <ul class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @foreach ($this->daftarTagihan as $tagihan)
                            <li wire:key="tagihan-{{ $tagihan->id }}">
                                <a href="{{ route('tagihan.show', [$grup, $tagihan]) }}" wire:navigate class="flex items-center justify-between gap-3 py-3 [&>*:first-child]:min-w-0 [&>*:last-child]:shrink-0 hover:opacity-80">
                                    <div>
                                        <flux:heading>{{ $tagihan->judul }}</flux:heading>
                                        <flux:text>{{ __('Dibayar dulu oleh :nama, :tanggal', ['nama' => $tagihan->pembayar->nama, 'tanggal' => $tagihan->tanggal->format('d/m/Y')]) }}</flux:text>
                                    </div>
                                    <x-rupiah :jumlah="$tagihan->total" class="font-semibold" />
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </flux:card>
        </div>
    </div>
</section>
