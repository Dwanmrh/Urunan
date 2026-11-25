<?php

use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Info pembayaran')] class extends Component {
    /**
     * Batas rekening yang bisa disimpan supaya pesan tagih tetap ringkas.
     */
    public const MAKSIMAL = 3;

    /**
     * Pilihan cepat bank dan e-wallet yang umum dipakai.
     */
    public const BANK = ['BCA', 'BRI', 'BNI', 'Mandiri', 'BSI', 'CIMB Niaga', 'Permata', 'SeaBank', 'Jago', 'blu', 'Jenius'];

    public const EWALLET = ['GoPay', 'OVO', 'DANA', 'ShopeePay', 'LinkAja'];

    public bool $lainnya = false;

    public ?int $urutanDiubah = null;

    public string $jenis = '';

    public string $nomor = '';

    public string $atasNama = '';

    /**
     * Rekening dan e-wallet yang tersimpan.
     *
     * @return list<array{jenis: string, nomor: string, atas_nama: string}>
     */
    #[Computed]
    public function daftar(): array
    {
        return Auth::user()->infoBayar();
    }

    /**
     * Buka form tambah rekening.
     */
    public function tambah(): void
    {
        if (count($this->daftar) >= self::MAKSIMAL) {
            Flux::toast(variant: 'warning', text: __('Maksimal :jumlah rekening. Hapus salah satu dulu.', ['jumlah' => self::MAKSIMAL]));

            return;
        }

        $this->reset('urutanDiubah', 'jenis', 'nomor', 'lainnya');
        $this->atasNama = Auth::user()->name;
        $this->resetValidation();

        Flux::modal('rekening')->show();
    }

    /**
     * Buka form ubah rekening.
     */
    public function ubah(int $urutan): void
    {
        $rekening = $this->daftar[$urutan] ?? null;

        abort_if($rekening === null, 404);

        $this->urutanDiubah = $urutan;
        $this->jenis = $rekening['jenis'];
        $this->nomor = $rekening['nomor'];
        $this->atasNama = $rekening['atas_nama'] ?? '';
        $this->lainnya = ! in_array($rekening['jenis'], [...self::BANK, ...self::EWALLET], true);
        $this->resetValidation();

        Flux::modal('rekening')->show();
    }

    /**
     * Pilih bank atau e-wallet dari pilihan cepat.
     */
    public function pilihJenis(string $jenis): void
    {
        abort_unless(in_array($jenis, [...self::BANK, ...self::EWALLET], true), 422);

        $this->jenis = $jenis;
        $this->lainnya = false;
        $this->resetValidation('jenis');
    }

    /**
     * Ketik sendiri nama bank atau e-wallet yang tidak ada di pilihan.
     */
    public function pilihLainnya(): void
    {
        $this->lainnya = true;
        $this->jenis = '';
    }

    /**
     * Apakah pilihan saat ini e-wallet, yang nomornya berupa nomor HP.
     */
    #[Computed]
    public function ewallet(): bool
    {
        return in_array($this->jenis, self::EWALLET, true);
    }

    /**
     * Simpan rekening baru atau perubahan.
     */
    public function simpan(): void
    {
        $this->nomor = preg_replace('/[\s.\-]/', '', $this->nomor) ?? '';
        $this->atasNama = trim($this->atasNama);

        $this->validate(
            [
                'jenis' => ['required', 'string', 'max:30'],
                'nomor' => $this->ewallet
                    ? ['required', 'regex:/^(08|\+628|628)[0-9]{7,12}$/']
                    : ['required', 'regex:/^[0-9]{6,20}$/'],
                'atasNama' => ['required', 'string', 'max:60'],
            ],
            [
                'jenis.required' => __('Pilih bank atau e-wallet.'),
                'jenis.max' => __('Nama bank atau e-wallet maksimal 30 karakter.'),
                'nomor.required' => __('Nomor rekening atau HP wajib diisi.'),
                'nomor.regex' => $this->ewallet
                    ? __('Nomor HP tidak valid. Contoh: 081234567890.')
                    : __('Nomor rekening berisi 6 sampai 20 angka.'),
                'atasNama.required' => __('Isi nama pemilik sesuai rekening.'),
                'atasNama.max' => __('Nama pemilik maksimal 60 karakter.'),
            ],
        );

        $daftar = $this->daftar;
        $baris = ['jenis' => trim($this->jenis), 'nomor' => $this->nomor, 'atas_nama' => trim($this->atasNama)];

        if ($this->urutanDiubah !== null && isset($daftar[$this->urutanDiubah])) {
            $daftar[$this->urutanDiubah] = $baris;
        } elseif (count($daftar) < self::MAKSIMAL) {
            $daftar[] = $baris;
        }

        Auth::user()->forceFill(['info_bayar' => array_values($daftar)])->save();

        unset($this->daftar);
        $this->reset('urutanDiubah', 'jenis', 'nomor', 'atasNama', 'lainnya');

        Flux::modal('rekening')->close();
        Flux::toast(variant: 'success', text: __('Info pembayaran disimpan.'));
    }

    /**
     * Hapus satu rekening.
     */
    public function hapus(int $urutan): void
    {
        $daftar = $this->daftar;
        unset($daftar[$urutan]);

        Auth::user()->forceFill(['info_bayar' => array_values($daftar)])->save();

        unset($this->daftar);

        Flux::toast(text: __('Rekening dihapus.'));
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Info pembayaran') }}</flux:heading>

    <x-pages::settings.layout heading="{{ __('Info pembayaran') }}" subheading="{{ __('Rekening atau e-wallet tujuan transfer. Tampil ke teman yang harus bayar ke kamu, dan ikut di pesan tagih WhatsApp.') }}">
        <div class="space-y-3">
            @forelse ($this->daftar as $urutan => $rekening)
                <div wire:key="rekening-{{ $urutan }}" class="flex items-center gap-3 rounded-2xl border border-zinc-200 bg-white p-4 dark:border-zinc-700/70 dark:bg-zinc-900" data-test="rekening">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-300">
                        <flux:icon.credit-card variant="mini" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-semibold text-zinc-600 dark:text-zinc-400 uppercase">{{ $rekening['jenis'] }}</p>
                        <p class="truncate font-mono font-semibold text-zinc-900 dark:text-white">{{ $rekening['nomor'] }}</p>
                        @if (filled($rekening['atas_nama'] ?? null))
                            <p class="truncate text-xs text-zinc-600 dark:text-zinc-400">{{ __('a.n.') }} {{ $rekening['atas_nama'] }}</p>
                        @endif
                    </div>
                    <flux:button wire:click="ubah({{ $urutan }})" size="sm" variant="subtle" icon="pencil-square" aria-label="{{ __('Edit') }}" />
                    <flux:button
                        size="sm"
                        variant="subtle"
                        icon="trash"
                        aria-label="{{ __('Hapus') }}"
                        x-on:click="$dispatch('konfirmasi', {
                            judul: {{ \Illuminate\Support\Js::from(__('Hapus :jenis?', ['jenis' => $rekening['jenis']])) }},
                            pesan: {{ \Illuminate\Support\Js::from(__('Rekening ini tidak akan tampil lagi di tagihanmu.')) }},
                            tombol: {{ \Illuminate\Support\Js::from(__('Hapus')) }},
                            bahaya: true,
                            aksi: () => $wire.hapus({{ $urutan }}),
                        })"
                    />
                </div>
            @empty
                <div class="rounded-2xl border border-dashed border-zinc-300 px-6 py-10 text-center dark:border-zinc-700">
                    <span class="text-4xl" aria-hidden="true">💳</span>
                    <p class="mt-3 font-medium text-zinc-900 dark:text-white">{{ __('Belum ada rekening') }}</p>
                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">{{ __('Tambahkan supaya teman nggak perlu nanya "transfer ke mana?" lagi.') }}</p>
                </div>
            @endforelse

            @if (count($this->daftar) < 3)
                <flux:button wire:click="tambah" icon="plus" class="w-full" data-test="tambah-rekening">{{ __('Tambah rekening atau e-wallet') }}</flux:button>
            @endif
        </div>
    </x-pages::settings.layout>

    <flux:modal name="rekening" focusable class="w-full max-w-lg">
        <form wire:submit="simpan" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $urutanDiubah === null ? __('Tambah rekening') : __('Edit rekening') }}</flux:heading>
                <flux:subheading>{{ __('Pilih tujuan transfer, lalu isi nomornya.') }}</flux:subheading>
            </div>

            @php
                $warnaJenis = ['BCA' => 'bg-blue-600', 'BRI' => 'bg-sky-700', 'BNI' => 'bg-orange-500', 'Mandiri' => 'bg-blue-900', 'BSI' => 'bg-teal-600', 'CIMB Niaga' => 'bg-red-700', 'Permata' => 'bg-emerald-700', 'SeaBank' => 'bg-orange-600', 'Jago' => 'bg-amber-500', 'blu' => 'bg-sky-500', 'Jenius' => 'bg-cyan-600', 'GoPay' => 'bg-sky-600', 'OVO' => 'bg-violet-700', 'DANA' => 'bg-blue-500', 'ShopeePay' => 'bg-orange-500', 'LinkAja' => 'bg-red-600'];
            @endphp

            @foreach ([__('Bank') => $this::BANK, __('E-wallet') => $this::EWALLET] as $kelompok => $daftar)
                <div>
                    <p class="mb-2 text-sm font-semibold text-zinc-800 dark:text-zinc-200">{{ $kelompok }}</p>
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3" role="radiogroup" aria-label="{{ $kelompok }}">
                        @foreach ($daftar as $pilihan)
                            @php($terpilih = $jenis === $pilihan && ! $lainnya)
                            <button
                                type="button"
                                wire:click="pilihJenis(@js($pilihan))"
                                role="radio"
                                aria-checked="{{ $terpilih ? 'true' : 'false' }}"
                                data-test="jenis-{{ \Illuminate\Support\Str::slug($pilihan) }}"
                                @class([
                                    'relative flex items-center gap-2 rounded-xl border px-2.5 py-2 text-start text-sm font-semibold transition hover:-translate-y-0.5 hover:shadow-sm',
                                    'border-indigo-500 bg-indigo-50 text-indigo-900 ring-2 ring-indigo-500/30 dark:border-indigo-400 dark:bg-indigo-500/15 dark:text-white' => $terpilih,
                                    'border-zinc-200 bg-white text-zinc-800 hover:border-zinc-300 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-100 dark:hover:border-zinc-600' => ! $terpilih,
                                ])
                            >
                                <span class="flex size-6 shrink-0 items-center justify-center rounded-md text-[0.6875rem] font-bold text-white {{ $warnaJenis[$pilihan] ?? 'bg-zinc-500' }}" aria-hidden="true">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($pilihan, 0, 1)) }}</span>
                                <span class="truncate">{{ $pilihan }}</span>
                                @if ($terpilih)
                                    <span class="absolute -top-1.5 -right-1.5 flex size-5 items-center justify-center rounded-full bg-indigo-600 text-white ring-2 ring-white dark:ring-zinc-800">
                                        <flux:icon.check variant="micro" class="size-3.5" />
                                    </span>
                                @endif
                            </button>
                        @endforeach
                    </div>
                </div>
            @endforeach

            <div>
                <button
                    type="button"
                    wire:click="pilihLainnya"
                    @class([
                        'flex w-full items-center gap-2 rounded-xl border border-dashed px-3 py-2 text-sm font-semibold transition',
                        'border-indigo-500 bg-indigo-50 text-indigo-900 dark:bg-indigo-500/15 dark:text-white' => $lainnya,
                        'border-zinc-300 text-zinc-700 hover:border-zinc-400 dark:border-zinc-600 dark:text-zinc-200' => ! $lainnya,
                    ])
                    data-test="jenis-lainnya"
                >
                    <flux:icon.plus variant="micro" />
                    {{ __('Bank atau e-wallet lain') }}
                </button>

                @if ($lainnya)
                    <div class="mt-3">
                        <flux:input wire:model="jenis" placeholder="{{ __('Contoh: Bank Jatim, Allo Bank') }}" aria-label="{{ __('Bank atau e-wallet') }}" autofocus />
                    </div>
                @endif
                <flux:error name="jenis" />
            </div>

            @if (filled($jenis) || $lainnya)
                <div class="space-y-4 rounded-2xl bg-zinc-50 p-4 motion-safe:animate-[muncul_0.25s_ease-out_both] dark:bg-white/5">
                    <flux:input
                        wire:model="nomor"
                        :label="$this->ewallet ? __('Nomor HP :jenis', ['jenis' => $jenis]) : __('Nomor rekening')"
                        :placeholder="$this->ewallet ? '081234567890' : '1234567890'"
                        inputmode="numeric"
                        autocomplete="off"
                        required
                    />

                    <flux:input
                        wire:model="atasNama"
                        :label="__('Atas nama')"
                        :description="__('Tulis persis seperti nama di rekening. Nama ini yang muncul saat temanmu transfer, jadi kalau beda mereka bisa ragu.')"
                        required
                    />
                </div>
            @endif

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Batal') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" type="submit" data-test="simpan-rekening">{{ __('Simpan') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
