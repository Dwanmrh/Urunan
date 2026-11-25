<?php

use App\Actions\Urunan\PesanTagih;
use App\Models\AnggotaGrup;
use App\Models\Grup;
use App\Models\ItemTagihan;
use App\Models\Tagihan;
use App\Support\BatasAksi;
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

    public string $gaya = 'santai';

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
     * Apakah user yang sedang login boleh mengedit atau menghapus tagihan ini.
     */
    #[Computed]
    public function bisaMengubah(): bool
    {
        return $this->tagihan->bisaDiubahOleh(Auth::user());
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
     * Jumlah anggota punya akun yang belum lunas dan belum mengirim bukti.
     */
    #[Computed]
    public function bisaDiingatkan(): int
    {
        return $this->statusPelunasan
            ->filter(fn (array $baris): bool => ! $baris['pembayar']
                && $baris['sisa'] > 0
                && $baris['menunggu'] === null
                && $baris['anggota']->user_id !== null
                && $baris['anggota']->user_id !== Auth::id())
            ->count();
    }

    /**
     * Kirim pengingat ke anggota yang belum bayar. Paling sering sekali sehari per tagihan.
     */
    public function ingatkan(): void
    {
        $this->pastikanPengelola();

        if ($this->tagihan->baruDiingatkan()) {
            Flux::toast(variant: 'warning', text: __('Tagihan ini sudah diingatkan dalam 24 jam terakhir. Coba lagi besok, ya.'));

            return;
        }

        $jumlah = $this->tagihan->ingatkanYangBelumBayar(Auth::user());

        Flux::toast(
            variant: $jumlah > 0 ? 'success' : 'warning',
            text: $jumlah > 0
                ? trans_choice('Pengingat dikirim ke :jumlah orang.', $jumlah, ['jumlah' => $jumlah])
                : __('Tidak ada yang perlu diingatkan.'),
        );
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

        $this->dispatch('konfeti');
        Flux::toast(variant: 'success', text: __(':nama ditandai lunas.', ['nama' => $anggota->nama]));
    }

    /**
     * Rekening atau e-wallet tujuan transfer milik yang menalangi.
     *
     * @return list<array{jenis: string, nomor: string, atas_nama: string}>
     */
    #[Computed]
    public function rekeningPembayar(): array
    {
        return $this->tagihan->pembayar->user?->infoBayar() ?? [];
    }

    /**
     * Pesan tagih siap kirim ke WhatsApp sesuai gaya bahasa yang dipilih.
     */
    #[Computed]
    public function pesanTagih(): string
    {
        return PesanTagih::buat($this->tagihan, $this->gaya);
    }

    /**
     * Persentase anggota yang sudah lunas, untuk bilah kemajuan.
     *
     * @return array{lunas: int, total: int, persen: int}
     */
    #[Computed]
    public function kemajuan(): array
    {
        $total = $this->statusPelunasan->count();
        $lunas = $this->statusPelunasan->filter(fn (array $baris): bool => $baris['sisa'] === 0)->count();

        return [
            'lunas' => $lunas,
            'total' => $total,
            'persen' => $total > 0 ? (int) round($lunas / $total * 100) : 0,
        ];
    }

    /**
     * Tarik kembali bukti bayar sendiri yang masih menunggu konfirmasi.
     */
    public function batalkanBukti(): void
    {
        $this->pastikanAnggota();

        abort_if($this->anggotaSaya === null, 403);

        if ($this->tagihan->batalkanBukti($this->anggotaSaya)) {
            Flux::toast(text: __('Bukti bayar ditarik. Kamu bisa kirim ulang kapan saja.'));
        }

        $this->segarkan();
    }

    /**
     * Batalkan pelunasan anggota yang salah ditandai lunas.
     */
    public function batalkanPelunasan(int $idAnggota): void
    {
        $this->pastikanPengelola();

        $anggota = $this->grup->anggota()->findOrFail($idAnggota);

        if ($this->tagihan->batalkanPelunasan($anggota) > 0) {
            Flux::toast(text: __('Pelunasan :nama dibatalkan.', ['nama' => $anggota->nama]));
        }

        $this->segarkan();
    }

    /**
     * Terima bukti bayar yang dikirim anggota.
     */
    public function konfirmasi(int $idPembayaran): void
    {
        $this->pastikanPengelola();

        $this->tagihan->konfirmasi($this->tagihan->pembayaran()->findOrFail($idPembayaran));

        $this->segarkan();

        $this->dispatch('konfeti');
        Flux::toast(variant: 'success', text: __('Pembayaran dikonfirmasi.'));
    }

    /**
     * Tolak bukti bayar yang dikirim anggota.
     */
    public function tolak(int $idPembayaran): void
    {
        $this->pastikanPengelola();

        $this->tagihan->tolak($this->tagihan->pembayaran()->findOrFail($idPembayaran));

        $this->segarkan();

        Flux::toast(text: __('Bukti bayar ditolak.'));
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
            ['bukti' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:4096']],
            [
                'bukti.required' => __('Pilih foto bukti transfer dulu.'),
                'bukti.file' => __('Bukti harus berupa gambar.'),
                'bukti.mimes' => __('Bukti harus berupa foto JPG, PNG, atau WEBP.'),
                'bukti.max' => __('Ukuran gambar maksimal 4 MB.'),
            ],
        );

        if (! BatasAksi::boleh('kirim-bukti', 10)) {
            return;
        }

        $path = $this->bukti->store('bukti-pembayaran', 'local');

        if ($this->tagihan->kirimBukti($anggota, $path, Auth::user()) === null) {
            \Illuminate\Support\Facades\Storage::disk('local')->delete($path);
            $this->reset('bukti');

            Flux::toast(variant: 'warning', text: __('Bagianmu sudah lunas atau buktinya masih menunggu konfirmasi.'));

            return;
        }

        $this->reset('bukti');
        $this->segarkan();

        Flux::modal('kirim-bukti')->close();
        Flux::toast(variant: 'success', text: __('Bukti terkirim. Menunggu konfirmasi :nama.', ['nama' => $this->tagihan->pembayar->nama]));
    }

    /**
     * Hapus tagihan ini lalu kembali ke halaman grup.
     */
    public function hapus(): void
    {
        $this->pastikanAnggota();

        abort_unless($this->tagihan->bisaDiubahOleh(Auth::user()), 403);

        $this->tagihan->hapus();

        $this->redirectRoute('grup.show', $this->grup, navigate: true);
    }

    /**
     * Muat ulang data yang tampil setelah ada perubahan.
     */
    private function segarkan(): void
    {
        unset($this->statusPelunasan, $this->sisaSaya, $this->kemajuan, $this->pesanTagih, $this->bisaDiingatkan);
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

<section class="w-full max-w-3xl space-y-6">
    @php($statusSaya = $this->statusPelunasan->first(fn ($baris) => $baris['anggota']->id === $this->anggotaSaya?->id))

    <x-tur nama="detail-tagihan" :langkah="[
        ['elemen' => '[data-tur=bagian-tiap-orang]', 'judul' => __('Status tiap orang'), 'isi' => __('Lihat bagian tiap anggota dan statusnya: belum bayar, menunggu konfirmasi, atau lunas.'), 'sisi' => 'top'],
        ['elemen' => '[data-tur=kirim-bukti]', 'judul' => __('Bayar bagianmu'), 'isi' => __('Transfer ke yang menalangi, lalu kirim foto bukti transfer di sini. Statusmu jadi lunas setelah dia mengonfirmasi.'), 'sisi' => 'top'],
        ['elemen' => '[data-tur=tagih]', 'judul' => __('Nagih tanpa canggung'), 'isi' => __('Pilih gaya bahasa, lalu kirim rekap tagihan ke grup WhatsApp dalam satu ketukan.'), 'sisi' => 'top'],
    ]" />

    <a href="{{ route('grup.show', $grup) }}" wire:navigate class="-ms-2 inline-flex max-w-full items-center gap-1.5 rounded-lg px-2 py-1 text-sm font-medium text-zinc-600 dark:text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-900 dark:hover:bg-white/5 dark:hover:text-white">
        <flux:icon.arrow-left variant="micro" class="shrink-0" />
        <span class="truncate">{{ $grup->emoji }} {{ $grup->nama }}</span>
    </a>

    {{-- Ringkasan tagihan --}}
    <section class="relative overflow-hidden rounded-3xl bg-linear-to-br {{ $grup->warna->gradasi() }} {{ $grup->warna->teks() }} p-6 shadow-lg motion-safe:animate-[muncul_0.5s_ease-out_both] sm:p-8">
        <div aria-hidden="true" class="absolute -top-20 -right-16 size-64 rounded-full bg-white/10 blur-2xl"></div>

        <div class="relative">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2 text-sm font-medium {{ $grup->warna->teksRedup() }}">
                        <span>{{ $tagihan->tanggal->locale(app()->getLocale())->translatedFormat('j F Y') }}</span>
                        <span aria-hidden="true">·</span>
                        <span>{{ $tagihan->cara_bagi->label() }}</span>
                        @if ($tagihan->tagihan_rutin_id)
                            <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold ring-1 {{ $grup->warna->kaca() }}">
                                <flux:icon.arrow-path variant="micro" class="size-3" />{{ __('Rutin') }}
                            </span>
                        @endif
                    </div>
                    <h1 class="mt-1 text-2xl font-bold tracking-tight break-words sm:text-3xl">{{ $tagihan->judul }}</h1>
                </div>

                @if ($this->bisaMengubah)
                    <flux:dropdown align="end">
                        <button type="button" class="flex size-10 shrink-0 items-center justify-center rounded-xl ring-1 backdrop-blur transition {{ $grup->warna->kaca() }}" aria-label="{{ __('Atur tagihan') }}" data-test="atur-tagihan">
                            <flux:icon.ellipsis-horizontal variant="mini" />
                        </button>
                        <flux:menu>
                            <flux:menu.item icon="pencil-square" :href="route('tagihan.edit', [$grup, $tagihan])" wire:navigate data-test="edit-tagihan">{{ __('Edit') }}</flux:menu.item>
                            <flux:menu.separator />
                            <flux:menu.item
                                icon="trash"
                                variant="danger"
                                data-test="hapus-tagihan"
                                x-on:click="$dispatch('konfirmasi', {
                                    judul: {{ \Illuminate\Support\Js::from(__('Hapus tagihan :tagihan?', ['tagihan' => $tagihan->judul])) }},
                                    pesan: {{ \Illuminate\Support\Js::from(__('Pembayaran yang sudah diterima tetap tercatat di saldo grup.')) }},
                                    tombol: {{ \Illuminate\Support\Js::from(__('Hapus')) }},
                                    bahaya: true,
                                    aksi: () => $wire.hapus(),
                                })"
                            >{{ __('Hapus') }}</flux:menu.item>
                        </flux:menu>
                    </flux:dropdown>
                @endif
            </div>

            <div class="mt-6 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="text-sm font-medium {{ $grup->warna->teksRedup() }}">{{ __('Total tagihan') }}</p>
                    <x-rupiah :jumlah="$tagihan->total" class="text-3xl font-bold tracking-tight tabular-nums sm:text-4xl" />
                </div>
                <div class="flex items-center gap-2 rounded-2xl px-3 py-2 ring-1 backdrop-blur {{ $grup->warna->kaca() }}">
                    <x-inisial :nama="$tagihan->pembayar->nama" class="size-8 ring-2 ring-white/40" />
                    <div class="text-sm leading-tight">
                        <p class="{{ $grup->warna->teksRedup() }}">{{ __('Ditalangi') }}</p>
                        <p class="font-semibold">{{ $tagihan->pembayar->nama }}</p>
                    </div>
                </div>
            </div>

            <div class="mt-6">
                <div class="flex items-center justify-between text-sm">
                    <span class="font-medium">{{ __(':lunas dari :total orang lunas', ['lunas' => $this->kemajuan['lunas'], 'total' => $this->kemajuan['total']]) }}</span>
                    <span class="font-semibold tabular-nums">{{ $this->kemajuan['persen'] }}%</span>
                </div>
                <div class="mt-2 h-2.5 overflow-hidden rounded-full {{ $grup->warna->bilah()['jalur'] }}">
                    <div class="h-full rounded-full transition-all duration-700 {{ $grup->warna->bilah()['isi'] }}" style="width: {{ $this->kemajuan['persen'] }}%"></div>
                </div>
            </div>
        </div>
    </section>

    {{-- Bagian saya --}}
    @if ($statusSaya && ! $statusSaya['pembayar'] && $statusSaya['sisa'] > 0)
        <x-kartu class="border-indigo-200 ring-4 ring-indigo-500/5 dark:border-indigo-500/30" data-tur="kirim-bukti">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('Bagianmu') }}</p>
                    <x-rupiah :jumlah="$statusSaya['sisa']" class="text-2xl font-bold text-zinc-900 tabular-nums dark:text-white" />
                </div>

                @if ($statusSaya['menunggu'])
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center gap-2 rounded-full bg-sky-50 px-3 py-1.5 text-sm font-medium text-sky-700 dark:bg-sky-500/10 dark:text-sky-300">
                            <span class="relative flex size-2"><span class="absolute inline-flex size-full rounded-full bg-sky-400 opacity-75 motion-safe:animate-ping"></span><span class="relative inline-flex size-2 rounded-full bg-sky-500"></span></span>
                            {{ __('Menunggu konfirmasi :nama', ['nama' => $tagihan->pembayar->nama]) }}
                        </span>
                        <flux:button
                            size="sm"
                            variant="ghost"
                            icon="arrow-uturn-left"
                            data-test="tarik-bukti"
                            x-on:click="$dispatch('konfirmasi', {
                                judul: {{ \Illuminate\Support\Js::from(__('Tarik bukti bayar?')) }},
                                pesan: {{ \Illuminate\Support\Js::from(__('Pakai ini kalau salah unggah. Kamu bisa kirim bukti baru setelahnya.')) }},
                                tombol: {{ \Illuminate\Support\Js::from(__('Tarik bukti')) }},
                                aksi: () => $wire.batalkanBukti(),
                            })"
                        >{{ __('Tarik bukti') }}</flux:button>
                    </div>
                @else
                    <flux:modal.trigger name="kirim-bukti">
                        <flux:button variant="primary" icon="arrow-up-tray" data-test="buka-kirim-bukti">{{ __('Kirim bukti bayar') }}</flux:button>
                    </flux:modal.trigger>
                @endif
            </div>

            <div class="mt-5 border-t border-zinc-100 pt-4 dark:border-zinc-800">
                <p class="text-sm font-medium text-zinc-900 dark:text-white">{{ __('Transfer ke :nama', ['nama' => $tagihan->pembayar->nama]) }}</p>
                @if ($this->rekeningPembayar === [])
                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">{{ __(':nama belum mengisi info rekening. Tanyakan langsung, ya.', ['nama' => $tagihan->pembayar->nama]) }}</p>
                @else
                    <ul class="mt-3 grid gap-2 sm:grid-cols-2">
                        @foreach ($this->rekeningPembayar as $urutan => $rekening)
                            <li wire:key="rekening-{{ $urutan }}" x-data="{ tersalin: false }" class="flex items-center gap-3 rounded-xl bg-zinc-50 px-3 py-2.5 dark:bg-white/5">
                                <div class="min-w-0 flex-1">
                                    <p class="text-xs font-semibold text-zinc-600 dark:text-zinc-400 uppercase">{{ $rekening['jenis'] }}</p>
                                    <p class="truncate font-mono font-semibold text-zinc-900 dark:text-white">{{ $rekening['nomor'] }}</p>
                                    @if (filled($rekening['atas_nama'] ?? null))
                                        <p class="truncate text-xs text-zinc-600 dark:text-zinc-400">{{ __('a.n.') }} {{ $rekening['atas_nama'] }}</p>
                                    @endif
                                </div>
                                <button type="button" x-on:click="navigator.clipboard?.writeText(@js($rekening['nomor'])); tersalin = true; setTimeout(() => tersalin = false, 1500)" class="shrink-0 rounded-lg bg-white px-2.5 py-1.5 text-xs font-semibold text-indigo-600 shadow-sm transition hover:bg-indigo-50 dark:bg-zinc-800 dark:text-indigo-300" x-text="tersalin ? @js(__('Tersalin ✓')) : @js(__('Salin'))">{{ __('Salin') }}</button>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </x-kartu>
    @elseif ($statusSaya && ! $statusSaya['pembayar'] && $statusSaya['sisa'] === 0)
        <div class="flex items-center gap-3 rounded-2xl border border-emerald-200 bg-emerald-50/70 px-5 py-4 dark:border-emerald-500/20 dark:bg-emerald-500/5">
            <span class="text-2xl" aria-hidden="true">🎉</span>
            <p class="font-medium text-emerald-800 dark:text-emerald-200">{{ __('Bagianmu sudah lunas. Kamu keren!') }}</p>
        </div>
    @endif

    {{-- Nagih via WhatsApp --}}
    @if ($this->bisaMengelola && $this->kemajuan['lunas'] < $this->kemajuan['total'])
        <x-kartu :judul="__('Nagih tanpa canggung')" ikon="megaphone" data-tur="tagih">
            <div x-data="{ tersalin: false }" class="space-y-4">
                <div class="flex flex-wrap gap-2" role="radiogroup" aria-label="{{ __('Gaya bahasa') }}">
                    @foreach (\App\Actions\Urunan\PesanTagih::pilihan() as $kunci => $label)
                        <button
                            type="button"
                            wire:click="$set('gaya', '{{ $kunci }}')"
                            role="radio"
                            aria-checked="{{ $gaya === $kunci ? 'true' : 'false' }}"
                            data-test="gaya-{{ $kunci }}"
                            @class([
                                'rounded-full px-3.5 py-1.5 text-sm font-semibold transition',
                                'bg-zinc-900 text-white shadow-sm dark:bg-white dark:text-zinc-900' => $gaya === $kunci,
                                'bg-zinc-100 text-zinc-700 hover:bg-zinc-200 dark:bg-white/5 dark:text-zinc-300 dark:hover:bg-white/10' => $gaya !== $kunci,
                            ])
                        >{{ $label }}</button>
                    @endforeach
                </div>

                <div class="relative rounded-2xl rounded-tl-sm bg-[#dcf8c6] p-4 text-sm whitespace-pre-line text-zinc-900 shadow-sm dark:bg-[#005c4b] dark:text-white" data-test="pratinjau-tagih" wire:loading.class="opacity-60" wire:target="gaya">{{ $this->pesanTagih }}</div>

                <div class="flex flex-wrap gap-2">
                    <a href="{{ \App\Actions\Urunan\PesanTagih::linkWhatsapp($this->pesanTagih) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 rounded-xl bg-[#25D366] px-4 py-2.5 text-sm font-semibold text-white transition hover:brightness-95" data-test="tagih-whatsapp">
                        <x-ikon-whatsapp class="size-4" />
                        {{ __('Kirim ke WhatsApp') }}
                    </a>
                    <flux:button icon="clipboard-document" x-on:click="navigator.clipboard?.writeText({{ \Illuminate\Support\Js::from($this->pesanTagih) }}); tersalin = true; setTimeout(() => tersalin = false, 1500)">
                        <span x-text="tersalin ? @js(__('Tersalin ✓')) : @js(__('Salin pesan'))">{{ __('Salin pesan') }}</span>
                    </flux:button>
                    @if ($this->bisaDiingatkan > 0)
                        <flux:button wire:click="ingatkan" icon="bell-alert" :disabled="$tagihan->baruDiingatkan()" data-test="ingatkan">
                            {{ $tagihan->baruDiingatkan() ? __('Sudah diingatkan') : __('Ingatkan yang belum bayar') }}
                        </flux:button>
                    @endif
                </div>

                @if ($this->bisaDiingatkan > 0)
                    <p class="text-xs text-zinc-600 dark:text-zinc-400">{{ __('Yang belum bayar juga diingatkan otomatis :hari hari setelah tanggal tagihan, lalu seminggu sekali.', ['hari' => \App\Models\Tagihan::HARI_SEBELUM_DIINGATKAN]) }}</p>
                @endif
            </div>
        </x-kartu>
    @endif

    @if ($tagihan->cara_bagi === \App\Enums\CaraBagi::PerItem)
        <x-kartu :judul="__('Rincian item')" ikon="list-bullet" :jumlah="$this->daftarItem->count()" padat>
            <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @foreach ($this->daftarItem as $item)
                    <li wire:key="item-{{ $item->id }}" class="flex items-center justify-between gap-3 px-5 py-3">
                        <div class="min-w-0">
                            <p class="truncate font-medium text-zinc-900 dark:text-white">{{ $item->nama }}</p>
                            <p class="truncate text-sm text-zinc-600 dark:text-zinc-400">{{ $item->anggota->isEmpty() ? __('Semua anggota') : $item->anggota->pluck('nama')->join(', ') }}</p>
                        </div>
                        <x-rupiah :jumlah="$item->harga" class="shrink-0 font-semibold text-zinc-900 tabular-nums dark:text-white" />
                    </li>
                @endforeach
            </ul>
        </x-kartu>
    @endif

    <x-kartu :judul="__('Bagian tiap orang')" ikon="users" padat data-tur="bagian-tiap-orang">
        @unless ($this->bisaMengelola)
            <p class="border-b border-zinc-100 px-5 py-3 text-sm text-zinc-600 dark:text-zinc-400 dark:border-zinc-800">{{ __('Hanya :nama yang bisa menandai lunas dan mengonfirmasi bukti bayar.', ['nama' => $tagihan->pembayar->nama]) }}</p>
        @endunless

        <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
            @foreach ($this->statusPelunasan as $baris)
                @php($menunggu = $baris['menunggu'])

                <li wire:key="bagian-{{ $baris['anggota']->id }}" class="flex flex-wrap items-center gap-3 px-5 py-3.5">
                    <div class="relative">
                        <x-inisial :nama="$baris['anggota']->nama" />
                        @if ($baris['sisa'] === 0)
                            <span class="absolute -right-1 -bottom-1 flex size-4 items-center justify-center rounded-full bg-emerald-500 text-white ring-2 ring-white dark:ring-zinc-900">
                                <flux:icon.check variant="micro" class="size-3" />
                            </span>
                        @endif
                    </div>

                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium text-zinc-900 dark:text-white">
                            {{ $baris['anggota']->nama }}
                            @if ($baris['anggota']->id === $this->anggotaSaya?->id)
                                <span class="text-xs font-normal text-zinc-600 dark:text-zinc-400">({{ __('kamu') }})</span>
                            @endif
                        </p>
                        <p class="text-xs text-zinc-600 sm:text-sm dark:text-zinc-400">
                            @if ($baris['pembayar'])
                                {{ __('Yang membayar dulu') }}
                            @elseif ($menunggu)
                                {{ __('Bukti terkirim, menunggu konfirmasi') }}
                            @elseif ($baris['sisa'] > 0 && $baris['dibayar'] > 0)
                                {{ __('Sisa :jumlah', ['jumlah' => \App\Actions\Urunan\FormatRupiah::rp($baris['sisa'])]) }}
                            @elseif ($baris['sisa'] > 0)
                                {{ __('Belum membayar') }}
                            @else
                                {{ __('Sudah membayar') }}
                            @endif
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <x-rupiah :jumlah="$baris['bagian']" class="font-semibold text-zinc-900 tabular-nums dark:text-white" />

                        @if ($baris['sisa'] === 0)
                            <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">{{ __('Lunas') }}</span>
                        @elseif ($menunggu)
                            <span class="rounded-full bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-700 dark:bg-sky-500/10 dark:text-sky-300">{{ __('Menunggu') }}</span>
                        @else
                            <span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700 dark:bg-amber-500/10 dark:text-amber-300">{{ __('Belum') }}</span>
                        @endif

                        @if ($this->bisaMengelola && ! $baris['pembayar'] && $baris['sisa'] > 0)
                            @if ($menunggu)
                                <flux:button :href="route('pembayaran.bukti', [$grup, $menunggu])" target="_blank" size="sm" variant="ghost" icon="photo">{{ __('Lihat bukti') }}</flux:button>
                                <flux:button wire:click="konfirmasi({{ $menunggu->id }})" size="sm" variant="primary" icon="check" data-test="konfirmasi-{{ $menunggu->id }}">{{ __('Konfirmasi') }}</flux:button>
                                <flux:button
                                    x-on:click="$dispatch('konfirmasi', {
                                        judul: {{ \Illuminate\Support\Js::from(__('Tolak bukti dari :nama?', ['nama' => $baris['anggota']->nama])) }},
                                        pesan: {{ \Illuminate\Support\Js::from(__('Buktinya akan dihapus dan :nama perlu mengirim ulang.', ['nama' => $baris['anggota']->nama])) }},
                                        tombol: {{ \Illuminate\Support\Js::from(__('Tolak')) }},
                                        bahaya: true,
                                        aksi: () => $wire.tolak({{ $menunggu->id }}),
                                    })"
                                    size="sm"
                                    variant="danger"
                                >{{ __('Tolak') }}</flux:button>
                            @else
                                <flux:button
                                    x-on:click="$dispatch('konfirmasi', {
                                        judul: {{ \Illuminate\Support\Js::from(__('Tandai :nama lunas?', ['nama' => $baris['anggota']->nama])) }},
                                        pesan: {{ \Illuminate\Support\Js::from(__('Pakai ini kalau :nama sudah membayar langsung, misalnya tunai.', ['nama' => $baris['anggota']->nama])) }},
                                        tombol: {{ \Illuminate\Support\Js::from(__('Tandai lunas')) }},
                                        aksi: () => $wire.tandaiLunas({{ $baris['anggota']->id }}),
                                    })"
                                    size="sm"
                                    variant="primary"
                                    data-test="tandai-lunas-{{ $baris['anggota']->id }}"
                                >{{ __('Tandai lunas') }}</flux:button>
                            @endif
                        @elseif ($this->bisaMengelola && ! $baris['pembayar'] && $baris['dibayar'] > 0)
                            <flux:dropdown align="end">
                                <flux:button icon="ellipsis-vertical" size="sm" variant="subtle" aria-label="{{ __('Atur pelunasan :nama', ['nama' => $baris['anggota']->nama]) }}" data-test="atur-pelunasan-{{ $baris['anggota']->id }}" />
                                <flux:menu>
                                    <flux:menu.item
                                        icon="arrow-uturn-left"
                                        variant="danger"
                                        x-on:click="$dispatch('konfirmasi', {
                                            judul: {{ \Illuminate\Support\Js::from(__('Batalkan pelunasan :nama?', ['nama' => $baris['anggota']->nama])) }},
                                            pesan: {{ \Illuminate\Support\Js::from(__('Pakai ini kalau salah tandai lunas. Statusnya kembali jadi belum bayar.')) }},
                                            tombol: {{ \Illuminate\Support\Js::from(__('Batalkan pelunasan')) }},
                                            bahaya: true,
                                            aksi: () => $wire.batalkanPelunasan({{ $baris['anggota']->id }}),
                                        })"
                                    >{{ __('Batalkan pelunasan') }}</flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    </x-kartu>

    <flux:modal name="kirim-bukti" focusable class="w-full max-w-lg">
        <form wire:submit="kirimBukti" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Kirim bukti bayar') }}</flux:heading>
                <flux:subheading>
                    {{ __('Transfer :jumlah ke :nama, lalu unggah foto atau screenshot buktinya. Statusmu jadi lunas setelah :nama mengonfirmasi.', ['jumlah' => \App\Actions\Urunan\FormatRupiah::rp($this->sisaSaya), 'nama' => $tagihan->pembayar->nama]) }}
                </flux:subheading>
            </div>

            <label class="flex cursor-pointer flex-col items-center gap-2 rounded-2xl border-2 border-dashed border-zinc-300 px-4 py-8 text-center transition hover:border-indigo-400 hover:bg-indigo-50/40 dark:border-zinc-700 dark:hover:bg-indigo-500/5">
                @if ($bukti && str_starts_with((string) $bukti->getMimeType(), 'image/'))
                    <img src="{{ $bukti->temporaryUrl() }}" alt="{{ __('Pratinjau bukti transfer') }}" class="max-h-56 rounded-xl">
                    <span class="text-sm font-medium text-indigo-600 dark:text-indigo-300">{{ __('Ganti foto') }}</span>
                @else
                    <span class="flex size-12 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-300">
                        <flux:icon.photo class="size-6" />
                    </span>
                    <span class="font-medium text-zinc-900 dark:text-white">{{ __('Pilih foto bukti transfer') }}</span>
                    <span class="text-xs text-zinc-600 dark:text-zinc-400">{{ __('JPG, PNG, atau WEBP, maksimal 4 MB') }}</span>
                @endif
                <input type="file" wire:model="bukti" accept="image/jpeg,image/png,image/webp" class="sr-only" aria-label="{{ __('Foto bukti transfer') }}">
            </label>

            <div wire:loading wire:target="bukti" class="flex items-center gap-2 text-sm text-zinc-600 dark:text-zinc-400">
                <x-spinner class="size-4" />
                {{ __('Mengunggah...') }}
            </div>

            <flux:error name="bukti" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Batal') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="primary" type="submit" data-test="kirim-bukti">{{ __('Kirim') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
