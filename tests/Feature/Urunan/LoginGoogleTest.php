<?php

use App\Models\Grup;
use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as AkunGoogle;

beforeEach(function () {
    config([
        'services.google.client_id' => 'client-uji',
        'services.google.client_secret' => 'rahasia-uji',
    ]);
});

/**
 * Pura-pura Google mengembalikan akun ini setelah user memilih akun.
 *
 * @param  array{id?: string, name?: string, email?: string, verified?: bool}  $data
 */
function palsukanAkunGoogle(array $data = []): void
{
    $akun = (new AkunGoogle)
        ->setRaw(['email_verified' => $data['verified'] ?? true])
        ->map([
            'id' => $data['id'] ?? 'google-123',
            'name' => $data['name'] ?? 'Sinta Rahma',
            'email' => $data['email'] ?? 'sinta@gmail.com',
        ]);

    $provider = Mockery::mock(GoogleProvider::class);
    $provider->shouldReceive('user')->andReturn($akun);

    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
}

test('tombol Google tampil di halaman login hanya kalau sudah diatur', function () {
    $this->get(route('login'))->assertOk()->assertSee('Masuk dengan Google');

    config(['services.google.client_id' => null]);

    $this->get(route('login'))->assertOk()->assertDontSee('Masuk dengan Google');
});

test('tombol Google mengarahkan ke halaman pilih akun Google', function () {
    $this->get(route('google.redirect'))->assertRedirectContains('accounts.google.com');
});

test('akun Google baru langsung didaftarkan dan masuk', function () {
    palsukanAkunGoogle();

    $this->get(route('google.callback'))->assertRedirect(route('dashboard'));

    $user = User::where('email', 'sinta@gmail.com')->first();

    expect($user)->not->toBeNull();
    expect($user->name)->toBe('Sinta Rahma');
    expect($user->google_id)->toBe('google-123');
    expect($user->email_verified_at)->not->toBeNull();
    $this->assertAuthenticatedAs($user);
});

test('email yang sudah terdaftar disambungkan ke Google, bukan dibuat dobel', function () {
    $user = User::factory()->create(['email' => 'sinta@gmail.com']);
    palsukanAkunGoogle(['email' => 'Sinta@Gmail.com']);

    $this->get(route('google.callback'))->assertRedirect(route('dashboard'));

    expect(User::count())->toBe(1);
    expect($user->fresh()->google_id)->toBe('google-123');
    $this->assertAuthenticatedAs($user);
});

test('email Google yang belum terverifikasi tidak bisa masuk', function () {
    User::factory()->create(['email' => 'sinta@gmail.com']);
    palsukanAkunGoogle(['verified' => false]);

    $this->get(route('google.callback'))->assertRedirect(route('login'));

    $this->assertGuest();
});

test('akun yang memakai 2FA tetap diminta kode 2FA', function () {
    User::factory()->withTwoFactor()->create(['email' => 'sinta@gmail.com']);
    palsukanAkunGoogle();

    $this->get(route('google.callback'))->assertRedirect(route('two-factor.login'));

    $this->assertGuest();
});

test('kalau Google gagal atau dibatalkan, kembali ke login', function () {
    $provider = Mockery::mock(GoogleProvider::class);
    $provider->shouldReceive('user')->andThrow(new InvalidStateException);
    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $this->get(route('google.callback'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('status');

    $this->assertGuest();
});

test('teman yang membuka link undangan lalu masuk dengan Google dibawa kembali ke halaman gabung', function () {
    $grup = Grup::buatUntuk(User::factory()->create(), 'Liburan');
    palsukanAkunGoogle();

    $this->get(route('grup.gabung', $grup->kode_undangan))->assertRedirect(route('login'));

    $this->get(route('google.callback'))->assertRedirect(route('grup.gabung', $grup->kode_undangan));
});

test('akun berisi email yang belum terverifikasi dicabut akses lamanya saat pemilik asli masuk dengan Google', function () {
    $penyusup = User::factory()->unverified()->create(['email' => 'sinta@gmail.com', 'password' => 'rahasia-penyusup']);
    $hashLama = $penyusup->password;

    palsukanAkunGoogle();

    $this->get(route('google.callback'))->assertRedirect(config('fortify.home'));

    $user = $penyusup->fresh();

    expect($user->password)->not->toBe($hashLama);
    expect($user->google_id)->toBe('google-123');
    expect($user->email_verified_at)->not->toBeNull();
    $this->assertAuthenticatedAs($user);
});
