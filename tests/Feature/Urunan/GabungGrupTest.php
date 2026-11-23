<?php

use App\Models\Grup;
use App\Models\User;
use Livewire\Livewire;

test('tamu yang membuka link undangan diarahkan ke login', function () {
    $grup = Grup::buatUntuk(User::factory()->create(), 'Liburan');

    $this->get(route('grup.gabung', $grup->kode_undangan))->assertRedirect(route('login'));
});

test('kode undangan yang salah menghasilkan 404', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('grup.gabung', 'kodesalah'))
        ->assertNotFound();
});

test('user bisa gabung sebagai anggota baru', function () {
    $grup = Grup::buatUntuk(User::factory()->create(), 'Liburan');
    $user = User::factory()->create(['name' => 'Raka']);

    $this->actingAs($user);

    $this->get(route('grup.gabung', $grup->kode_undangan))->assertOk()->assertSee('Liburan');

    Livewire::test('pages::grup.gabung', ['kode' => $grup->kode_undangan])
        ->set('pilihan', 'baru')
        ->call('gabung')
        ->assertRedirect(route('grup.show', $grup));

    expect($grup->punyaAnggota($user))->toBeTrue();
    expect($grup->anggota()->count())->toBe(2);
});

test('user bisa mengakui anggota tanpa akun yang sudah ada sehingga tagihannya tersambung', function () {
    $grup = Grup::buatUntuk(User::factory()->create(), 'Liburan');
    $sinta = $grup->anggota()->create(['nama' => 'Sinta']);
    $user = User::factory()->create(['name' => 'Sinta Rahma']);

    $this->actingAs($user);

    Livewire::test('pages::grup.gabung', ['kode' => $grup->kode_undangan])
        ->set('pilihan', (string) $sinta->id)
        ->call('gabung')
        ->assertRedirect(route('grup.show', $grup));

    expect($sinta->fresh()->user_id)->toBe($user->id);
    expect($grup->anggota()->count())->toBe(2);
});

test('anggota yang sudah punya akun tidak bisa diakui orang lain', function () {
    $pembuat = User::factory()->create();
    $grup = Grup::buatUntuk($pembuat, 'Liburan');
    $anggotaPembuat = $grup->anggota()->first();

    $this->actingAs(User::factory()->create());

    Livewire::test('pages::grup.gabung', ['kode' => $grup->kode_undangan])
        ->set('pilihan', (string) $anggotaPembuat->id)
        ->call('gabung')
        ->assertHasErrors(['pilihan']);

    expect($anggotaPembuat->fresh()->user_id)->toBe($pembuat->id);
});

test('anggota yang membuka link undangan langsung diarahkan ke grupnya', function () {
    $user = User::factory()->create();
    $grup = Grup::buatUntuk($user, 'Liburan');

    $this->actingAs($user)
        ->get(route('grup.gabung', $grup->kode_undangan))
        ->assertRedirect(route('grup.show', $grup));
});
