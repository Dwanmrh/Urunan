<?php

use App\Models\AnggotaGrup;
use App\Models\Grup;
use App\Models\ItemTagihan;
use App\Models\Tagihan;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Detail tagihan')] class extends Component {
    public Grup $grup;

    public Tagihan $tagihan;

    /**
     * Mount the component.
     */
    public function mount(Grup $grup, Tagihan $tagihan): void
    {
        abort_unless($tagihan->grup_id === $grup->id, 404);

        $this->grup = $grup;
        $this->tagihan = $tagihan;

        $this->pastikanAnggota();
    }

    /**
     * @return Collection<int, array{anggota: AnggotaGrup, bagian: int, dibayar: int, sisa: int, pembayar: bool}>
     */
    #[Computed]
    public function statusPelunasan(): Collection
    {
        return $this->tagihan->statusPelunasan();
    }

    /**
     * @return Collection<int, ItemTagihan>
     */
    #[Computed]
    public function daftarItem(): Collection
    {
        return $this->tagihan->item()->with('anggota')->orderBy('id')->get();
    }

    /**
     * Catat bahwa seorang anggota sudah melunasi bagiannya.
     */
    public function tandaiLunas(int $idAnggota): void
    {
        $this->pastikanAnggota();

        $anggota = $this->grup->anggota()->findOrFail($idAnggota);

        if ($this->tagihan->tandaiLunas($anggota) === null) {
            return;
        }

        unset($this->statusPelunasan);

        Flux::toast(variant: 'success', text: $anggota->nama.' ditandai lunas.');
    }

    /**
     * Tolak akses kalau user bukan anggota grup ini.
     */
    private function pastikanAnggota(): void
    {
        abort_unless($this->grup->punyaAnggota(Auth::user()), 403);
    }
}; ?>

<section class="w-full max-w-3xl">
    <div class="mb-6">
        <flux:button :href="route('grup.show', $grup)" variant="subtle" icon="arrow-left" size="sm" wire:navigate class="mb-3">{{ $grup->nama }}</flux:button>
        <flux:heading size="xl" level="1">{{ $tagihan->judul }}</flux:heading>
        <flux:subheading size="lg">
            Dibayar dulu oleh {{ $tagihan->pembayar->nama }}, {{ $tagihan->tanggal->format('d/m/Y') }}, {{ $tagihan->cara_bagi->label() }}
        </flux:subheading>
    </div>

    <flux:card class="mb-6">
        <flux:text>Total tagihan</flux:text>
        <x-rupiah :jumlah="$tagihan->total" class="text-2xl font-bold" />
    </flux:card>

    @if ($tagihan->cara_bagi === \App\Enums\CaraBagi::PerItem)
        <flux:card class="mb-6 space-y-3">
            <flux:heading size="lg" level="2">Rincian item</flux:heading>

            <ul class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @foreach ($this->daftarItem as $item)
                    <li wire:key="item-{{ $item->id }}" class="flex items-center justify-between gap-3 py-3">
                        <div>
                            <flux:heading>{{ $item->nama }}</flux:heading>
                            <flux:text>{{ $item->anggota->isEmpty() ? 'Semua anggota' : $item->anggota->pluck('nama')->join(', ') }}</flux:text>
                        </div>
                        <x-rupiah :jumlah="$item->harga" class="font-semibold" />
                    </li>
                @endforeach
            </ul>
        </flux:card>
    @endif

    <flux:card class="space-y-3">
        <flux:heading size="lg" level="2">Bagian tiap orang</flux:heading>

        <ul class="divide-y divide-zinc-200 dark:divide-zinc-700">
            @foreach ($this->statusPelunasan as $baris)
                <li wire:key="bagian-{{ $baris['anggota']->id }}" class="flex flex-wrap items-center justify-between gap-3 py-3">
                    <div>
                        <flux:heading>{{ $baris['anggota']->nama }}</flux:heading>
                        <flux:text>
                            @if ($baris['pembayar'])
                                Yang membayar dulu
                            @elseif ($baris['sisa'] > 0 && $baris['dibayar'] > 0)
                                Sisa <x-rupiah :jumlah="$baris['sisa']" />
                            @elseif ($baris['sisa'] > 0)
                                Belum membayar
                            @else
                                Sudah membayar
                            @endif
                        </flux:text>
                    </div>

                    <div class="flex items-center gap-3">
                        <x-rupiah :jumlah="$baris['bagian']" class="font-semibold" />

                        @if ($baris['sisa'] === 0)
                            <flux:badge color="green">Lunas</flux:badge>
                        @else
                            <flux:badge color="amber">Belum</flux:badge>
                            <flux:button
                                wire:click="tandaiLunas({{ $baris['anggota']->id }})"
                                wire:confirm="Tandai {{ $baris['anggota']->nama }} sudah membayar?"
                                size="sm"
                                variant="primary"
                                data-test="tandai-lunas-{{ $baris['anggota']->id }}"
                            >
                                Tandai lunas
                            </flux:button>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    </flux:card>
</section>
