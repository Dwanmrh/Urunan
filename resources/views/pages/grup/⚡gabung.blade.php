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
                'pilihan.required' => 'Pilih salah satu.',
                'pilihan.in' => 'Pilihan tidak valid. Mungkin nama itu sudah diakui orang lain, coba muat ulang halaman.',
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

<section class="w-full max-w-lg">
    <flux:heading size="xl" level="1">Gabung ke {{ $grup->nama }}</flux:heading>
    <flux:subheading size="lg" class="mb-6">Diundang oleh {{ $grup->pembuat->name }}</flux:subheading>

    <form wire:submit="gabung" class="space-y-6">
        <flux:radio.group wire:model="pilihan" label="Kamu yang mana?" variant="cards" class="flex-col">
            <flux:radio value="baru" label="Saya anggota baru" description="Masuk sebagai {{ auth()->user()->name }}" />

            @foreach ($this->anggotaTanpaAkun as $anggota)
                <flux:radio
                    value="{{ $anggota->id }}"
                    label="Saya {{ $anggota->nama }}"
                    description="Sudah dimasukkan ke grup, tagihannya ikut tersambung ke akunmu"
                    wire:key="anggota-{{ $anggota->id }}"
                />
            @endforeach
        </flux:radio.group>

        <flux:button variant="primary" type="submit" class="w-full" data-test="gabung-grup">Gabung</flux:button>
    </form>
</section>
