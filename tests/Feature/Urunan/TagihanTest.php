<?php

use App\Enums\CaraBagi;
use App\Models\Grup;
use App\Models\Tagihan;
use App\Models\User;
use Livewire\Livewire;

/**
 * Siapkan grup berisi Dwan (punya akun) dan tiga anggota tanpa akun.
 *
 * @return array{0: User, 1: Grup}
 */
function grupMakanMalam(): array
{
    $user = User::factory()->create(['name' => 'Dwan']);
    $grup = Grup::buatUntuk($user, 'Makan malam ultah');

    foreach (['Sinta', 'Raka', 'Budi'] as $nama) {
        $grup->anggota()->create(['nama' => $nama]);
    }

    return [$user, $grup];
}

test('anggota bisa mencatat tagihan bagi rata', function () {
    [$user, $grup] = grupMakanMalam();
    $dwan = $grup->anggota()->where('user_id', $user->id)->first();

    $this->actingAs($user);

    Livewire::test('pages::tagihan.create', ['grup' => $grup])
        ->set('judul', 'Makan malam')
        ->set('total', '200000')
        ->call('simpan')
        ->assertHasNoErrors();

    $tagihan = Tagihan::first();

    expect($tagihan->total)->toBe(200000);
    expect($tagihan->dibayar_oleh)->toBe($dwan->id);
    expect((int) $tagihan->bagian()->sum('jumlah'))->toBe(200000);
    expect($grup->saldo()[$dwan->id])->toBe(150000);
});

test('anggota bisa mencatat tagihan per item', function () {
    [$user, $grup] = grupMakanMalam();
    $dwan = $grup->anggota()->where('user_id', $user->id)->first();
    $sinta = $grup->anggota()->where('nama', 'Sinta')->first();

    $this->actingAs($user);

    Livewire::test('pages::tagihan.create', ['grup' => $grup])
        ->set('judul', 'Makan siang berdua')
        ->set('caraBagi', CaraBagi::PerItem->value)
        ->set('item', [
            ['nama' => 'Ayam bakar', 'harga' => '60000', 'penanggung' => [(string) $sinta->id]],
            ['nama' => 'Es teh', 'harga' => '30000', 'penanggung' => [(string) $dwan->id, (string) $sinta->id]],
        ])
        ->call('simpan')
        ->assertHasNoErrors();

    $tagihan = Tagihan::first();

    expect($tagihan->total)->toBe(90000);
    expect($tagihan->item()->count())->toBe(2);
    expect($tagihan->hitungBagian())->toBe([
        $dwan->id => 15000,
        $sinta->id => 75000,
    ]);
});

test('total dan harga item wajib diisi', function () {
    [$user, $grup] = grupMakanMalam();

    $this->actingAs($user);

    Livewire::test('pages::tagihan.create', ['grup' => $grup])
        ->set('judul', 'Makan malam')
        ->set('total', '')
        ->call('simpan')
        ->assertHasErrors(['total']);

    Livewire::test('pages::tagihan.create', ['grup' => $grup])
        ->set('judul', 'Makan malam')
        ->set('caraBagi', CaraBagi::PerItem->value)
        ->set('item', [['nama' => 'Ayam bakar', 'harga' => '', 'penanggung' => []]])
        ->call('simpan')
        ->assertHasErrors(['item.0.harga']);

    expect(Tagihan::count())->toBe(0);
});

test('bukan anggota tidak bisa membuka form tambah tagihan', function () {
    [, $grup] = grupMakanMalam();

    $this->actingAs(User::factory()->create())
        ->get(route('tagihan.create', $grup))
        ->assertForbidden();
});

test('tagihan dari grup lain tidak bisa dibuka lewat alamat grup ini', function () {
    [$user, $grup] = grupMakanMalam();
    [, $grupLain] = grupMakanMalam();
    $tagihanLain = Tagihan::factory()->create([
        'grup_id' => $grupLain->id,
        'dibayar_oleh' => $grupLain->anggota()->first()->id,
    ]);

    $this->actingAs($user)
        ->get(route('tagihan.show', [$grup, $tagihanLain]))
        ->assertNotFound();
});

test('menandai lunas mencatat pembayaran sebesar sisa bagian dan tidak dobel', function () {
    [$user, $grup] = grupMakanMalam();
    $dwan = $grup->anggota()->where('user_id', $user->id)->first();
    $sinta = $grup->anggota()->where('nama', 'Sinta')->first();

    $tagihan = Tagihan::catat($grup, [
        'judul' => 'Makan malam',
        'tanggal' => now()->toDateString(),
        'dibayar_oleh' => $dwan->id,
        'cara_bagi' => CaraBagi::Rata,
        'total' => 200000,
        'peserta' => $grup->anggota()->orderBy('id')->pluck('id')->all(),
        'item' => [],
    ]);

    $this->actingAs($user);

    $this->get(route('tagihan.show', [$grup, $tagihan]))->assertOk()->assertSee('Sinta');

    Livewire::test('pages::tagihan.show', ['grup' => $grup, 'tagihan' => $tagihan])
        ->call('tandaiLunas', $sinta->id)
        ->call('tandaiLunas', $sinta->id);

    expect($tagihan->pembayaran()->count())->toBe(1);
    expect($tagihan->pembayaran()->first()->jumlah)->toBe(50000);
    expect($grup->saldo()[$sinta->id])->toBe(0);
    expect($grup->saldo()[$dwan->id])->toBe(100000);
});
