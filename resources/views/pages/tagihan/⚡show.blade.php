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
use Livewire\WithFileUploads;

new #[Title('Detail tagihan')] class extends Component {
    use WithFileUploads;

    public Grup $grup;

    public Tagihan $tagihan;

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $bukti = null;

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
     * @return Collection<int, array{anggota: AnggotaGrup, bagian: int, dibayar: int, sisa: int, pembayar: bool, menunggu: \App\Models\Pembayaran|null}>
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
     * Keanggotaan user yang sedang login di grup ini.
     */
    #[Computed]
    public function anggotaSaya(): ?AnggotaGrup
    {
        return $this->grup->anggota()->where('user_id', Auth::id())->first();
    }

    /**
     * Apakah user yang sedang login boleh mengelola tagihan ini.
     */
    #[Computed]
    public function bisaMengelola(): bool
    {
        return $this->tagihan->bisaDikelolaOleh(Auth::user());
    }

    /**
     * Sisa bagian user yang sedang login di tagihan ini.
     */
    #[Computed]
    public function sisaSaya(): int
    {
        return (int) ($this->statusPelunasan->first(
            fn (array $baris): bool => $baris['anggota']->id === $this->anggotaSaya?->id,
        )['sisa'] ?? 0);
    }

    /**
     * Catat bahwa seorang anggota sudah membayar langsung, misalnya tunai.
     */
    public function tandaiLunas(int $idAnggota): void
    {
        $this->pastikanPengelola();

        $anggota = $this->grup->anggota()->findOrFail($idAnggota);

        if ($this->tagihan->tandaiLunas($anggota, Auth::user()) === null) {
            return;
        }

        $this->segarkan();

        Flux::toast(variant: 'success', text: $anggota->nama.' ditandai lunas.');
    }

    /**
     * Terima bukti bayar yang dikirim anggota.
     */
    public function konfirmasi(int $idPembayaran): void
    {
        $this->pastikanPengelola();

        $this->tagihan->konfirmasi($this->tagihan->pembayaran()->findOrFail($idPembayaran));

        $this->segarkan();

        Flux::toast(variant: 'success', text: 'Pembayaran dikonfirmasi.');
    }

    /**
     * Tolak bukti bayar yang dikirim anggota.
     */
    public function tolak(int $idPembayaran): void
    {
        $this->pastikanPengelola();

        $this->tagihan->tolak($this->tagihan->pembayaran()->findOrFail($idPembayaran));

        $this->segarkan();

        Flux::toast(text: 'Bukti bayar ditolak.');
    }

    /**
     * Unggah bukti transfer untuk bagian user yang sedang login.
     */
    public function kirimBukti(): void
    {
        $this->pastikanAnggota();

        $anggota = $this->anggotaSaya;

        abort_if($anggota === null, 403);

        $this->validate(
            ['bukti' => ['required', 'image', 'max:4096']],
            [
                'bukti.required' => 'Pilih foto bukti transfer dulu.',
                'bukti.image' => 'Bukti harus berupa gambar.',
                'bukti.max' => 'Ukuran gambar maksimal 4 MB.',
            ],
        );

        $path = $this->bukti->store('bukti-pembayaran', 'local');

        if ($this->tagihan->kirimBukti($anggota, $path, Auth::user()) === null) {
            \Illuminate\Support\Facades\Storage::disk('local')->delete($path);
            $this->reset('bukti');

            Flux::toast(variant: 'warning', text: 'Bagianmu sudah lunas atau buktinya masih menunggu konfirmasi.');

            return;
        }

        $this->reset('bukti');
        $this->segarkan();

        Flux::modal('kirim-bukti')->close();
        Flux::toast(variant: 'success', text: 'Bukti terkirim. Menunggu konfirmasi '.$this->tagihan->pembayar->nama.'.');
    }

    /**
     * Hapus tagihan ini lalu kembali ke halaman grup.
     */
    public function hapus(): void
    {
        $this->pastikanPengelola();

        $this->tagihan->hapus();

        $this->redirectRoute('grup.show', $this->grup, navigate: true);
    }

    /**
     * Muat ulang data yang tampil setelah ada perubahan.
     */
    private function segarkan(): void
    {
        unset($this->statusPelunasan, $this->sisaSaya);
    }

    /**
     * Tolak akses kalau user bukan anggota grup ini.
     */
    private function pastikanAnggota(): void
    {
        abort_unless($this->grup->punyaAnggota(Auth::user()), 403);
    }

    /**
     * Tolak akses kalau user bukan pengelola tagihan ini.
     */
    private function pastikanPengelola(): void
    {
        $this->pastikanAnggota();

        abort_unless($this->tagihan->bisaDikelolaOleh(Auth::user()), 403);
    }
}; ?>

<section class="w-full max-w-3xl">
    <div class="mb-6">
        <flux:button :href="route('grup.show', $grup)" variant="subtle" icon="arrow-left" size="sm" wire:navigate class="mb-3">{{ $grup->nama }}</flux:button>

        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <flux:heading size="xl" level="1">{{ $tagihan->judul }}</flux:heading>
                <flux:subheading size="lg">
                    Dibayar dulu oleh {{ $tagihan->pembayar->nama }}, {{ $tagihan->tanggal->format('d/m/Y') }}, {{ $tagihan->cara_bagi->label() }}
                </flux:subheading>
            </div>

            @if ($this->bisaMengelola)
                <div class="flex gap-2">
                    <flux:button :href="route('tagihan.edit', [$grup, $tagihan])" icon="pencil-square" size="sm" wire:navigate data-test="edit-tagihan">Edit</flux:button>
                    <flux:button
                        wire:click="hapus"
                        wire:confirm="Hapus tagihan ini? Pembayaran yang sudah diterima tetap tercatat di saldo grup."
                        icon="trash"
                        size="sm"
                        variant="danger"
                        data-test="hapus-tagihan"
                    >
                        Hapus
                    </flux:button>
                </div>
            @endif
        </div>
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

        @unless ($this->bisaMengelola)
            <flux:text>Hanya {{ $tagihan->pembayar->nama }} yang bisa menandai lunas dan mengonfirmasi bukti bayar.</flux:text>
        @endunless

        <ul class="divide-y divide-zinc-200 dark:divide-zinc-700">
            @foreach ($this->statusPelunasan as $baris)
                @php($menunggu = $baris['menunggu'])

                <li wire:key="bagian-{{ $baris['anggota']->id }}" class="flex flex-wrap items-center justify-between gap-3 py-3">
                    <div>
                        <flux:heading>{{ $baris['anggota']->nama }}</flux:heading>
                        <flux:text>
                            @if ($baris['pembayar'])
                                Yang membayar dulu
                            @elseif ($menunggu)
                                Bukti terkirim, menunggu konfirmasi
                            @elseif ($baris['sisa'] > 0 && $baris['dibayar'] > 0)
                                Sisa <x-rupiah :jumlah="$baris['sisa']" />
                            @elseif ($baris['sisa'] > 0)
                                Belum membayar
                            @else
                                Sudah membayar
                            @endif
                        </flux:text>
                    </div>

                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <x-rupiah :jumlah="$baris['bagian']" class="me-1 font-semibold" />

                        @if ($baris['sisa'] === 0)
                            <flux:badge color="green">Lunas</flux:badge>
                        @elseif ($menunggu)
                            <flux:badge color="sky">Menunggu</flux:badge>
                        @else
                            <flux:badge color="amber">Belum</flux:badge>
                        @endif

                        @if ($this->bisaMengelola && ! $baris['pembayar'] && $baris['sisa'] > 0)
                            @if ($menunggu)
                                <flux:button :href="route('pembayaran.bukti', [$grup, $menunggu])" target="_blank" size="sm" variant="ghost" icon="photo">Lihat bukti</flux:button>
                                <flux:button wire:click="konfirmasi({{ $menunggu->id }})" size="sm" variant="primary" data-test="konfirmasi-{{ $menunggu->id }}">Konfirmasi</flux:button>
                                <flux:button
                                    wire:click="tolak({{ $menunggu->id }})"
                                    wire:confirm="Tolak bukti dari {{ $baris['anggota']->nama }}? Buktinya akan dihapus dan perlu dikirim ulang."
                                    size="sm"
                                    variant="danger"
                                >
                                    Tolak
                                </flux:button>
                            @else
                                <flux:button
                                    wire:click="tandaiLunas({{ $baris['anggota']->id }})"
                                    wire:confirm="Tandai {{ $baris['anggota']->nama }} sudah membayar langsung, misalnya tunai?"
                                    size="sm"
                                    variant="primary"
                                    data-test="tandai-lunas-{{ $baris['anggota']->id }}"
                                >
                                    Tandai lunas
                                </flux:button>
                            @endif
                        @elseif ($this->anggotaSaya?->id === $baris['anggota']->id && ! $baris['pembayar'] && $baris['sisa'] > 0 && ! $menunggu)
                            <flux:modal.trigger name="kirim-bukti">
                                <flux:button size="sm" variant="primary" icon="arrow-up-tray" data-test="buka-kirim-bukti">Kirim bukti bayar</flux:button>
                            </flux:modal.trigger>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    </flux:card>

    <flux:modal name="kirim-bukti" focusable class="max-w-lg">
        <form wire:submit="kirimBukti" class="space-y-6">
            <div>
                <flux:heading size="lg">Kirim bukti bayar</flux:heading>
                <flux:subheading>
                    Transfer <x-rupiah :jumlah="$this->sisaSaya" class="font-semibold" /> ke {{ $tagihan->pembayar->nama }}, lalu unggah foto atau screenshot buktinya.
                    Statusmu jadi lunas setelah {{ $tagihan->pembayar->nama }} mengonfirmasi.
                </flux:subheading>
            </div>

            <flux:input type="file" wire:model="bukti" label="Foto bukti transfer" accept="image/*" />

            <div wire:loading wire:target="bukti">
                <flux:text>Mengunggah...</flux:text>
            </div>

            @if ($bukti && str_starts_with((string) $bukti->getMimeType(), 'image/'))
                <img src="{{ $bukti->temporaryUrl() }}" alt="Pratinjau bukti transfer" class="max-h-64 rounded-lg border border-zinc-200 dark:border-zinc-700">
            @endif

            <div class="flex justify-end space-x-2 rtl:space-x-reverse">
                <flux:modal.close>
                    <flux:button variant="filled">Batal</flux:button>
                </flux:modal.close>

                <flux:button variant="primary" type="submit" data-test="kirim-bukti">Kirim</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
