<?php

use App\Models\Tagihan;
use App\Models\User;
use App\Notifications\TagihanBaru;

test('tamu bisa mengganti bahasa ke Inggris dan pilihannya bertahan', function () {
    $this->get(route('home'))->assertSee('Patungan bareng teman, tanpa hitung manual.');

    $this->get(route('bahasa.ganti', 'en'))->assertRedirect();

    $this->get(route('home'))
        ->assertSee('Split bills with friends, no manual math.')
        ->assertDontSee('Patungan bareng teman, tanpa hitung manual.');
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
