<?php

use App\Models\Grup;
use App\Models\User;
use Livewire\Livewire;

test('tamu diarahkan ke halaman login saat membuka daftar grup', function () {
    $this->get(route('grup.index'))->assertRedirect(route('login'));
});

test('user melihat grup yang diikutinya di daftar grup', function () {
    $user = User::factory()->create();
    Grup::buatUntuk($user, 'Makan malam ultah');
    Grup::buatUntuk(User::factory()->create(), 'Grup orang lain');

    $this->actingAs($user)
        ->get(route('grup.index'))
        ->assertOk()
        ->assertSee('Makan malam ultah')
        ->assertDontSee('Grup orang lain');
});

test('user bisa membuat grup dan langsung menjadi anggotanya', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::grup.index')
        ->set('nama', 'Liburan Pangandaran')
        ->call('buatGrup')
        ->assertHasNoErrors();

    $grup = Grup::where('nama', 'Liburan Pangandaran')->first();

    expect($grup)->not->toBeNull();
    expect($grup->punyaAnggota($user))->toBeTrue();
});

test('nama grup wajib diisi', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::grup.index')
        ->set('nama', '')
        ->call('buatGrup')
        ->assertHasErrors(['nama']);

    expect(Grup::count())->toBe(0);
});

test('user yang bukan anggota tidak bisa membuka halaman grup', function () {
    $grup = Grup::buatUntuk(User::factory()->create(), 'Grup tertutup');

    $this->actingAs(User::factory()->create())
        ->get(route('grup.show', $grup))
        ->assertForbidden();
});

test('anggota bisa membuka halaman grup dan menambah anggota tanpa akun', function () {
    $user = User::factory()->create();
    $grup = Grup::buatUntuk($user, 'Kos November');

    $this->actingAs($user);

    $this->get(route('grup.show', $grup))->assertOk()->assertSee('Kos November');

    Livewire::test('pages::grup.show', ['grup' => $grup])
        ->set('namaAnggota', 'Sinta')
        ->call('tambahAnggota')
        ->assertHasNoErrors();

    expect($grup->anggota()->pluck('nama')->all())->toContain('Sinta');
    expect($grup->anggota()->count())->toBe(2);
});
