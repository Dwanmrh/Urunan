<?php

use App\Enums\CaraBagi;
use App\Models\Grup;
use App\Models\Tagihan;
use App\Models\User;

test('pembuat grup otomatis menjadi anggota dan grup mendapat kode undangan', function () {
    $user = User::factory()->create(['name' => 'Dwan']);

    $grup = Grup::buatUntuk($user, 'Makan malam');

    expect($grup->kode_undangan)->not->toBeEmpty();
    expect($grup->anggota()->count())->toBe(1);
    expect($grup->anggota()->first()->user_id)->toBe($user->id);
});

test('tagihan bagi rata menghasilkan saldo yang benar dan berubah setelah ada pembayaran', function () {
    $grup = Grup::buatUntuk(User::factory()->create(), 'Makan malam');
    $dwan = $grup->anggota()->first();
    $sinta = $grup->anggota()->create(['nama' => 'Sinta']);
    $raka = $grup->anggota()->create(['nama' => 'Raka']);
    $budi = $grup->anggota()->create(['nama' => 'Budi']);

    $tagihan = Tagihan::factory()->create([
        'grup_id' => $grup->id,
        'dibayar_oleh' => $dwan->id,
        'total' => 200000,
        'cara_bagi' => CaraBagi::Rata,
    ]);
    $tagihan->simpanBagian();

    expect($grup->saldo())->toBe([
        $dwan->id => 150000,
        $sinta->id => -50000,
        $raka->id => -50000,
        $budi->id => -50000,
    ]);

    $grup->pembayaran()->create([
        'tagihan_id' => $tagihan->id,
        'dari_anggota_id' => $sinta->id,
        'ke_anggota_id' => $dwan->id,
        'jumlah' => 50000,
        'dibayar_pada' => now(),
    ]);

    expect($grup->saldo())->toBe([
        $dwan->id => 100000,
        $sinta->id => 0,
        $raka->id => -50000,
        $budi->id => -50000,
    ]);
});

test('tagihan per item hanya membebani anggota yang ikut menanggung item', function () {
    $grup = Grup::buatUntuk(User::factory()->create(), 'Makan siang');
    $dwan = $grup->anggota()->first();
    $sinta = $grup->anggota()->create(['nama' => 'Sinta']);

    $tagihan = Tagihan::factory()->create([
        'grup_id' => $grup->id,
        'dibayar_oleh' => $dwan->id,
        'total' => 90000,
        'cara_bagi' => CaraBagi::PerItem,
    ]);

    $ayamBakar = $tagihan->item()->create(['nama' => 'Ayam bakar', 'harga' => 60000]);
    $ayamBakar->anggota()->attach($sinta->id);

    $esTeh = $tagihan->item()->create(['nama' => 'Es teh', 'harga' => 30000, 'jumlah' => 2]);
    $esTeh->anggota()->attach([$dwan->id, $sinta->id]);

    expect($tagihan->hitungBagian())->toBe([
        $dwan->id => 15000,
        $sinta->id => 75000,
    ]);
});
