<?php

use App\Models\AnggotaGrup;
use App\Models\Grup;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Gabung grup')] class extends Component {
    public Grup $grup;

    public string $pilihan = 'baru';

    /**
     * Mount the component.
     */
    public function mount(string $kode): void
    {
        $this->grup = Grup::where('kode_undangan', $kode)->firstOrFail();

        if ($this->grup->punyaAnggota(Auth::user())) {
            $this->redirectRoute('grup.show', $this->grup, navigate: true);
        }
    }

    /**
     * Anggota tanpa akun yang bisa diakui sebagai diri sendiri.
     *
     * @return Collection<int, AnggotaGrup>
     */
    #[Computed]
    public function anggotaTanpaAkun(): Collection
    {
        return $this->grup->anggota()->whereNull('user_id')->orderBy('id')->get();
    }

    /**
     * Gabung ke grup, sebagai anggota baru atau sebagai anggota tanpa akun yang sudah ada.
     */
    public function gabung(): void
    {
        $user = Auth::user();

        if ($this->grup->punyaAnggota($user)) {
            $this->redirectRoute('grup.show', $this->grup, navigate: true);

            return;
        }

        $this->validate(
            ['pilihan' => ['required', Rule::in(['baru', ...$this->anggotaTanpaAkun->pluck('id')->map(fn (int $id): string => (string) $id)])]],
            [
                'pilihan.required' => __('Pilih salah satu.'),
                'pilihan.in' => __('Pilihan tidak valid. Mungkin nama itu sudah diakui orang lain, coba muat ulang halaman.'),
            ],
        );

        if ($this->pilihan === 'baru') {
            $this->grup->anggota()->create([
                'user_id' => $user->id,
                'nama' => $user->name,
            ]);
        } else {
            $this->grup->anggota()
                ->whereNull('user_id')
                ->findOrFail((int) $this->pilihan)
                ->update(['user_id' => $user->id]);
        }

        $this->redirectRoute('grup.show', $this->grup, navigate: true);
    }
}; ?>

<section class="mx-auto w-full max-w-lg">
    <div class="overflow-hidden rounded-3xl border border-zinc-200 bg-white shadow-xl shadow-zinc-900/5 motion-safe:animate-[muncul_0.5s_ease-out_both] dark:border-zinc-700/70 dark:bg-zinc-900">
        <div class="relative flex flex-col items-center bg-linear-to-br {{ $grup->warna->gradasi() }} {{ $grup->warna->teks() }} px-6 pt-8 pb-16 text-center">
            <div aria-hidden="true" class="absolute -top-16 -right-10 size-48 rounded-full bg-white/10 blur-2xl"></div>
            <p class="relative text-sm font-medium {{ $grup->warna->teksRedup() }}">{{ __('Kamu diundang ke') }}</p>
            <h1 class="relative mt-1 text-2xl font-bold tracking-tight break-words">{{ $grup->nama }}</h1>
            <p class="relative mt-1 text-sm font-medium {{ $grup->warna->teksRedup() }}">{{ __('Diundang oleh :nama', ['nama' => $grup->pembuat->name]) }}</p>
        </div>

        <span class="relative mx-auto -mt-9 flex size-[4.5rem] items-center justify-center rounded-3xl bg-white text-4xl shadow-lg ring-4 ring-white transition hover:scale-110 hover:-rotate-6 dark:bg-zinc-800 dark:ring-zinc-900" aria-hidden="true">{{ $grup->emoji }}</span>

        <form wire:submit="gabung" class="space-y-6 p-6">
            <flux:radio.group wire:model="pilihan" label="{{ __('Kamu yang mana?') }}" variant="cards" class="flex-col">
                <flux:radio value="baru" label="{{ __('Saya anggota baru') }}" description="{{ __('Masuk sebagai :nama', ['nama' => auth()->user()->name]) }}" />

                @foreach ($this->anggotaTanpaAkun as $anggota)
                    <flux:radio
                        value="{{ $anggota->id }}"
                        label="{{ __('Saya :nama', ['nama' => $anggota->nama]) }}"
                        description="{{ __('Sudah dimasukkan ke grup, tagihannya ikut tersambung ke akunmu') }}"
                        wire:key="anggota-{{ $anggota->id }}"
                    />
                @endforeach
            </flux:radio.group>

            <flux:button variant="primary" type="submit" class="w-full" data-test="gabung-grup">{{ __('Gabung sekarang') }} 🚀</flux:button>
        </form>
    </div>
</section>
