<?php

use App\Enums\StatusPembayaran;
use App\Models\Grup;
use App\Models\Pembayaran;
use App\Models\Tagihan;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Statistik')] class extends Component {
    /**
     * Pengunjung dan pengguna yang aktif dalam 5 menit terakhir, dihitung dari tabel sessions.
     *
     * @return array{pengunjung: int, pengguna: int}
     */
    #[Computed]
    public function online(): array
    {
        $batas = now()->subMinutes(5)->getTimestamp();

        return [
            'pengunjung' => DB::table('sessions')->where('last_activity', '>=', $batas)->count(),
            'pengguna' => DB::table('sessions')->where('last_activity', '>=', $batas)->whereNotNull('user_id')->distinct()->count('user_id'),
        ];
    }

    /**
     * @return array{pengunjung: int, penggunaAktif: int, pendaftarBaru: int}
     */
    #[Computed]
    public function hariIni(): array
    {
        $hariIni = now()->toDateString();

        return [
            'pengunjung' => DB::table('kunjungan')->where('tanggal', $hariIni)->count(),
            'penggunaAktif' => DB::table('kunjungan')->where('tanggal', $hariIni)->whereNotNull('user_id')->distinct()->count('user_id'),
            'pendaftarBaru' => User::whereDate('created_at', $hariIni)->count(),
        ];
    }

    /**
     * @return array{pengguna: int, grup: int, tagihan: int, nilaiTagihan: int, sudahDibayar: int}
     */
    #[Computed]
    public function total(): array
    {
        return [
            'pengguna' => User::count(),
            'grup' => Grup::count(),
            'tagihan' => Tagihan::count(),
            'nilaiTagihan' => (int) Tagihan::sum('total'),
            'sudahDibayar' => (int) Pembayaran::where('status', StatusPembayaran::Diterima->value)->sum('jumlah'),
        ];
    }

    /**
     * Pengunjung unik per hari selama 14 hari terakhir.
     *
     * @return list<array{label: string, jumlah: int}>
     */
    #[Computed]
    public function grafik(): array
    {
        $mulai = now()->subDays(13)->startOfDay();

        $jumlah = DB::table('kunjungan')
            ->where('tanggal', '>=', $mulai->toDateString())
            ->selectRaw('tanggal, COUNT(*) as jumlah')
            ->groupBy('tanggal')
            ->pluck('jumlah', 'tanggal');

        return collect(range(0, 13))
            ->map(function (int $hari) use ($mulai, $jumlah): array {
                $tanggal = Carbon::parse($mulai)->addDays($hari);

                return [
                    'label' => $tanggal->format('d/m'),
                    'jumlah' => (int) ($jumlah[$tanggal->toDateString()] ?? 0),
                ];
            })
            ->all();
    }
}; ?>

<section class="w-full space-y-8" wire:poll.10s>
    <div>
        <flux:heading size="xl" level="1">{{ __('Statistik Urunan') }}</flux:heading>
        <flux:subheading size="lg">{{ __('Diperbarui otomatis tiap 10 detik. Hanya admin yang bisa melihat halaman ini.') }}</flux:subheading>
    </div>

    <section class="space-y-3">
        <flux:heading size="lg" level="2">{{ __('Sedang online') }}</flux:heading>
        <div class="grid gap-4 sm:grid-cols-2">
            <flux:card>
                <flux:text>{{ __('Pengunjung (5 menit terakhir)') }}</flux:text>
                <div class="text-3xl font-bold">{{ $this->online['pengunjung'] }}</div>
            </flux:card>
            <flux:card>
                <flux:text>{{ __('Pengguna yang login') }}</flux:text>
                <div class="text-3xl font-bold">{{ $this->online['pengguna'] }}</div>
            </flux:card>
        </div>
    </section>

    <section class="space-y-3">
        <flux:heading size="lg" level="2">{{ __('Hari ini') }}</flux:heading>
        <div class="grid gap-4 sm:grid-cols-3">
            <flux:card>
                <flux:text>{{ __('Pengunjung unik') }}</flux:text>
                <div class="text-3xl font-bold">{{ $this->hariIni['pengunjung'] }}</div>
            </flux:card>
            <flux:card>
                <flux:text>{{ __('Pengguna aktif') }}</flux:text>
                <div class="text-3xl font-bold">{{ $this->hariIni['penggunaAktif'] }}</div>
            </flux:card>
            <flux:card>
                <flux:text>{{ __('Pendaftar baru') }}</flux:text>
                <div class="text-3xl font-bold">{{ $this->hariIni['pendaftarBaru'] }}</div>
            </flux:card>
        </div>
    </section>

    <section class="space-y-3">
        <flux:heading size="lg" level="2">{{ __('Pengunjung unik 14 hari terakhir') }}</flux:heading>
        @php($tertinggi = max(1, max(array_column($this->grafik, 'jumlah'))))
        <flux:card>
            <div role="img" aria-label="{{ __('Grafik pengunjung unik per hari selama 14 hari terakhir') }}" class="flex h-48 items-end gap-1 sm:gap-2">
                @foreach ($this->grafik as $hari)
                    <div class="flex h-full flex-1 flex-col items-center justify-end gap-1" wire:key="grafik-{{ $hari['label'] }}">
                        <span class="text-[11px] font-semibold text-zinc-600 dark:text-zinc-300">{{ $hari['jumlah'] ?: '' }}</span>
                        <div class="w-full rounded-t bg-accent" style="height: {{ round($hari['jumlah'] / $tertinggi * 100) }}%; min-height: {{ $hari['jumlah'] ? '4px' : '0' }}"></div>
                        <span class="hidden text-[11px] text-zinc-500 sm:block">{{ $hari['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </flux:card>
    </section>

    <section class="space-y-3">
        <flux:heading size="lg" level="2">{{ __('Sejak awal') }}</flux:heading>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <flux:card>
                <flux:text>{{ __('Pengguna terdaftar') }}</flux:text>
                <div class="text-2xl font-bold">{{ number_format($this->total['pengguna'], 0, ',', '.') }}</div>
            </flux:card>
            <flux:card>
                <flux:text>{{ __('Grup patungan') }}</flux:text>
                <div class="text-2xl font-bold">{{ number_format($this->total['grup'], 0, ',', '.') }}</div>
            </flux:card>
            <flux:card>
                <flux:text>{{ __('Tagihan dicatat') }}</flux:text>
                <div class="text-2xl font-bold">{{ number_format($this->total['tagihan'], 0, ',', '.') }}</div>
            </flux:card>
            <flux:card>
                <flux:text>{{ __('Nilai tagihan dicatat') }}</flux:text>
                <x-rupiah :jumlah="$this->total['nilaiTagihan']" class="text-2xl font-bold" />
            </flux:card>
            <flux:card>
                <flux:text>{{ __('Pembayaran yang sudah lunas') }}</flux:text>
                <x-rupiah :jumlah="$this->total['sudahDibayar']" class="text-2xl font-bold" />
            </flux:card>
        </div>
    </section>
</section>
