<?php

use App\Models\Tagihan;
use App\Models\User;
use App\Notifications\PembayaranDikonfirmasi;
use App\Notifications\TagihanBaru;
use Illuminate\Support\Str;

test('tamu bisa mengganti bahasa ke Inggris dan pilihannya bertahan', function () {
    $this->get(route('home'))->assertSee('Patungan bareng teman,');

    $this->get(route('bahasa.ganti', 'en'))->assertRedirect();

    $this->get(route('home'))
        ->assertSee('Split bills with friends,')
        ->assertDontSee('Patungan bareng teman,');
});

test('pilihan bahasa user yang login tersimpan di akunnya', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('bahasa.ganti', 'en'))->assertRedirect();

    expect($user->fresh()->locale)->toBe('en');

    $this->actingAs($user->fresh())->get(route('grup.index'))->assertSee('Group splits');
});

test('bahasa yang tidak tersedia ditolak', function () {
    $this->get(route('bahasa.ganti', 'fr'))->assertNotFound();
});

test('notifikasi dikirim dalam bahasa penerimanya', function () {
    $userInggris = User::factory()->create();
    $userInggris->forceFill(['locale' => 'en'])->save();
    $userIndonesia = User::factory()->create();
    $tagihan = Tagihan::factory()->create(['judul' => 'Ngegrill']);

    $userInggris->notify(new TagihanBaru($tagihan, 22000));
    $userIndonesia->notify(new TagihanBaru($tagihan, 22000));

    expect($userInggris->notifications()->first()->data['judul'])->toBe('New bill');
    expect($userIndonesia->notifications()->first()->data['judul'])->toBe('Tagihan baru');
});

test('notifikasi tampil dalam bahasa yang sedang dipilih, termasuk notifikasi lama', function () {
    $user = User::factory()->create();
    $tagihan = Tagihan::factory()->create(['judul' => 'Ngegrill']);

    $user->notify(new TagihanBaru($tagihan, 22000));

    $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => PembayaranDikonfirmasi::class,
        'data' => [
            'judul' => 'Pembayaran diterima',
            'pesan' => 'Pembayaranmu Rp 22.000 untuk Ngegrill sudah dikonfirmasi Deren. Status: lunas.',
            'url' => '/grup/1/tagihan/1',
        ],
    ]);

    $this->actingAs($user)->get(route('notifikasi.index'))
        ->assertSee('Tagihan baru')
        ->assertSee('Pembayaran diterima');

    $user->forceFill(['locale' => 'en'])->save();

    $this->actingAs($user->fresh())->get(route('notifikasi.index'))
        ->assertSee('New bill')
        ->assertSee('Your share is Rp 22.000.')
        ->assertSee('Payment received')
        ->assertSee('Your Rp 22.000 payment for Ngegrill was confirmed by Deren. Status: paid.')
        ->assertDontSee('Pembayaran diterima');
});
