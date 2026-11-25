<?php

use App\Models\Grup;
use App\Models\User;

test('manifest aplikasi lengkap supaya bisa dipasang', function () {
    $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true, flags: JSON_THROW_ON_ERROR);

    expect($manifest)
        ->name->toContain('Udunan')
        ->display->toBe('standalone')
        ->start_url->toStartWith('/dashboard');

    $ukuran = collect($manifest['icons'])->pluck('sizes')->all();
    expect($ukuran)->toContain('192x192')->toContain('512x512')
        ->and(collect($manifest['icons'])->pluck('purpose')->all())->toContain('maskable');

    foreach ($manifest['icons'] as $ikon) {
        expect(file_exists(public_path(ltrim($ikon['src'], '/'))))->toBeTrue();
    }

    expect(file_exists(public_path('sw.js')))->toBeTrue()
        ->and(file_exists(public_path('offline.html')))->toBeTrue();
});

test('halaman memuat manifest dan tag aplikasi', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('rel="manifest"', false)
        ->assertSee('apple-mobile-web-app-capable', false)
        ->assertSee('viewport-fit=cover', false);
});

test('navigasi bawah menampilkan grup untuk catat tagihan cepat', function () {
    $user = User::factory()->create();
    $grup = Grup::buatUntuk($user, 'Kos Melati');

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('data-test="navigasi-bawah"', false)
        ->assertSee(route('tagihan.create', $grup), false)
        ->assertSee(__('Pasang Udunan di HP-mu'));
});

test('halaman profil tidak langsung membuka keyboard di HP', function () {
    $html = $this->actingAs(User::factory()->create())
        ->get(route('profile.edit'))
        ->assertOk()
        ->getContent();

    expect(preg_match('/<input[^>]*\sautofocus[\s>=]/', $html))->toBe(0);
});
