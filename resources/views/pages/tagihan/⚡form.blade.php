<?php

use App\Actions\Urunan\BagiRupiah;
use App\Actions\Urunan\FormatRupiah;
use App\Enums\CaraBagi;
use App\Enums\Frekuensi;
use App\Models\AnggotaGrup;
use App\Models\Grup;
use App\Models\ItemTagihan;
use App\Models\Tagihan;
use App\Models\TagihanRutin;
use App\Support\BatasAksi;
use Illuminate\Support\Carbon;
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

    public bool $ulangi = false;

    public string $frekuensi = 'bulanan';

    /**
     * Mount the component.
     */
    public function mount(Grup $grup, ?Tagihan $tagihan = null): void
    {
        $this->grup = $grup;

        abort_unless($grup->punyaAnggota(Auth::user()), 403);

        if ($tagihan?->exists) {
            abort_unless($tagihan->grup_id === $grup->id, 404);
            abort_unless($tagihan->bisaDiubahOleh(Auth::user()), 403);

            $this->tagihan = $tagihan;
            $this->isiDariTagihan($tagihan);

            return;
        }

        $this->tanggal = now()->format('d/m/Y');
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
     * Tanggal tagihan rutin berikutnya kalau opsi ulangi dipilih.
     */
    #[Computed]
    public function jadwalBerikutnya(): ?string
    {
        $frekuensi = Frekuensi::tryFrom($this->frekuensi);

        if ($frekuensi === null || preg_match('#^\d{2}/\d{2}/\d{4}$#', $this->tanggal) !== 1) {
            return null;
        }

        try {
            $mulai = Carbon::createFromFormat('!d/m/Y', $this->tanggal);
        } catch (\Throwable) {
            return null;
        }

        return $frekuensi->tanggalKe($mulai, 1)->format('d/m/Y');
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
            abort_unless($this->tagihan->bisaDiubahOleh(Auth::user()), 403);
        }

        // Validasi di bawah tidak memakai $this->validate(), jadi pesan error lama dibersihkan manual.
        $this->resetErrorBag();

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
            'tanggal' => ['required', 'date_format:d/m/Y', 'after_or_equal:01/01/2000', 'before_or_equal:31/12/2099'],
            'dibayarOleh' => ['required', Rule::in($idAnggota)],
            'caraBagi' => ['required', Rule::enum(CaraBagi::class)],
        ];

        if ($this->tagihan === null && ! $perItem && $this->ulangi) {
            $data['frekuensi'] = $this->frekuensi;
            $aturan['frekuensi'] = ['required', Rule::enum(Frekuensi::class)];
        }

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

        if (! BatasAksi::boleh('simpan-tagihan', 20)) {
            return;
        }

        $isi = [
            'judul' => $this->judul,
            'tanggal' => Carbon::createFromFormat('!d/m/Y', $this->tanggal)->toDateString(),
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

            if (! $perItem && $this->ulangi) {
                TagihanRutin::mulaiDari($tagihan, Frekuensi::from($this->frekuensi), Auth::user());
            }
        }

        $this->redirectRoute('tagihan.show', ['grup' => $this->grup, 'tagihan' => $tagihan], navigate: true);
    }

    /**
     * Isi form dari tagihan yang akan diedit.
     */
    private function isiDariTagihan(Tagihan $tagihan): void
    {
        $this->judul = $tagihan->judul;
        $this->tanggal = $tagihan->tanggal->format('d/m/Y');
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
            'judul.required' => __('Judul tagihan wajib diisi.'),
            'judul.max' => __('Judul maksimal 100 karakter.'),
            'tanggal.required' => __('Tanggal wajib diisi.'),
            'tanggal.date_format' => __('Tanggal tidak valid. Pakai format HH/BB/TTTT, misalnya 24/11/2026.'),
            'tanggal.after_or_equal' => __('Tanggal terlalu lama, minimal tahun 2000.'),
            'tanggal.before_or_equal' => __('Tanggal terlalu jauh ke depan.'),
            'dibayarOleh.required' => __('Pilih siapa yang membayar dulu.'),
            'dibayarOleh.in' => __('Pembayar harus anggota grup ini.'),
            'caraBagi.required' => __('Pilih cara membagi.'),
            'caraBagi.enum' => __('Cara membagi tidak valid.'),
            'frekuensi.required' => __('Pilih seberapa sering tagihan diulang.'),
            'frekuensi.enum' => __('Pilihan pengulangan tidak valid.'),
            'total.required' => __('Total tagihan wajib diisi.'),
            'total.integer' => __('Total harus berupa angka.'),
            'total.min' => __('Total minimal Rp 1.'),
            'total.max' => __('Total terlalu besar.'),
            'peserta.required' => __('Pilih minimal satu orang yang ikut menanggung.'),
            'peserta.min' => __('Pilih minimal satu orang yang ikut menanggung.'),
            'peserta.*.in' => __('Ada peserta yang bukan anggota grup ini.'),
            'item.required' => __('Tambahkan minimal satu item.'),
            'item.min' => __('Tambahkan minimal satu item.'),
            'item.*.nama.required' => __('Nama item wajib diisi.'),
            'item.*.nama.max' => __('Nama item maksimal 100 karakter.'),
            'item.*.harga.required' => __('Harga wajib diisi.'),
            'item.*.harga.integer' => __('Harga harus berupa angka.'),
            'item.*.harga.min' => __('Harga minimal Rp 1.'),
            'item.*.harga.max' => __('Harga terlalu besar.'),
            'item.*.penanggung.*.in' => __('Ada penanggung yang bukan anggota grup ini.'),
        ];
    }
}; ?>

<section class="w-full max-w-3xl">
    <x-tur nama="form-tagihan" :langkah="[
        ['elemen' => '[data-tur=pembayar]', 'judul' => __('Siapa yang membayar dulu?'), 'isi' => __('Pilih orang yang menalangi. Dia yang nanti menerima uang dan mengonfirmasi bukti bayar.')],
        ['elemen' => '[data-tur=cara-bagi]', 'judul' => __('Bagi rata atau per item'), 'isi' => __('Bagi rata untuk tagihan sama besar. Per item kalau tiap orang pesan berbeda, item bersama dibagi ke yang ikut menanggung.')],
        ['elemen' => '[data-tur=perkiraan]', 'judul' => __('Cek sebelum simpan'), 'isi' => __('Bagian tiap orang langsung dihitung saat kamu mengisi form. Setelah disimpan, anggota yang punya akun dapat notifikasi.'), 'sisi' => 'top'],
    ]" />

    <x-kepala-halaman
        :judul="$tagihan ? __('Edit tagihan') : __('Tambah tagihan')"
        :sub="$tagihan ? null : __('Yang bayar duluan catat di sini. Udunan yang bagi, kamu tinggal santai.')"
        :kembali="$tagihan ? route('tagihan.show', [$grup, $tagihan]) : route('grup.show', $grup)"
        :kembali-label="$tagihan ? $tagihan->judul : $grup->emoji.' '.$grup->nama"
    />

    @if ($tagihan)
        <flux:callout icon="information-circle" class="mb-6" text="{{ __('Pembayaran yang sudah diterima tetap tercatat. Bukti bayar yang masih menunggu konfirmasi akan dibatalkan dan perlu dikirim ulang.') }}" />

        @if ($tagihan->tagihan_rutin_id)
            <flux:callout icon="arrow-path" color="indigo" class="-mt-3 mb-6" text="{{ __('Tagihan ini dibuat dari tagihan rutin. Perubahan di sini hanya berlaku untuk tagihan ini, bukan untuk bulan atau minggu berikutnya.') }}" />
        @endif
    @endif

    <form wire:submit="simpan" class="grid gap-6 lg:grid-cols-[1fr_18rem] lg:items-start">
        <div class="min-w-0 space-y-6">
        <x-kartu :judul="__('Detail')" ikon="pencil-square">
        <div class="space-y-6">
        <flux:input wire:model="judul" label="{{ __('Judul') }}" type="text" placeholder="{{ __('Contoh: Makan malam ultah Sinta') }}" required />

        <div class="grid gap-4 sm:grid-cols-2">
            <div
                x-data="{
                    bukaKalender() {
                        const pemilih = $refs.pemilih;
                        const [hari, bulan, tahun] = ($wire.tanggal ?? '').split('/');
                        pemilih.value = tahun?.length === 4 ? `${tahun}-${bulan}-${hari}` : '';
                        try { pemilih.showPicker(); } catch { pemilih.click(); }
                    },
                    pilih(nilai) {
                        if (! nilai) return;
                        const [tahun, bulan, hari] = nilai.split('-');
                        $wire.tanggal = `${hari}/${bulan}/${tahun}`;
                    },
                }"
            >
                <flux:input
                    wire:model.blur="tanggal"
                    label="{{ __('Tanggal') }}"
                    type="text"
                    inputmode="numeric"
                    mask="99/99/9999"
                    placeholder="{{ __('HH/BB/TTTT') }}"
                    autocomplete="off"
                    required
                    data-test="input-tanggal"
                >
                    <x-slot name="iconTrailing">
                        <flux:button type="button" size="sm" variant="subtle" icon="calendar-days" class="-me-1" x-on:click="bukaKalender()" aria-label="{{ __('Pilih dari kalender') }}" />
                        <input type="date" x-ref="pemilih" x-on:change="pilih($event.target.value)" class="pointer-events-none absolute end-0 bottom-0 size-px opacity-0" tabindex="-1" aria-hidden="true" />
                    </x-slot>
                </flux:input>
            </div>

            <flux:select wire:model="dibayarOleh" label="{{ __('Dibayar dulu oleh') }}" data-tur="pembayar">
                @foreach ($this->anggota as $anggota)
                    <flux:select.option value="{{ $anggota->id }}" wire:key="pembayar-{{ $anggota->id }}">{{ $anggota->nama }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        </div>
        </x-kartu>

        <x-kartu :judul="__('Pembagian')" ikon="scale">
        <div class="space-y-6">
        <flux:radio.group wire:model.live="caraBagi" label="{{ __('Cara membagi') }}" variant="segmented" data-tur="cara-bagi">
            @foreach (\App\Enums\CaraBagi::cases() as $cara)
                <flux:radio value="{{ $cara->value }}" label="{{ $cara->label() }}" wire:key="cara-{{ $cara->value }}" />
            @endforeach
        </flux:radio.group>

        @if ($caraBagi === 'per_item')
            <div class="space-y-4">
                @foreach ($item as $urutan => $baris)
                    <div wire:key="item-{{ $urutan }}" class="space-y-4 rounded-2xl bg-zinc-50 p-4 dark:bg-white/5">
                        <div class="grid gap-4 sm:grid-cols-[1fr_12rem]">
                            <flux:input wire:model="item.{{ $urutan }}.nama" label="{{ __('Nama item') }}" type="text" placeholder="{{ __('Contoh: Ayam bakar') }}" />
                            <flux:input
                                wire:model.live.debounce.400ms="item.{{ $urutan }}.harga"
                                label="{{ __('Harga') }}"
                                type="text"
                                inputmode="numeric"
                                mask:dynamic="$money($input, ',', '.', 0)"
                                placeholder="60.000"
                            >
                                <x-slot name="iconLeading">
                                    <span class="text-sm text-zinc-600 dark:text-zinc-400">Rp</span>
                                </x-slot>
                            </flux:input>
                        </div>

                        <flux:checkbox.group wire:model.live="item.{{ $urutan }}.penanggung" label="{{ __('Ditanggung oleh') }}" description="{{ __('Kosongkan kalau ditanggung semua anggota.') }}" variant="pills">
                            @foreach ($this->anggota as $anggota)
                                <flux:checkbox value="{{ $anggota->id }}" label="{{ $anggota->nama }}" wire:key="item-{{ $urutan }}-anggota-{{ $anggota->id }}" />
                            @endforeach
                        </flux:checkbox.group>

                        @if (count($item) > 1)
                            <flux:button wire:click="hapusItem({{ $urutan }})" variant="subtle" size="sm" icon="trash">{{ __('Hapus item') }}</flux:button>
                        @endif
                    </div>
                @endforeach

                <flux:button wire:click="tambahItem" icon="plus" class="w-full">{{ __('Tambah item') }}</flux:button>
            </div>
        @else
            <flux:input
                wire:model.live.debounce.400ms="total"
                label="{{ __('Total tagihan') }}"
                type="text"
                inputmode="numeric"
                mask:dynamic="$money($input, ',', '.', 0)"
                placeholder="200.000"
            >
                <x-slot name="iconLeading">
                    <span class="text-sm text-zinc-600 dark:text-zinc-400">Rp</span>
                </x-slot>
            </flux:input>

            <flux:checkbox.group wire:model.live="peserta" label="{{ __('Ditanggung oleh') }}" variant="pills">
                @foreach ($this->anggota as $anggota)
                    <flux:checkbox value="{{ $anggota->id }}" label="{{ $anggota->nama }}" wire:key="peserta-{{ $anggota->id }}" />
                @endforeach
            </flux:checkbox.group>

            @unless ($tagihan)
                <div class="rounded-xl border border-indigo-200 bg-indigo-50/60 p-4 dark:border-indigo-500/30 dark:bg-indigo-500/10" data-tur="ulangi">
                    <flux:switch
                        wire:model.live="ulangi"
                        label="{{ __('Ulangi otomatis') }}"
                        description="{{ __('Cocok untuk wifi kos, listrik, atau langganan bareng. Tagihan berikutnya dicatat sendiri dan anggota langsung dikabari.') }}"
                        data-test="ulangi-tagihan"
                    />

                    @if ($ulangi)
                        <div class="mt-4 space-y-2">
                            <flux:radio.group wire:model.live="frekuensi" variant="segmented" size="sm">
                                @foreach (\App\Enums\Frekuensi::cases() as $pilihan)
                                    <flux:radio value="{{ $pilihan->value }}" label="{{ $pilihan->label() }}" wire:key="frekuensi-{{ $pilihan->value }}" />
                                @endforeach
                            </flux:radio.group>
                            <flux:error name="frekuensi" />

                            @if ($this->jadwalBerikutnya)
                                <flux:text size="sm" class="flex items-center gap-1.5">
                                    <flux:icon.arrow-path variant="micro" class="text-indigo-500" />
                                    {{ __('Tagihan berikutnya: :tanggal', ['tanggal' => $this->jadwalBerikutnya]) }}
                                </flux:text>
                            @endif
                        </div>
                    @endif
                </div>
            @endunless
        @endif

        </div>
        </x-kartu>
        </div>

        <div class="space-y-4 lg:sticky lg:top-6">
            <x-kartu :judul="__('Bagian tiap orang')" ikon="calculator" padat data-tur="perkiraan">
                @if ($this->perkiraanBagian === [])
                    <div class="flex flex-col items-center px-5 py-8 text-center">
                        <span class="text-3xl" aria-hidden="true">🧮</span>
                        <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">{{ __('Isi nominal untuk melihat bagian tiap orang.') }}</p>
                    </div>
                @else
                    <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($this->perkiraanBagian as $urutan => $baris)
                            <li wire:key="perkiraan-{{ $urutan }}" class="flex items-center gap-3 px-5 py-2.5">
                                <x-inisial :nama="$baris['nama']" class="size-7 text-xs" />
                                <span class="min-w-0 flex-1 truncate text-sm text-zinc-700 dark:text-zinc-300">{{ $baris['nama'] }}</span>
                                <x-rupiah :jumlah="$baris['jumlah']" class="shrink-0 text-sm font-semibold text-zinc-900 tabular-nums dark:text-white" />
                            </li>
                        @endforeach
                    </ul>
                    <div class="flex items-center justify-between border-t border-zinc-100 px-5 py-3 dark:border-zinc-800">
                        <span class="text-sm font-medium text-zinc-600 dark:text-zinc-400">{{ __('Total') }}</span>
                        <x-rupiah :jumlah="collect($this->perkiraanBagian)->sum('jumlah')" class="font-bold text-zinc-900 tabular-nums dark:text-white" />
                    </div>
                @endif
            </x-kartu>

            <div class="flex gap-2 max-lg:sticky max-lg:bottom-4 max-lg:z-10 max-lg:rounded-2xl max-lg:bg-white/80 max-lg:p-2 max-lg:shadow-lg max-lg:ring-1 max-lg:ring-zinc-200 max-lg:backdrop-blur dark:max-lg:bg-zinc-900/80 dark:max-lg:ring-zinc-700">
                <flux:button :href="$tagihan ? route('tagihan.show', [$grup, $tagihan]) : route('grup.show', $grup)" variant="filled" wire:navigate class="flex-1">{{ __('Batal') }}</flux:button>
                <flux:button variant="primary" type="submit" data-test="simpan-tagihan" class="flex-1">{{ $tagihan ? __('Simpan') : __('Simpan tagihan') }}</flux:button>
            </div>
        </div>
    </form>
</section>
