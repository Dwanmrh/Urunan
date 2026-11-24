<?php

use App\Actions\Urunan\SederhanakanUtang;
use App\Models\AnggotaGrup;
use App\Models\Grup;
use App\Models\Tagihan;
use App\Models\TagihanRutin;
use App\Support\BatasAksi;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Detail grup')] class extends Component {
    public Grup $grup;

    public string $namaAnggota = '';

    public string $namaGrup = '';

    public ?int $idAnggotaDiubah = null;

    public string $namaAnggotaBaru = '';

    /**
     * Mount the component.
     */
    public function mount(Grup $grup): void
    {
        $this->grup = $grup;
        $this->namaGrup = $grup->nama;

        $this->pastikanAnggota();
    }

    /**
     * Apakah user yang sedang login adalah pembuat grup ini.
     */
    #[Computed]
    public function bisaMengelola(): bool
    {
        return $this->grup->bisaDikelolaOleh(Auth::user());
    }

    /**
     * Anggota grup beserta saldonya.
     *
     * @return Collection<int, array{anggota: AnggotaGrup, saldo: int, bisaDihapus: bool}>
     */
    #[Computed]
    public function anggotaDenganSaldo(): Collection
    {
        $saldo = $this->grup->saldo();

        return $this->grup->anggota()->with('grup')->orderBy('id')->get()
            ->map(fn (AnggotaGrup $anggota): array => [
                'anggota' => $anggota,
                'saldo' => $saldo[$anggota->id] ?? 0,
                'bisaDihapus' => $this->bisaMengelola && $anggota->bisaDihapus(),
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
     * @return Collection<int, TagihanRutin>
     */
    #[Computed]
    public function daftarRutin(): Collection
    {
        return $this->grup->tagihanRutin()->with('pembayar')->orderByDesc('aktif')->orderBy('jadwal_berikutnya')->get();
    }

    /**
     * Jeda atau lanjutkan tagihan rutin.
     */
    public function alihkanRutin(int $id): void
    {
        $rutin = $this->rutinYangBolehDiubah($id);

        if ($rutin->aktif) {
            $rutin->update(['aktif' => false]);
            Flux::toast(text: __('Tagihan rutin :judul dijeda.', ['judul' => $rutin->judul]));
        } else {
            $rutin->lanjutkan();
            Flux::toast(variant: 'success', text: __('Tagihan rutin :judul dilanjutkan.', ['judul' => $rutin->judul]));
        }

        unset($this->daftarRutin);
    }

    /**
     * Hentikan tagihan rutin. Tagihan yang sudah tercatat tetap ada.
     */
    public function hapusRutin(int $id): void
    {
        $rutin = $this->rutinYangBolehDiubah($id);
        $rutin->delete();

        unset($this->daftarRutin);

        Flux::toast(variant: 'success', text: __('Tagihan rutin dihentikan. Tagihan yang sudah tercatat tetap ada.'));
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

        if (! BatasAksi::boleh('tambah-anggota', 30)) {
            return;
        }

        $this->grup->anggota()->create(['nama' => $validated['namaAnggota']]);

        $this->reset('namaAnggota');

        unset($this->anggotaDenganSaldo, $this->rencanaPelunasan);

        Flux::toast(variant: 'success', text: __('Anggota ditambahkan.'));
    }

    /**
     * Ganti nama grup.
     */
    public function ubahNamaGrup(): void
    {
        $this->pastikanPengelola();

        $validated = $this->validate(
            ['namaGrup' => ['required', 'string', 'max:100']],
            [
                'namaGrup.required' => __('Nama grup wajib diisi.'),
                'namaGrup.max' => __('Nama grup maksimal 100 karakter.'),
            ],
        );

        $this->grup->update(['nama' => $validated['namaGrup']]);

        Flux::modal('ubah-grup')->close();
        Flux::toast(variant: 'success', text: __('Nama grup diperbarui.'));
    }

    /**
     * Hapus grup beserta semua isinya, lalu kembali ke daftar grup.
     */
    public function hapusGrup(): void
    {
        $this->pastikanPengelola();

        $this->grup->hapusSemua();

        Flux::toast(variant: 'success', text: __('Grup dihapus.'));

        $this->redirectRoute('grup.index', navigate: true);
    }

    /**
     * Buka form ganti nama untuk anggota tanpa akun.
     */
    public function ubahAnggota(int $id): void
    {
        $this->pastikanPengelola();

        $anggota = $this->anggotaTanpaAkun($id);

        $this->idAnggotaDiubah = $anggota->id;
        $this->namaAnggotaBaru = $anggota->nama;
        $this->resetValidation();

        Flux::modal('ubah-anggota')->show();
    }

    /**
     * Simpan nama baru anggota tanpa akun.
     */
    public function simpanAnggota(): void
    {
        $this->pastikanPengelola();

        $anggota = $this->anggotaTanpaAkun((int) $this->idAnggotaDiubah);

        $validated = $this->validate(
            ['namaAnggotaBaru' => ['required', 'string', 'max:60']],
            [
                'namaAnggotaBaru.required' => __('Nama anggota wajib diisi.'),
                'namaAnggotaBaru.max' => __('Nama anggota maksimal 60 karakter.'),
            ],
        );

        $anggota->update(['nama' => $validated['namaAnggotaBaru']]);

        $this->reset('idAnggotaDiubah', 'namaAnggotaBaru');
        unset($this->anggotaDenganSaldo, $this->rencanaPelunasan, $this->daftarTagihan);

        Flux::modal('ubah-anggota')->close();
        Flux::toast(variant: 'success', text: __('Nama anggota diperbarui.'));
    }

    /**
     * Keluarkan anggota yang belum tercatat di tagihan mana pun.
     */
    public function hapusAnggota(int $id): void
    {
        $this->pastikanPengelola();

        $anggota = $this->grup->anggota()->findOrFail($id);

        if (! $anggota->bisaDihapus()) {
            Flux::toast(variant: 'warning', text: __(':nama sudah tercatat di tagihan, jadi tidak bisa dikeluarkan.', ['nama' => $anggota->nama]));

            return;
        }

        $anggota->delete();

        unset($this->anggotaDenganSaldo, $this->rencanaPelunasan);

        Flux::toast(variant: 'success', text: __(':nama dikeluarkan dari grup.', ['nama' => $anggota->nama]));
    }

    /**
     * Ambil anggota tanpa akun di grup ini. Nama anggota yang punya akun mengikuti akunnya.
     */
    private function anggotaTanpaAkun(int $id): AnggotaGrup
    {
        $anggota = $this->grup->anggota()->findOrFail($id);

        abort_if($anggota->user_id !== null, 403);

        return $anggota;
    }

    /**
     * Ambil tagihan rutin di grup ini yang boleh diubah user.
     */
    private function rutinYangBolehDiubah(int $id): TagihanRutin
    {
        $this->pastikanAnggota();

        $rutin = $this->grup->tagihanRutin()->findOrFail($id);

        abort_unless($rutin->bisaDiubahOleh(Auth::user()), 403);

        return $rutin;
    }

    /**
     * Tolak akses kalau user bukan pembuat grup ini.
     */
    private function pastikanPengelola(): void
    {
        $this->pastikanAnggota();

        abort_unless($this->grup->bisaDikelolaOleh(Auth::user()), 403);
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
    <x-tur nama="detail-grup" :langkah="[
        ['elemen' => '[data-tur=link-undangan]', 'judul' => __('Ajak teman'), 'isi' => __('Salin link ini lalu kirim ke grup WhatsApp. Teman yang membukanya bisa daftar dan langsung bergabung.')],
        ['elemen' => '[data-tur=tambah-anggota]', 'judul' => __('Anggota tanpa akun'), 'isi' => __('Teman yang belum mau daftar tetap bisa dicatat. Kalau nanti dia bergabung lewat link, tagihannya ikut tersambung.')],
        ['elemen' => '[data-tur=tambah-tagihan]', 'judul' => __('Catat tagihan'), 'isi' => __('Siapa yang membayar dulu mencatat tagihannya di sini, lalu Urunan membagi ke tiap anggota.'), 'sisi' => 'left'],
        ['elemen' => '[data-tur=rencana-pelunasan]', 'judul' => __('Siapa bayar ke siapa'), 'isi' => __('Urunan meringkas utang semua anggota jadi transfer sesedikit mungkin.')],
        ['elemen' => '[data-tur=kelola-grup]', 'judul' => __('Kelola grup'), 'isi' => __('Sebagai pembuat grup, kamu bisa mengganti nama atau menghapus grup dari menu ini.'), 'sisi' => 'left'],
    ]" />

    <div class="mb-6">
        <flux:button :href="route('grup.index')" variant="subtle" icon="arrow-left" size="sm" wire:navigate class="mb-3">{{ __('Semua grup') }}</flux:button>
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <flux:heading size="xl" level="1" class="break-words">{{ $grup->nama }}</flux:heading>
                <flux:subheading size="lg" class="mb-4">{{ __(':jumlah anggota', ['jumlah' => $this->anggotaDenganSaldo->count()]) }}</flux:subheading>
            </div>

            @if ($this->bisaMengelola)
                <flux:dropdown position="bottom" align="end">
                    <flux:button icon="ellipsis-horizontal" size="sm" variant="ghost" aria-label="{{ __('Kelola grup') }}" data-test="kelola-grup" data-tur="kelola-grup" />

                    <flux:menu>
                        <flux:menu.item icon="pencil-square" x-on:click="$flux.modal('ubah-grup').show()" data-test="ubah-nama-grup">{{ __('Ganti nama grup') }}</flux:menu.item>
                        <flux:menu.separator />
                        <flux:menu.item
                            icon="trash"
                            variant="danger"
                            data-test="hapus-grup"
                            x-on:click="$dispatch('konfirmasi', {
                                judul: {{ \Illuminate\Support\Js::from(__('Hapus grup :grup?', ['grup' => $grup->nama])) }},
                                pesan: {{ \Illuminate\Support\Js::from(__('Semua tagihan, pembayaran, dan bukti bayar di grup ini ikut terhapus. Tindakan ini tidak bisa dibatalkan.')) }},
                                tombol: {{ \Illuminate\Support\Js::from(__('Hapus grup')) }},
                                bahaya: true,
                                aksi: () => $wire.hapusGrup(),
                            })"
                        >{{ __('Hapus grup') }}</flux:menu.item>
                    </flux:menu>
                </flux:dropdown>
            @endif
        </div>

        <div class="max-w-xl" data-tur="link-undangan">
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

                        <div class="flex items-center gap-1">
                            @if ($baris['saldo'] < 0)
                                <flux:badge color="red">{{ __('Utang :jumlah', ['jumlah' => \App\Actions\Urunan\FormatRupiah::rp($baris['saldo'])]) }}</flux:badge>
                            @elseif ($baris['saldo'] > 0)
                                <flux:badge color="green">{{ __('Menerima :jumlah', ['jumlah' => \App\Actions\Urunan\FormatRupiah::rp($baris['saldo'])]) }}</flux:badge>
                            @else
                                <flux:badge color="zinc">{{ __('Lunas') }}</flux:badge>
                            @endif

                            @if ($this->bisaMengelola && (! $baris['anggota']->user_id || $baris['bisaDihapus']))
                                <flux:dropdown position="bottom" align="end">
                                    <flux:button icon="ellipsis-vertical" size="sm" variant="subtle" aria-label="{{ __('Atur :nama', ['nama' => $baris['anggota']->nama]) }}" data-test="atur-anggota-{{ $baris['anggota']->id }}" />

                                    <flux:menu>
                                        @unless ($baris['anggota']->user_id)
                                            <flux:menu.item icon="pencil-square" wire:click="ubahAnggota({{ $baris['anggota']->id }})">{{ __('Ganti nama') }}</flux:menu.item>
                                        @endunless

                                        @if ($baris['bisaDihapus'])
                                            <flux:menu.item
                                                icon="user-minus"
                                                variant="danger"
                                                x-on:click="$dispatch('konfirmasi', {
                                                    judul: {{ \Illuminate\Support\Js::from(__('Keluarkan :nama?', ['nama' => $baris['anggota']->nama])) }},
                                                    pesan: {{ \Illuminate\Support\Js::from(__(':nama belum tercatat di tagihan mana pun, jadi saldo grup tidak berubah.', ['nama' => $baris['anggota']->nama])) }},
                                                    tombol: {{ \Illuminate\Support\Js::from(__('Keluarkan')) }},
                                                    bahaya: true,
                                                    aksi: () => $wire.hapusAnggota({{ $baris['anggota']->id }}),
                                                })"
                                            >{{ __('Keluarkan dari grup') }}</flux:menu.item>
                                        @endif
                                    </flux:menu>
                                </flux:dropdown>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>

            <form wire:submit="tambahAnggota" class="flex items-end gap-3" data-tur="tambah-anggota">
                <div class="flex-1">
                    <flux:input wire:model="namaAnggota" label="{{ __('Tambah anggota') }}" type="text" placeholder="{{ __('Nama, misalnya Sinta') }}" />
                </div>

                <flux:button variant="primary" type="submit" data-test="tambah-anggota">{{ __('Tambah') }}</flux:button>
            </form>
        </flux:card>

        <div class="space-y-6">
            <flux:card class="space-y-4" data-tur="rencana-pelunasan">
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

            @if ($this->daftarRutin->isNotEmpty())
                <flux:card class="space-y-4" data-tur="tagihan-rutin">
                    <div class="flex items-center gap-2">
                        <flux:icon.arrow-path class="size-5 text-indigo-500" />
                        <flux:heading size="lg" level="2">{{ __('Tagihan rutin') }}</flux:heading>
                    </div>

                    <ul class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @foreach ($this->daftarRutin as $rutin)
                            <li wire:key="rutin-{{ $rutin->id }}" class="flex items-center justify-between gap-3 py-3" data-test="tagihan-rutin">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <flux:heading class="truncate">{{ $rutin->judul }}</flux:heading>
                                        @if ($rutin->aktif)
                                            <flux:badge size="sm" color="indigo" inset="top bottom">{{ $rutin->frekuensi->label() }}</flux:badge>
                                        @else
                                            <flux:badge size="sm" color="zinc" inset="top bottom">{{ __('Dijeda') }}</flux:badge>
                                        @endif
                                    </div>
                                    <flux:text size="sm">
                                        <x-rupiah :jumlah="$rutin->total" />
                                        · {{ __('ke :nama', ['nama' => $rutin->pembayar->nama]) }}
                                        @if ($rutin->aktif)
                                            · {{ __('berikutnya :tanggal', ['tanggal' => $rutin->jadwal_berikutnya->format('d/m/Y')]) }}
                                        @endif
                                    </flux:text>
                                </div>

                                @if ($rutin->bisaDiubahOleh(auth()->user()))
                                    <flux:dropdown align="end">
                                        <flux:button icon="ellipsis-horizontal" size="sm" variant="ghost" aria-label="{{ __('Kelola tagihan rutin') }}" data-test="kelola-rutin" />
                                        <flux:menu>
                                            <flux:menu.item
                                                :icon="$rutin->aktif ? 'pause' : 'play'"
                                                wire:click="alihkanRutin({{ $rutin->id }})"
                                                data-test="alihkan-rutin"
                                            >{{ $rutin->aktif ? __('Jeda') : __('Lanjutkan') }}</flux:menu.item>
                                            <flux:menu.separator />
                                            <flux:menu.item
                                                icon="trash"
                                                variant="danger"
                                                data-test="hapus-rutin"
                                                x-on:click="$dispatch('konfirmasi', {
                                                    judul: {{ \Illuminate\Support\Js::from(__('Hentikan :judul?', ['judul' => $rutin->judul])) }},
                                                    pesan: {{ \Illuminate\Support\Js::from(__('Tagihan tidak akan dicatat otomatis lagi. Tagihan yang sudah tercatat tetap ada.')) }},
                                                    tombol: {{ \Illuminate\Support\Js::from(__('Hentikan')) }},
                                                    bahaya: true,
                                                    aksi: () => $wire.hapusRutin({{ $rutin->id }}),
                                                })"
                                            >{{ __('Hentikan') }}</flux:menu.item>
                                        </flux:menu>
                                    </flux:dropdown>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </flux:card>
            @endif

            <flux:card class="space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <flux:heading size="lg" level="2">{{ __('Tagihan') }}</flux:heading>
                    <flux:button :href="route('tagihan.create', $grup)" variant="primary" size="sm" icon="plus" wire:navigate data-test="tambah-tagihan" data-tur="tambah-tagihan">{{ __('Tambah tagihan') }}</flux:button>
                </div>

                @if ($this->daftarTagihan->isEmpty())
                    <flux:text>{{ __('Belum ada tagihan di grup ini.') }}</flux:text>
                @else
                    <ul class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @foreach ($this->daftarTagihan as $tagihan)
                            <li wire:key="tagihan-{{ $tagihan->id }}">
                                <a href="{{ route('tagihan.show', [$grup, $tagihan]) }}" wire:navigate class="flex items-center justify-between gap-3 py-3 [&>*:first-child]:min-w-0 [&>*:last-child]:shrink-0 hover:opacity-80">
                                    <div>
                                        <flux:heading class="flex items-center gap-1.5">
                                            @if ($tagihan->tagihan_rutin_id)
                                                <flux:icon.arrow-path variant="micro" class="shrink-0 text-indigo-500" aria-label="{{ __('Tagihan rutin') }}" />
                                            @endif
                                            <span class="truncate">{{ $tagihan->judul }}</span>
                                        </flux:heading>
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

    @if ($this->bisaMengelola)
        <flux:modal name="ubah-grup" focusable class="w-full max-w-lg">
            <form wire:submit="ubahNamaGrup" class="space-y-6">
                <flux:heading size="lg">{{ __('Ganti nama grup') }}</flux:heading>

                <flux:input wire:model="namaGrup" label="{{ __('Nama grup') }}" type="text" required />

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="filled">{{ __('Batal') }}</flux:button>
                    </flux:modal.close>

                    <flux:button variant="primary" type="submit" data-test="simpan-nama-grup">{{ __('Simpan') }}</flux:button>
                </div>
            </form>
        </flux:modal>

        <flux:modal name="ubah-anggota" focusable class="w-full max-w-lg">
            <form wire:submit="simpanAnggota" class="space-y-6">
                <flux:heading size="lg">{{ __('Ganti nama anggota') }}</flux:heading>

                <flux:input wire:model="namaAnggotaBaru" label="{{ __('Nama') }}" type="text" required />

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="filled">{{ __('Batal') }}</flux:button>
                    </flux:modal.close>

                    <flux:button variant="primary" type="submit" data-test="simpan-nama-anggota">{{ __('Simpan') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    @endif
</section>
