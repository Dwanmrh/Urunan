<?php

use App\Enums\StatusPembayaran;
use App\Models\AnggotaGrup;
use App\Models\BagianTagihan;
use App\Models\Pembayaran;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    /**
     * Keanggotaan user di semua grup beserta saldonya.
     *
     * @return Collection<int, array{anggota: AnggotaGrup, saldo: int}>
     */
    #[Computed]
    public function keanggotaan(): Collection
    {
        return Auth::user()->anggotaGrup()
            ->with('grup')
            ->latest('id')
            ->get()
            ->map(fn (AnggotaGrup $anggota): array => [
                'anggota' => $anggota,
                'saldo' => $anggota->grup->saldo()[$anggota->id] ?? 0,
            ]);
    }

    /**
     * Total yang harus dibayar dan yang akan diterima dari semua grup.
     *
     * @return array{harusBayar: int, akanTerima: int}
     */
    #[Computed]
    public function ringkasan(): array
    {
        $saldo = $this->keanggotaan->pluck('saldo');

        return [
            'harusBayar' => (int) abs($saldo->filter(fn (int $jumlah): bool => $jumlah < 0)->sum()),
            'akanTerima' => (int) $saldo->filter(fn (int $jumlah): bool => $jumlah > 0)->sum(),
        ];
    }

    /**
     * Tagihan yang bagian user masih belum lunas.
     *
     * @return Collection<int, array{tagihan: \App\Models\Tagihan, sisa: int, menunggu: bool}>
     */
    #[Computed]
    public function perluDibayar(): Collection
    {
        $idAnggota = $this->keanggotaan->pluck('anggota.id')->all();

        return BagianTagihan::query()
            ->whereIn('anggota_grup_id', $idAnggota)
            ->with(['tagihan.grup', 'tagihan.pembayar'])
            ->get()
            ->map(function (BagianTagihan $bagian): array {
                $status = $bagian->tagihan->statusPelunasan()
                    ->first(fn (array $baris): bool => $baris['anggota']->id === $bagian->anggota_grup_id);

                return [
                    'tagihan' => $bagian->tagihan,
                    'sisa' => $status['sisa'] ?? 0,
                    'pembayar' => $status['pembayar'] ?? false,
                    'menunggu' => ($status['menunggu'] ?? null) !== null,
                ];
            })
            ->filter(fn (array $baris): bool => ! $baris['pembayar'] && $baris['sisa'] > 0)
            ->sortByDesc(fn (array $baris): string => $baris['tagihan']->tanggal->toDateString())
            ->values();
    }

    /**
     * Bukti bayar yang menunggu konfirmasi user.
     *
     * @return Collection<int, Pembayaran>
     */
    #[Computed]
    public function menungguKonfirmasi(): Collection
    {
        return Pembayaran::query()
            ->where('status', StatusPembayaran::Menunggu->value)
            ->whereNotNull('tagihan_id')
            ->whereIn('grup_id', $this->keanggotaan->pluck('anggota.grup_id')->all())
            ->with(['dari', 'tagihan.pembayar', 'tagihan.grup'])
            ->latest()
            ->get()
            ->filter(fn (Pembayaran $pembayaran): bool => $pembayaran->tagihan->bisaDikelolaOleh(Auth::user()))
            ->values();
    }
}; ?>

<div class="w-full space-y-8">
    <div>
        <flux:heading size="xl" level="1">{{ __('Halo, :nama', ['nama' => auth()->user()->name]) }}</flux:heading>
        <flux:subheading size="lg">{{ __('Ini ringkasan patunganmu hari ini.') }}</flux:subheading>
    </div>

    @if ($this->keanggotaan->isEmpty())
        <flux:card class="space-y-4">
            <flux:heading size="lg" level="2">{{ __('Mulai patungan pertamamu') }}</flux:heading>
            <flux:text>
                {{ __('Buat grup untuk acara atau lingkaran pertemananmu, misalnya "Liburan Pangandaran" atau "Kos bulan ini", lalu catat tagihan dan bagikan link undangannya ke grup WhatsApp.') }}
            </flux:text>
            <flux:text>{{ __('Kalau temanmu sudah mengirim link undangan, cukup buka link itu untuk bergabung.') }}</flux:text>
            <flux:button :href="route('grup.index')" variant="primary" icon="plus" wire:navigate>{{ __('Buat grup pertama') }}</flux:button>
        </flux:card>
    @else
        <div class="grid gap-4 sm:grid-cols-2">
            <flux:card>
                <flux:text>{{ __('Harus kamu bayar') }}</flux:text>
                <x-rupiah :jumlah="$this->ringkasan['harusBayar']" class="text-2xl font-bold text-red-600 dark:text-red-400" />
            </flux:card>

            <flux:card>
                <flux:text>{{ __('Akan kamu terima') }}</flux:text>
                <x-rupiah :jumlah="$this->ringkasan['akanTerima']" class="text-2xl font-bold text-green-700 dark:text-green-400" />
            </flux:card>
        </div>

        @if ($this->menungguKonfirmasi->isNotEmpty())
            <section class="space-y-3">
                <flux:heading size="lg" level="2">{{ __('Bukti bayar menunggu konfirmasimu') }}</flux:heading>

                <flux:card class="p-0">
                    <ul class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @foreach ($this->menungguKonfirmasi as $pembayaran)
                            <li wire:key="menunggu-{{ $pembayaran->id }}">
                                <a href="{{ route('tagihan.show', [$pembayaran->tagihan->grup_id, $pembayaran->tagihan_id]) }}" wire:navigate class="flex items-center justify-between gap-3 px-4 py-3 [&>*:first-child]:min-w-0 [&>*:last-child]:shrink-0 hover:bg-zinc-50 dark:hover:bg-zinc-700">
                                    <div>
                                        <flux:heading>{{ __(':nama mengirim bukti', ['nama' => $pembayaran->dari->nama]) }}</flux:heading>
                                        <flux:text>{{ $pembayaran->tagihan->judul }}, {{ $pembayaran->tagihan->grup->nama }}</flux:text>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <x-rupiah :jumlah="$pembayaran->jumlah" class="font-semibold" />
                                        <flux:badge color="sky">{{ __('Cek') }}</flux:badge>
                                    </div>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </flux:card>
            </section>
        @endif

        <section class="space-y-3">
            <flux:heading size="lg" level="2">{{ __('Yang perlu kamu bayar') }}</flux:heading>

            @if ($this->perluDibayar->isEmpty())
                <flux:card>
                    <flux:text>{{ __('Semua bagianmu sudah lunas.') }}</flux:text>
                </flux:card>
            @else
                <flux:card class="p-0">
                    <ul class="divide-y divide-zinc-200 dark:divide-zinc-700">
                        @foreach ($this->perluDibayar as $baris)
                            <li wire:key="bayar-{{ $baris['tagihan']->id }}">
                                <a href="{{ route('tagihan.show', [$baris['tagihan']->grup_id, $baris['tagihan']->id]) }}" wire:navigate class="flex items-center justify-between gap-3 px-4 py-3 [&>*:first-child]:min-w-0 [&>*:last-child]:shrink-0 hover:bg-zinc-50 dark:hover:bg-zinc-700">
                                    <div>
                                        <flux:heading>{{ $baris['tagihan']->judul }}</flux:heading>
                                        <flux:text>{{ __('Ke :nama, :grup', ['nama' => $baris['tagihan']->pembayar->nama, 'grup' => $baris['tagihan']->grup->nama]) }}</flux:text>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <x-rupiah :jumlah="$baris['sisa']" class="font-semibold" />
                                        @if ($baris['menunggu'])
                                            <flux:badge color="sky">{{ __('Menunggu') }}</flux:badge>
                                        @else
                                            <flux:badge color="amber">{{ __('Belum') }}</flux:badge>
                                        @endif
                                    </div>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </flux:card>
            @endif
        </section>

        <section class="space-y-3">
            <div class="flex items-center justify-between gap-3">
                <flux:heading size="lg" level="2">{{ __('Grup kamu') }}</flux:heading>
                <flux:button :href="route('grup.index')" size="sm" variant="subtle" wire:navigate>{{ __('Lihat semua') }}</flux:button>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                @foreach ($this->keanggotaan->take(4) as $baris)
                    <a href="{{ route('grup.show', $baris['anggota']->grup) }}" wire:navigate wire:key="grup-{{ $baris['anggota']->grup_id }}" class="block">
                        <flux:card class="h-full hover:bg-zinc-50 dark:hover:bg-zinc-700">
                            <flux:heading level="3">{{ $baris['anggota']->grup->nama }}</flux:heading>

                            @if ($baris['saldo'] < 0)
                                <flux:text class="mt-1">{{ __('Kamu utang :jumlah', ['jumlah' => \App\Actions\Urunan\FormatRupiah::rp($baris['saldo'])]) }}</flux:text>
                            @elseif ($baris['saldo'] > 0)
                                <flux:text class="mt-1">{{ __('Ditunggu :jumlah', ['jumlah' => \App\Actions\Urunan\FormatRupiah::rp($baris['saldo'])]) }}</flux:text>
                            @else
                                <flux:text class="mt-1">{{ __('Semua lunas') }}</flux:text>
                            @endif
                        </flux:card>
                    </a>
                @endforeach
            </div>
        </section>
    @endif
</div>
