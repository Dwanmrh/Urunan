<?php

use App\Actions\Urunan\BagiRupiah;
use App\Actions\Urunan\FormatRupiah;
use App\Enums\CaraBagi;
use App\Models\AnggotaGrup;
use App\Models\Grup;
use App\Models\ItemTagihan;
use App\Models\Tagihan;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Tagihan')] class extends Component {
    public Grup $grup;

    public ?Tagihan $tagihan = null;

    public string $judul = '';

    public string $tanggal = '';

    public string $dibayarOleh = '';

    public string $caraBagi = 'rata';

    public string $total = '';

    /** @var list<string> */
    public array $peserta = [];

    /** @var list<array{nama: string, harga: string, penanggung: list<string>}> */
    public array $item = [];

    /**
     * Mount the component.
     */
    public function mount(Grup $grup, ?Tagihan $tagihan = null): void
    {
        $this->grup = $grup;

        abort_unless($grup->punyaAnggota(Auth::user()), 403);

        if ($tagihan?->exists) {
            abort_unless($tagihan->grup_id === $grup->id, 404);
            abort_unless($tagihan->bisaDikelolaOleh(Auth::user()), 403);

            $this->tagihan = $tagihan;
            $this->isiDariTagihan($tagihan);

            return;
        }

        $this->tanggal = now()->toDateString();
        $this->dibayarOleh = (string) $grup->anggota()->where('user_id', Auth::id())->value('id');
        $this->peserta = $this->semuaIdAnggota();
        $this->tambahItem();
    }

    /**
     * @return Collection<int, AnggotaGrup>
     */
    #[Computed]
    public function anggota(): Collection
    {
        return $this->grup->anggota()->orderBy('id')->get();
    }

    /**
     * Perkiraan bagian tiap orang selama form diisi.
     *
     * @return list<array{nama: string, jumlah: int}>
     */
    #[Computed]
    public function perkiraanBagian(): array
    {
        if ($this->caraBagi === CaraBagi::PerItem->value) {
            $item = collect($this->item)
                ->map(fn (array $baris): array => [
                    'harga' => FormatRupiah::keAngka($baris['harga']),
                    'penanggung' => $this->idTerpilih($baris['penanggung']),
                ])
                ->filter(fn (array $baris): bool => $baris['harga'] > 0)
                ->values()
                ->all();

            $bagian = BagiRupiah::perItem($item, $this->anggota->pluck('id')->all());
        } else {
            $total = FormatRupiah::keAngka($this->total);
            $bagian = $total > 0 ? BagiRupiah::rata($total, $this->idTerpilih($this->peserta)) : [];
        }

        $nama = $this->anggota->pluck('nama', 'id');

        return collect($bagian)
            ->map(fn (int $jumlah, int $id): array => ['nama' => $nama[$id], 'jumlah' => $jumlah])
            ->values()
            ->all();
    }

    /**
     * Tambah satu baris item kosong.
     */
    public function tambahItem(): void
    {
        $this->item[] = ['nama' => '', 'harga' => '', 'penanggung' => []];
    }

    /**
     * Hapus satu baris item, tetapi sisakan minimal satu baris.
     */
    public function hapusItem(int $urutan): void
    {
        if (count($this->item) <= 1) {
            return;
        }

        unset($this->item[$urutan]);

        $this->item = array_values($this->item);
    }

    /**
     * Simpan tagihan baru atau perubahan, lalu buka halaman detailnya.
     */
    public function simpan(): void
    {
        abort_unless($this->grup->punyaAnggota(Auth::user()), 403);

        if ($this->tagihan !== null) {
            abort_unless($this->tagihan->bisaDikelolaOleh(Auth::user()), 403);
        }

        $idAnggota = $this->anggota->pluck('id')->all();
        $perItem = $this->caraBagi === CaraBagi::PerItem->value;

        $data = [
            'judul' => $this->judul,
            'tanggal' => $this->tanggal,
            'dibayarOleh' => $this->dibayarOleh,
            'caraBagi' => $this->caraBagi,
            'total' => FormatRupiah::bersihkan($this->total),
            'peserta' => $this->peserta,
            'item' => array_map(fn (array $baris): array => [
                'nama' => $baris['nama'],
                'harga' => FormatRupiah::bersihkan($baris['harga']),
                'penanggung' => $baris['penanggung'],
            ], $this->item),
        ];

        $aturan = [
            'judul' => ['required', 'string', 'max:100'],
            'tanggal' => ['required', 'date'],
            'dibayarOleh' => ['required', Rule::in($idAnggota)],
            'caraBagi' => ['required', Rule::enum(CaraBagi::class)],
        ];

        if ($perItem) {
            $aturan += [
                'item' => ['required', 'array', 'min:1'],
                'item.*.nama' => ['required', 'string', 'max:100'],
                'item.*.harga' => ['required', 'integer', 'min:1', 'max:1000000000'],
                'item.*.penanggung' => ['array'],
                'item.*.penanggung.*' => [Rule::in($idAnggota)],
            ];
        } else {
            $aturan += [
                'total' => ['required', 'integer', 'min:1', 'max:1000000000'],
                'peserta' => ['required', 'array', 'min:1'],
                'peserta.*' => [Rule::in($idAnggota)],
            ];
        }

        Validator::make($data, $aturan, $this->pesanValidasi())->validate();

        $isi = [
            'judul' => $this->judul,
            'tanggal' => $this->tanggal,
            'dibayar_oleh' => (int) $this->dibayarOleh,
            'cara_bagi' => CaraBagi::from($this->caraBagi),
            'total' => (int) $data['total'],
            'peserta' => $this->idTerpilih($this->peserta),
            'item' => $perItem ? array_map(fn (array $baris): array => [
                'nama' => $baris['nama'],
                'harga' => (int) $baris['harga'],
                'penanggung' => $this->idTerpilih($baris['penanggung']),
            ], $data['item']) : [],
        ];

        if ($this->tagihan !== null) {
            $this->tagihan->perbarui($isi);
            $tagihan = $this->tagihan;
        } else {
            $tagihan = Tagihan::catat($this->grup, $isi, Auth::user());
        }

        $this->redirectRoute('tagihan.show', ['grup' => $this->grup, 'tagihan' => $tagihan], navigate: true);
    }

    /**
     * Isi form dari tagihan yang akan diedit.
     */
    private function isiDariTagihan(Tagihan $tagihan): void
    {
        $this->judul = $tagihan->judul;
        $this->tanggal = $tagihan->tanggal->toDateString();
        $this->dibayarOleh = (string) $tagihan->dibayar_oleh;
        $this->caraBagi = $tagihan->cara_bagi->value;
        $this->total = FormatRupiah::keTeks($tagihan->total);

        if ($tagihan->cara_bagi === CaraBagi::PerItem) {
            $this->peserta = $this->semuaIdAnggota();
            $this->item = $tagihan->item()->with('anggota')->orderBy('id')->get()
                ->map(fn (ItemTagihan $item): array => [
                    'nama' => $item->nama,
                    'harga' => FormatRupiah::keTeks($item->harga),
                    'penanggung' => $item->anggota->map(fn (AnggotaGrup $anggota): string => (string) $anggota->id)->values()->all(),
                ])
                ->all();
        } else {
            $this->peserta = $tagihan->bagian()->orderBy('anggota_grup_id')->pluck('anggota_grup_id')
                ->map(fn (int $id): string => (string) $id)
                ->all();
            $this->tambahItem();
        }
    }

    /**
     * @return list<string>
     */
    private function semuaIdAnggota(): array
    {
        return $this->anggota->map(fn (AnggotaGrup $anggota): string => (string) $anggota->id)->values()->all();
    }

    /**
     * Ubah pilihan dari form menjadi id anggota grup yang valid, terurut dan unik.
     *
     * @param  list<string|int>  $pilihan
     * @return list<int>
     */
    private function idTerpilih(array $pilihan): array
    {
        $idAnggota = $this->anggota->pluck('id')->all();

        $id = array_values(array_unique(array_filter(
            array_map('intval', $pilihan),
            fn (int $id): bool => in_array($id, $idAnggota, true),
        )));

        sort($id);

        return $id;
    }

    /**
     * @return array<string, string>
     */
    private function pesanValidasi(): array
    {
        return [
            'judul.required' => 'Judul tagihan wajib diisi.',
            'judul.max' => 'Judul maksimal 100 karakter.',
            'tanggal.required' => 'Tanggal wajib diisi.',
            'tanggal.date' => 'Tanggal tidak valid.',
            'dibayarOleh.required' => 'Pilih siapa yang membayar dulu.',
            'dibayarOleh.in' => 'Pembayar harus anggota grup ini.',
            'caraBagi.required' => 'Pilih cara membagi.',
            'caraBagi.enum' => 'Cara membagi tidak valid.',
            'total.required' => 'Total tagihan wajib diisi.',
            'total.integer' => 'Total harus berupa angka.',
            'total.min' => 'Total minimal Rp 1.',
            'total.max' => 'Total terlalu besar.',
            'peserta.required' => 'Pilih minimal satu orang yang ikut menanggung.',
            'peserta.min' => 'Pilih minimal satu orang yang ikut menanggung.',
            'peserta.*.in' => 'Ada peserta yang bukan anggota grup ini.',
            'item.required' => 'Tambahkan minimal satu item.',
            'item.min' => 'Tambahkan minimal satu item.',
            'item.*.nama.required' => 'Nama item wajib diisi.',
            'item.*.nama.max' => 'Nama item maksimal 100 karakter.',
            'item.*.harga.required' => 'Harga wajib diisi.',
            'item.*.harga.integer' => 'Harga harus berupa angka.',
            'item.*.harga.min' => 'Harga minimal Rp 1.',
            'item.*.harga.max' => 'Harga terlalu besar.',
            'item.*.penanggung.*.in' => 'Ada penanggung yang bukan anggota grup ini.',
        ];
    }
}; ?>

<section class="w-full max-w-2xl">
    <div class="mb-6">
        <flux:button :href="$tagihan ? route('tagihan.show', [$grup, $tagihan]) : route('grup.show', $grup)" variant="subtle" icon="arrow-left" size="sm" wire:navigate class="mb-3">
            {{ $tagihan ? $tagihan->judul : $grup->nama }}
        </flux:button>
        <flux:heading size="xl" level="1">{{ $tagihan ? 'Edit tagihan' : 'Tambah tagihan' }}</flux:heading>
    </div>

    @if ($tagihan)
        <flux:callout icon="information-circle" class="mb-6" text="Pembayaran yang sudah diterima tetap tercatat. Bukti bayar yang masih menunggu konfirmasi akan dibatalkan dan perlu dikirim ulang." />
    @endif

    <form wire:submit="simpan" class="space-y-6">
        <flux:input wire:model="judul" label="Judul" type="text" placeholder="Contoh: Makan malam ultah Sinta" required />

        <div class="grid gap-4 sm:grid-cols-2">
            <flux:input wire:model="tanggal" label="Tanggal" type="date" required />

            <flux:select wire:model="dibayarOleh" label="Dibayar dulu oleh">
                @foreach ($this->anggota as $anggota)
                    <flux:select.option value="{{ $anggota->id }}" wire:key="pembayar-{{ $anggota->id }}">{{ $anggota->nama }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <flux:radio.group wire:model.live="caraBagi" label="Cara membagi" variant="segmented">
            @foreach (\App\Enums\CaraBagi::cases() as $cara)
                <flux:radio value="{{ $cara->value }}" label="{{ $cara->label() }}" wire:key="cara-{{ $cara->value }}" />
            @endforeach
        </flux:radio.group>

        @if ($caraBagi === 'per_item')
            <div class="space-y-4">
                @foreach ($item as $urutan => $baris)
                    <flux:card wire:key="item-{{ $urutan }}" class="space-y-4">
                        <div class="grid gap-4 sm:grid-cols-[1fr_12rem]">
                            <flux:input wire:model="item.{{ $urutan }}.nama" label="Nama item" type="text" placeholder="Contoh: Ayam bakar" />
                            <flux:input
                                wire:model.live.debounce.400ms="item.{{ $urutan }}.harga"
                                label="Harga"
                                type="text"
                                inputmode="numeric"
                                mask:dynamic="$money($input, ',', '.', 0)"
                                placeholder="60.000"
                            >
                                <x-slot name="iconLeading">
                                    <span class="text-sm text-zinc-500">Rp</span>
                                </x-slot>
                            </flux:input>
                        </div>

                        <flux:checkbox.group wire:model.live="item.{{ $urutan }}.penanggung" label="Ditanggung oleh" description="Kosongkan kalau ditanggung semua anggota." variant="pills">
                            @foreach ($this->anggota as $anggota)
                                <flux:checkbox value="{{ $anggota->id }}" label="{{ $anggota->nama }}" wire:key="item-{{ $urutan }}-anggota-{{ $anggota->id }}" />
                            @endforeach
                        </flux:checkbox.group>

                        @if (count($item) > 1)
                            <flux:button wire:click="hapusItem({{ $urutan }})" variant="subtle" size="sm" icon="trash">Hapus item</flux:button>
                        @endif
                    </flux:card>
                @endforeach

                <flux:button wire:click="tambahItem" icon="plus" class="w-full">Tambah item</flux:button>
            </div>
        @else
            <flux:input
                wire:model.live.debounce.400ms="total"
                label="Total tagihan"
                type="text"
                inputmode="numeric"
                mask:dynamic="$money($input, ',', '.', 0)"
                placeholder="200.000"
            >
                <x-slot name="iconLeading">
                    <span class="text-sm text-zinc-500">Rp</span>
                </x-slot>
            </flux:input>

            <flux:checkbox.group wire:model.live="peserta" label="Ditanggung oleh">
                @foreach ($this->anggota as $anggota)
                    <flux:checkbox value="{{ $anggota->id }}" label="{{ $anggota->nama }}" wire:key="peserta-{{ $anggota->id }}" />
                @endforeach
            </flux:checkbox.group>
        @endif

        <flux:card class="space-y-3">
            <flux:heading level="2">Perkiraan bagian tiap orang</flux:heading>

            @if ($this->perkiraanBagian === [])
                <flux:text>Isi nominal untuk melihat bagian tiap orang.</flux:text>
            @else
                <ul class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @foreach ($this->perkiraanBagian as $urutan => $baris)
                        <li wire:key="perkiraan-{{ $urutan }}" class="flex items-center justify-between gap-3 py-2 [&>*:first-child]:min-w-0 [&>*:last-child]:shrink-0">
                            <flux:text>{{ $baris['nama'] }}</flux:text>
                            <x-rupiah :jumlah="$baris['jumlah']" class="font-semibold" />
                        </li>
                    @endforeach
                </ul>
            @endif
        </flux:card>

        <div class="flex justify-end gap-2">
            <flux:button :href="$tagihan ? route('tagihan.show', [$grup, $tagihan]) : route('grup.show', $grup)" variant="filled" wire:navigate>Batal</flux:button>
            <flux:button variant="primary" type="submit" data-test="simpan-tagihan">{{ $tagihan ? 'Simpan perubahan' : 'Simpan tagihan' }}</flux:button>
        </div>
    </form>
</section>
