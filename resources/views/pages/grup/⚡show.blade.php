<?php

use App\Actions\Urunan\FormatRupiah;
use App\Actions\Urunan\RaporGrup;
use App\Actions\Urunan\SederhanakanUtang;
use App\Enums\Frekuensi;
use App\Enums\WarnaGrup;
use App\Models\AnggotaGrup;
use App\Models\Grup;
use App\Models\Tagihan;
use App\Models\TagihanRutin;
use App\Support\BatasAksi;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Detail grup')] class extends Component {
    public Grup $grup;

    public string $namaAnggota = '';

    public string $nama = '';

    public string $emoji = '🎉';

    public string $warna = 'indigo';

    public ?int $idRutinDiubah = null;

    public string $judulRutin = '';

    public string $totalRutin = '';

    public string $frekuensiRutin = 'bulanan';

    /** @var list<string> */
    public array $pesertaRutin = [];

    public ?int $idAnggotaDiubah = null;

    public string $namaAnggotaBaru = '';

    /**
     * Mount the component.
     */
    public function mount(Grup $grup): void
    {
        $this->grup = $grup;
        $this->isiTampilanGrup();

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
     * Lencana seru untuk anggota grup.
     *
     * @return list<array{kunci: string, emoji: string, judul: string, nama: string, keterangan: string}>
     */
    #[Computed]
    public function rapor(): array
    {
        return RaporGrup::untuk($this->grup);
    }

    /**
     * Anggota yang sedang login di grup ini.
     */
    #[Computed]
    public function anggotaSaya(): ?AnggotaGrup
    {
        return $this->grup->anggota()->where('user_id', Auth::id())->first();
    }

    /**
     * Pesan ajakan bergabung untuk dikirim ke WhatsApp.
     */
    #[Computed]
    public function pesanUndangan(): string
    {
        return __('Yuk gabung grup patungan *:grup* :emoji di Udunan, biar urusan bayar-bayaran jelas dan nggak ada yang kelupaan:', ['grup' => $this->grup->nama, 'emoji' => $this->grup->emoji])
            ."\n".route('grup.gabung', $this->grup->kode_undangan);
    }

    /**
     * Ganti nama, emoji, dan warna grup.
     */
    public function ubahNamaGrup(): void
    {
        $this->pastikanPengelola();

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

        $this->grup->update($validated);

        Flux::modal('ubah-grup')->close();
        Flux::toast(variant: 'success', text: __('Grup diperbarui.'));
    }

    /**
     * Kembalikan isian form ubah grup ke data tersimpan.
     */
    public function isiTampilanGrup(): void
    {
        $this->nama = $this->grup->nama;
        $this->emoji = $this->grup->emoji;
        $this->warna = $this->grup->warna->value;
        $this->resetValidation();
    }

    /**
     * Keluar dari grup. Kalau sudah tercatat di tagihan, namamu tetap ada sebagai anggota tanpa akun.
     */
    public function keluarGrup(): void
    {
        $this->pastikanAnggota();

        abort_if($this->grup->bisaDikelolaOleh(Auth::user()), 403);

        $this->grup->keluarkan(Auth::user());

        Flux::toast(variant: 'success', text: __('Kamu keluar dari :grup.', ['grup' => $this->grup->nama]));

        $this->redirectRoute('grup.index', navigate: true);
    }

    /**
     * Buka form ubah tagihan rutin.
     */
    public function ubahRutin(int $id): void
    {
        $rutin = $this->rutinYangBolehDiubah($id);

        $this->idRutinDiubah = $rutin->id;
        $this->judulRutin = $rutin->judul;
        $this->totalRutin = FormatRupiah::keTeks($rutin->total);
        $this->frekuensiRutin = $rutin->frekuensi->value;
        $this->pesertaRutin = array_map('strval', $rutin->peserta);
        $this->resetValidation();

        Flux::modal('ubah-rutin')->show();
    }

    /**
     * Simpan perubahan tagihan rutin. Berlaku untuk tagihan berikutnya.
     */
    public function simpanRutin(): void
    {
        $rutin = $this->rutinYangBolehDiubah((int) $this->idRutinDiubah);
        $idAnggota = $this->grup->anggota()->pluck('id')->all();

        $this->totalRutin = FormatRupiah::bersihkan($this->totalRutin);

        $validated = $this->validate(
            [
                'judulRutin' => ['required', 'string', 'max:100'],
                'totalRutin' => ['required', 'integer', 'min:1', 'max:1000000000'],
                'frekuensiRutin' => ['required', Rule::enum(Frekuensi::class)],
                'pesertaRutin' => ['required', 'array', 'min:1'],
                'pesertaRutin.*' => [Rule::in($idAnggota)],
            ],
            [
                'judulRutin.required' => __('Judul tagihan wajib diisi.'),
                'judulRutin.max' => __('Judul maksimal 100 karakter.'),
                'totalRutin.required' => __('Total tagihan wajib diisi.'),
                'totalRutin.integer' => __('Total harus berupa angka.'),
                'totalRutin.min' => __('Total minimal Rp 1.'),
                'totalRutin.max' => __('Total terlalu besar.'),
                'pesertaRutin.required' => __('Pilih minimal satu orang yang ikut menanggung.'),
                'pesertaRutin.min' => __('Pilih minimal satu orang yang ikut menanggung.'),
                'pesertaRutin.*.in' => __('Ada peserta yang bukan anggota grup ini.'),
            ],
        );

        $rutin->ubah([
            'judul' => $validated['judulRutin'],
            'total' => (int) $validated['totalRutin'],
            'frekuensi' => Frekuensi::from($validated['frekuensiRutin']),
            'peserta' => array_map('intval', $validated['pesertaRutin']),
        ]);

        $this->reset('idRutinDiubah', 'judulRutin', 'totalRutin', 'pesertaRutin');
        unset($this->daftarRutin);

        Flux::modal('ubah-rutin')->close();
        Flux::toast(variant: 'success', text: __('Tagihan rutin diperbarui. Berlaku mulai tagihan berikutnya.'));
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

<section class="w-full space-y-6">
    <x-tur nama="grup-detail" :langkah="[
        ['elemen' => '[data-tur=link-undangan]', 'judul' => __('Ajak teman'), 'isi' => __('Kirim link undangan ke grup WhatsApp. Teman yang membukanya bisa daftar dan langsung bergabung.')],
        ['elemen' => '[data-tur=tambah-anggota]', 'judul' => __('Anggota tanpa akun'), 'isi' => __('Teman yang belum mau daftar tetap bisa dicatat. Kalau nanti dia bergabung lewat link, tagihannya ikut tersambung.')],
        ['elemen' => '[data-tur=tambah-tagihan]', 'judul' => __('Catat tagihan'), 'isi' => __('Siapa yang membayar dulu mencatat tagihannya di sini, lalu Udunan membagi ke tiap anggota.'), 'sisi' => 'bottom'],
        ['elemen' => '[data-tur=rencana-pelunasan]', 'judul' => __('Siapa bayar ke siapa'), 'isi' => __('Udunan meringkas utang semua anggota jadi transfer sesedikit mungkin.')],
        ['elemen' => '[data-tur=kelola-grup]', 'judul' => __('Atur grup'), 'isi' => __('Ganti nama, emoji, dan warna grup, atau keluar dari grup lewat menu ini.'), 'sisi' => 'left'],
    ]" />

    <a href="{{ route('grup.index') }}" wire:navigate class="-ms-2 inline-flex items-center gap-1.5 rounded-lg px-2 py-1 text-sm font-medium text-zinc-600 dark:text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-900 dark:hover:bg-white/5 dark:hover:text-white">
        <flux:icon.arrow-left variant="micro" />
        {{ __('Semua grup') }}
    </a>

    {{-- Sampul grup --}}
    <section class="relative overflow-hidden rounded-3xl bg-linear-to-br {{ $grup->warna->gradasi() }} {{ $grup->warna->teks() }} p-6 shadow-lg motion-safe:animate-[muncul_0.5s_ease-out_both] sm:p-8">
        <div aria-hidden="true" class="absolute -top-20 -right-16 size-64 rounded-full bg-white/10 blur-2xl"></div>
        <div aria-hidden="true" class="absolute -bottom-24 left-1/3 size-56 rounded-full bg-white/10 blur-3xl"></div>

        <div class="relative flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between">
            <div class="flex min-w-0 items-center gap-4">
                <span class="flex size-16 shrink-0 items-center justify-center rounded-2xl text-4xl ring-1 backdrop-blur {{ $grup->warna->kaca() }}" aria-hidden="true">{{ $grup->emoji }}</span>
                <div class="min-w-0">
                    <h1 class="text-2xl font-bold tracking-tight break-words sm:text-3xl">{{ $grup->nama }}</h1>
                    <div class="mt-2 flex items-center gap-3">
                        <div class="flex -space-x-2">
                            @foreach ($this->anggotaDenganSaldo->take(5) as $baris)
                                <x-inisial :nama="$baris['anggota']->nama" class="size-7 text-xs ring-2 ring-white/40" />
                            @endforeach
                        </div>
                        <p class="text-sm font-medium {{ $grup->warna->teksRedup() }}">{{ trans_choice(':jumlah anggota', $this->anggotaDenganSaldo->count(), ['jumlah' => $this->anggotaDenganSaldo->count()]) }}</p>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('tagihan.create', $grup) }}" wire:navigate data-test="tambah-tagihan" data-tur="tambah-tagihan" class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-zinc-900 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                    <flux:icon.plus variant="mini" class="size-4" />
                    {{ __('Tambah tagihan') }}
                </a>

                <flux:dropdown align="end">
                    <button type="button" class="flex size-10 items-center justify-center rounded-xl ring-1 backdrop-blur transition {{ $grup->warna->kaca() }}" aria-label="{{ __('Atur grup') }}" data-test="kelola-grup" data-tur="kelola-grup">
                        <flux:icon.ellipsis-horizontal variant="mini" />
                    </button>

                    <flux:menu>
                        @if ($this->bisaMengelola)
                            <flux:menu.item icon="paint-brush" wire:click="isiTampilanGrup" x-on:click="$flux.modal('ubah-grup').show()" data-test="ubah-nama-grup">{{ __('Ubah nama & tampilan') }}</flux:menu.item>
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
                        @else
                            <flux:menu.item
                                icon="arrow-right-start-on-rectangle"
                                variant="danger"
                                data-test="keluar-grup"
                                x-on:click="$dispatch('konfirmasi', {
                                    judul: {{ \Illuminate\Support\Js::from(__('Keluar dari :grup?', ['grup' => $grup->nama])) }},
                                    pesan: {{ \Illuminate\Support\Js::from(__('Tagihan dan pembayaran yang sudah tercatat atas namamu tetap ada di grup, tapi kamu tidak bisa membukanya lagi kecuali diundang ulang.')) }},
                                    tombol: {{ \Illuminate\Support\Js::from(__('Keluar')) }},
                                    bahaya: true,
                                    aksi: () => $wire.keluarGrup(),
                                })"
                            >{{ __('Keluar dari grup') }}</flux:menu.item>
                        @endif
                    </flux:menu>
                </flux:dropdown>
            </div>
        </div>
    </section>

    {{-- Rapor grup --}}
    @if ($this->rapor !== [])
        <section class="-mx-4 overflow-x-auto px-4 sm:mx-0 sm:px-0" aria-label="{{ __('Rapor grup') }}">
            <ul class="flex gap-3 sm:grid sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($this->rapor as $urutan => $lencana)
                    <li wire:key="lencana-{{ $lencana['kunci'] }}" class="flex w-64 shrink-0 items-center gap-3 rounded-2xl border border-zinc-200 bg-white p-4 transition hover:-translate-y-0.5 hover:shadow-md motion-safe:animate-[muncul_0.4s_ease-out_both] sm:w-auto dark:border-zinc-700/70 dark:bg-zinc-900" style="animation-delay: {{ 100 + $urutan * 70 }}ms" data-test="lencana">
                        <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-zinc-50 text-2xl dark:bg-white/5" aria-hidden="true">{{ $lencana['emoji'] }}</span>
                        <div class="min-w-0">
                            <p class="text-xs font-semibold tracking-wide text-indigo-600 uppercase dark:text-indigo-300">{{ $lencana['judul'] }}</p>
                            <p class="truncate font-semibold text-zinc-900 dark:text-white">{{ $lencana['nama'] }}</p>
                            <p class="truncate text-xs text-zinc-600 dark:text-zinc-400">{{ $lencana['keterangan'] }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <div class="grid gap-6 lg:grid-cols-[1fr_22rem]">
        <div class="min-w-0 space-y-6">
            {{-- Siapa bayar ke siapa --}}
            <x-kartu :judul="__('Siapa bayar ke siapa')" ikon="arrows-right-left" padat data-tur="rencana-pelunasan">
                @if ($this->rencanaPelunasan === [])
                    <div class="flex items-center gap-3 px-5 py-5">
                        <span class="text-2xl" aria-hidden="true">✨</span>
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('Tidak ada yang perlu dibayar. Semua aman!') }}</p>
                    </div>
                @else
                    <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($this->rencanaPelunasan as $urutan => $pembayaran)
                            <li wire:key="pelunasan-{{ $urutan }}" class="flex items-center gap-3 px-5 py-3.5">
                                <x-inisial :nama="$pembayaran['dari']" />
                                <div class="min-w-0 flex-1">
                                    <p class="flex items-center gap-1.5 truncate text-sm font-medium text-zinc-900 dark:text-white">
                                        <span class="truncate">{{ $pembayaran['dari'] }}</span>
                                        <flux:icon.arrow-long-right variant="micro" class="shrink-0 text-zinc-500 dark:text-zinc-400" />
                                        <span class="truncate">{{ $pembayaran['ke'] }}</span>
                                    </p>
                                    <p class="text-xs text-zinc-600 dark:text-zinc-400">{{ __(':dari membayar ke :ke', ['dari' => $pembayaran['dari'], 'ke' => $pembayaran['ke']]) }}</p>
                                </div>
                                <x-rupiah :jumlah="$pembayaran['jumlah']" class="shrink-0 font-semibold text-zinc-900 tabular-nums dark:text-white" />
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-kartu>

            {{-- Tagihan rutin --}}
            @if ($this->daftarRutin->isNotEmpty())
                <x-kartu :judul="__('Tagihan rutin')" ikon="arrow-path" :jumlah="$this->daftarRutin->count()" padat data-tur="tagihan-rutin">
                    <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($this->daftarRutin as $rutin)
                            <li wire:key="rutin-{{ $rutin->id }}" class="flex items-center gap-3 px-5 py-3.5" data-test="tagihan-rutin">
                                <span @class([
                                    'flex size-9 shrink-0 items-center justify-center rounded-xl',
                                    'bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-300' => $rutin->aktif,
                                    'bg-zinc-100 text-zinc-500 dark:text-zinc-400 dark:bg-zinc-800' => ! $rutin->aktif,
                                ])>
                                    <flux:icon :icon="$rutin->aktif ? 'arrow-path' : 'pause'" variant="mini" class="size-4" />
                                </span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="truncate font-medium text-zinc-900 dark:text-white">{{ $rutin->judul }}</p>
                                        @if ($rutin->aktif)
                                            <span class="rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-semibold text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300">{{ $rutin->frekuensi->label() }}</span>
                                        @else
                                            <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-semibold text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">{{ __('Dijeda') }}</span>
                                        @endif
                                    </div>
                                    <p class="truncate text-xs text-zinc-600 dark:text-zinc-400">
                                        {{ __('ke :nama', ['nama' => $rutin->pembayar->nama]) }}
                                        @if ($rutin->aktif)
                                            · {{ __('berikutnya :tanggal', ['tanggal' => $rutin->jadwal_berikutnya->format('d/m/Y')]) }}
                                        @endif
                                    </p>
                                </div>
                                <x-rupiah :jumlah="$rutin->total" class="shrink-0 text-sm font-semibold text-zinc-900 tabular-nums dark:text-white" />

                                @if ($rutin->bisaDiubahOleh(auth()->user()))
                                    <flux:dropdown align="end">
                                        <flux:button icon="ellipsis-vertical" size="sm" variant="subtle" aria-label="{{ __('Kelola tagihan rutin') }}" data-test="kelola-rutin" />
                                        <flux:menu>
                                            <flux:menu.item icon="pencil-square" wire:click="ubahRutin({{ $rutin->id }})" data-test="ubah-rutin">{{ __('Edit') }}</flux:menu.item>
                                            <flux:menu.item :icon="$rutin->aktif ? 'pause' : 'play'" wire:click="alihkanRutin({{ $rutin->id }})" data-test="alihkan-rutin">{{ $rutin->aktif ? __('Jeda') : __('Lanjutkan') }}</flux:menu.item>
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
                </x-kartu>
            @endif

            {{-- Tagihan --}}
            <x-kartu :judul="__('Tagihan')" ikon="receipt-percent" :jumlah="$this->daftarTagihan->count() ?: null" padat>
                <x-slot:aksi>
                    <flux:button :href="route('tagihan.create', $grup)" size="sm" icon="plus" wire:navigate>{{ __('Tambah') }}</flux:button>
                </x-slot:aksi>

                @if ($this->daftarTagihan->isEmpty())
                    <div class="flex flex-col items-center px-5 py-10 text-center">
                        <span class="text-4xl" aria-hidden="true">🧾</span>
                        <p class="mt-3 font-medium text-zinc-900 dark:text-white">{{ __('Belum ada tagihan di grup ini.') }}</p>
                        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">{{ __('Yang bayar duluan, catat di sini biar langsung dibagi.') }}</p>
                    </div>
                @else
                    <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($this->daftarTagihan as $tagihan)
                            <li wire:key="tagihan-{{ $tagihan->id }}">
                                <a href="{{ route('tagihan.show', [$grup, $tagihan]) }}" wire:navigate class="group flex items-center gap-3 px-5 py-3.5 transition hover:bg-zinc-50 dark:hover:bg-white/5">
                                    <div class="flex w-11 shrink-0 flex-col items-center rounded-xl bg-zinc-50 py-1 dark:bg-white/5">
                                        <span class="text-xs font-semibold text-zinc-600 dark:text-zinc-400 uppercase">{{ $tagihan->tanggal->locale(app()->getLocale())->translatedFormat('M') }}</span>
                                        <span class="text-base leading-tight font-bold text-zinc-900 dark:text-white">{{ $tagihan->tanggal->format('d') }}</span>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="flex items-center gap-1.5 font-medium text-zinc-900 dark:text-white">
                                            @if ($tagihan->tagihan_rutin_id)
                                                <flux:icon.arrow-path variant="micro" class="shrink-0 text-indigo-500" aria-label="{{ __('Tagihan rutin') }}" />
                                            @endif
                                            <span class="truncate">{{ $tagihan->judul }}</span>
                                        </p>
                                        <p class="truncate text-sm text-zinc-600 dark:text-zinc-400">{{ __('Dibayar dulu oleh :nama', ['nama' => $tagihan->pembayar->nama]) }}</p>
                                    </div>
                                    <x-rupiah :jumlah="$tagihan->total" class="shrink-0 font-semibold text-zinc-900 tabular-nums dark:text-white" />
                                    <flux:icon.chevron-right variant="micro" class="shrink-0 text-zinc-300 transition group-hover:translate-x-0.5 dark:text-zinc-600" />
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-kartu>
        </div>

        <div class="min-w-0 space-y-6">
            {{-- Undangan --}}
            <x-kartu :judul="__('Ajak teman')" ikon="link" data-tur="link-undangan">
                <div x-data="{ tersalin: false, salin() { navigator.clipboard?.writeText(@js(route('grup.gabung', $grup->kode_undangan))); this.tersalin = true; setTimeout(() => this.tersalin = false, 1800) } }" class="space-y-3">
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('Bagikan ke grup WhatsApp. Teman yang membuka link ini bisa daftar lalu bergabung.') }}</p>
                    <button type="button" x-on:click="salin()" class="flex w-full items-center gap-2 rounded-xl border border-dashed border-zinc-300 bg-zinc-50 px-3 py-2.5 text-start text-sm transition hover:border-indigo-400 dark:border-zinc-700 dark:bg-white/5" data-test="salin-undangan">
                        <span class="min-w-0 flex-1 truncate font-mono text-xs text-zinc-600 dark:text-zinc-300">{{ route('grup.gabung', $grup->kode_undangan) }}</span>
                        <span class="shrink-0 font-semibold text-indigo-600 dark:text-indigo-300" x-text="tersalin ? @js(__('Tersalin! ✓')) : @js(__('Salin'))">{{ __('Salin') }}</span>
                    </button>
                    <a href="{{ \App\Actions\Urunan\PesanTagih::linkWhatsapp($this->pesanUndangan) }}" target="_blank" rel="noopener" class="flex w-full items-center justify-center gap-2 rounded-xl bg-[#25D366] px-4 py-2.5 text-sm font-semibold text-white transition hover:brightness-95">
                        <x-ikon-whatsapp class="size-4" />
                        {{ __('Kirim ke WhatsApp') }}
                    </a>
                </div>
            </x-kartu>

            {{-- Anggota --}}
            <x-kartu :judul="__('Anggota')" ikon="users" :jumlah="$this->anggotaDenganSaldo->count()" padat>
                <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach ($this->anggotaDenganSaldo as $baris)
                        <li wire:key="anggota-{{ $baris['anggota']->id }}" class="flex items-center gap-3 px-5 py-3">
                            <x-inisial :nama="$baris['anggota']->nama" />
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-medium text-zinc-900 dark:text-white">
                                    {{ $baris['anggota']->nama }}
                                    @if ($baris['anggota']->user_id === auth()->id())
                                        <span class="text-xs font-normal text-zinc-600 dark:text-zinc-400">({{ __('kamu') }})</span>
                                    @endif
                                </p>
                                @if ($baris['saldo'] < 0)
                                    <p class="text-xs font-medium text-red-600 dark:text-red-400">{{ __('Utang :jumlah', ['jumlah' => \App\Actions\Urunan\FormatRupiah::rp($baris['saldo'])]) }}</p>
                                @elseif ($baris['saldo'] > 0)
                                    <p class="text-xs font-medium text-emerald-600 dark:text-emerald-400">{{ __('Menerima :jumlah', ['jumlah' => \App\Actions\Urunan\FormatRupiah::rp($baris['saldo'])]) }}</p>
                                @else
                                    <p class="text-xs text-zinc-600 dark:text-zinc-400">{{ __('Lunas') }} · {{ $baris['anggota']->user_id ? __('Punya akun') : __('Tanpa akun') }}</p>
                                @endif
                            </div>

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
                        </li>
                    @endforeach
                </ul>

                <form wire:submit="tambahAnggota" class="flex items-start gap-2 border-t border-zinc-100 p-4 dark:border-zinc-800" data-tur="tambah-anggota">
                    <div class="flex-1">
                        <flux:input wire:model="namaAnggota" type="text" size="sm" placeholder="{{ __('Tambah anggota tanpa akun') }}" aria-label="{{ __('Tambah anggota') }}" />
                    </div>
                    <flux:button variant="primary" size="sm" type="submit" icon="plus" data-test="tambah-anggota">{{ __('Tambah') }}</flux:button>
                </form>
            </x-kartu>
        </div>
    </div>

    @if ($this->bisaMengelola)
        <flux:modal name="ubah-grup" focusable class="w-full max-w-lg">
            <form wire:submit="ubahNamaGrup" class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Ubah nama & tampilan') }}</flux:heading>
                    <flux:subheading>{{ __('Emoji dan warna bikin grup gampang dikenali di daftar.') }}</flux:subheading>
                </div>

                @include('partials.pilih-tampilan-grup')

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

    <flux:modal name="ubah-rutin" focusable class="w-full max-w-lg">
        <form wire:submit="simpanRutin" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Edit tagihan rutin') }}</flux:heading>
                <flux:subheading>{{ __('Berlaku mulai tagihan berikutnya. Tagihan yang sudah tercatat tidak berubah.') }}</flux:subheading>
            </div>

            <flux:input wire:model="judulRutin" label="{{ __('Judul') }}" type="text" required />

            <flux:input wire:model="totalRutin" label="{{ __('Total tagihan') }}" type="text" inputmode="numeric" mask:dynamic="$money($input, ',', '.', 0)" required>
                <x-slot name="iconLeading">
                    <span class="text-sm text-zinc-600 dark:text-zinc-400">Rp</span>
                </x-slot>
            </flux:input>

            <flux:radio.group wire:model="frekuensiRutin" label="{{ __('Ulangi') }}" variant="segmented">
                @foreach (\App\Enums\Frekuensi::cases() as $pilihan)
                    <flux:radio value="{{ $pilihan->value }}" label="{{ $pilihan->label() }}" wire:key="ubah-frekuensi-{{ $pilihan->value }}" />
                @endforeach
            </flux:radio.group>

            <flux:checkbox.group wire:model="pesertaRutin" label="{{ __('Ditanggung oleh') }}" variant="pills">
                @foreach ($this->anggotaDenganSaldo as $baris)
                    <flux:checkbox value="{{ $baris['anggota']->id }}" label="{{ $baris['anggota']->nama }}" wire:key="ubah-peserta-{{ $baris['anggota']->id }}" />
                @endforeach
            </flux:checkbox.group>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Batal') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="primary" type="submit" data-test="simpan-rutin">{{ __('Simpan') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
