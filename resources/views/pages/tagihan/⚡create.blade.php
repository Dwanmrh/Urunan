<?php

use App\Actions\Urunan\BagiRupiah;
use App\Enums\CaraBagi;
use App\Models\AnggotaGrup;
use App\Models\Grup;
use App\Models\Tagihan;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Tambah tagihan')] class extends Component {
    public Grup $grup;

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
    public function mount(Grup $grup): void
    {
        $this->grup = $grup;

        $this->pastikanAnggota();

        $this->tanggal = now()->toDateString();
        $this->dibayarOleh = (string) $grup->anggota()->where('user_id', Auth::id())->value('id');
        $this->peserta = $this->anggota->map(fn (AnggotaGrup $anggota): string => (string) $anggota->id)->all();
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
                    'harga' => max(0, (int) $baris['harga']),
                    'penanggung' => $this->idTerpilih($baris['penanggung']),
                ])
                ->filter(fn (array $baris): bool => $baris['harga'] > 0)
                ->values()
                ->all();

            $bagian = BagiRupiah::perItem($item, $this->anggota->pluck('id')->all());
        } else {
            $total = max(0, (int) $this->total);
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
     * Simpan tagihan lalu buka halaman detailnya.
     */
    public function simpan(): void
    {
        $this->pastikanAnggota();

        $idAnggota = $this->anggota->pluck('id')->all();
        $perItem = $this->caraBagi === CaraBagi::PerItem->value;

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

        $this->validate($aturan, $this->pesanValidasi());

        $tagihan = Tagihan::catat($this->grup, [
            'judul' => $this->judul,
            'tanggal' => $this->tanggal,
            'dibayar_oleh' => (int) $this->dibayarOleh,
            'cara_bagi' => CaraBagi::from($this->caraBagi),
            'total' => (int) $this->total,
            'peserta' => $this->idTerpilih($this->peserta),
            'item' => array_map(fn (array $baris): array => [
                'nama' => $baris['nama'],
                'harga' => (int) $baris['harga'],
                'penanggung' => $this->idTerpilih($baris['penanggung']),
            ], $perItem ? $this->item : []),
        ]);

        $this->redirectRoute('tagihan.show', ['grup' => $this->grup, 'tagihan' => $tagihan], navigate: true);
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
            'total.integer' => 'Total harus angka bulat, tanpa titik atau koma.',
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
            'item.*.harga.integer' => 'Harga harus angka bulat, tanpa titik atau koma.',
            'item.*.harga.min' => 'Harga minimal Rp 1.',
            'item.*.harga.max' => 'Harga terlalu besar.',
            'item.*.penanggung.*.in' => 'Ada penanggung yang bukan anggota grup ini.',
        ];
    }

    /**
     * Tolak akses kalau user bukan anggota grup ini.
     */
    private function pastikanAnggota(): void
    {
        abort_unless($this->grup->punyaAnggota(Auth::user()), 403);
    }
}; ?>

<section class="w-full max-w-2xl">
    <div class="mb-6">
        <flux:button :href="route('grup.show', $grup)" variant="subtle" icon="arrow-left" size="sm" wire:navigate class="mb-3">{{ $grup->nama }}</flux:button>
        <flux:heading size="xl" level="1">Tambah tagihan</flux:heading>
    </div>

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
                            <flux:input wire:model.live.debounce.400ms="item.{{ $urutan }}.harga" label="Harga (Rp)" type="number" inputmode="numeric" min="1" />
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
            <flux:input wire:model.live.debounce.400ms="total" label="Total tagihan (Rp)" type="number" inputmode="numeric" min="1" placeholder="Contoh: 200000" />

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
                        <li wire:key="perkiraan-{{ $urutan }}" class="flex items-center justify-between gap-3 py-2">
                            <flux:text>{{ $baris['nama'] }}</flux:text>
                            <x-rupiah :jumlah="$baris['jumlah']" class="font-semibold" />
                        </li>
                    @endforeach
                </ul>
            @endif
        </flux:card>

        <div class="flex justify-end gap-2">
            <flux:button :href="route('grup.show', $grup)" variant="filled" wire:navigate>Batal</flux:button>
            <flux:button variant="primary" type="submit" data-test="simpan-tagihan">Simpan tagihan</flux:button>
        </div>
    </form>
</section>
