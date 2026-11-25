<?php

use App\Enums\CaraBagi;
use App\Models\AnggotaGrup;
use App\Models\Grup;
use App\Models\Pembayaran;
use App\Models\Tagihan;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * Grup buatan Dwan berisi Deren (punya akun) dan Raka (tanpa akun).
 *
 * @return array{userDwan: User, userDeren: User, grup: Grup, deren: AnggotaGrup, raka: AnggotaGrup}
 */
function grupBuatanDwan(): array
{
    $userDwan = User::factory()->create(['name' => 'Dwan']);
    $userDeren = User::factory()->create(['name' => 'Deren']);
    $grup = Grup::buatUntuk($userDwan, 'Liburan');
    $deren = $grup->anggota()->create(['nama' => 'Deren', 'user_id' => $userDeren->id]);
    $raka = $grup->anggota()->create(['nama' => 'Raka']);

    return compact('userDwan', 'userDeren', 'grup', 'deren', 'raka');
}

test('pembuat grup bisa mengganti nama grup', function () {
    ['userDwan' => $userDwan, 'grup' => $grup] = grupBuatanDwan();

    $this->actingAs($userDwan);

    Livewire::test('pages::grup.show', ['grup' => $grup])
        ->set('nama', 'Liburan Pangandaran')
        ->call('ubahNamaGrup')
        ->assertHasNoErrors();

    expect($grup->fresh()->nama)->toBe('Liburan Pangandaran');
});

test('anggota biasa tidak bisa mengganti nama, menghapus grup, atau mengatur anggota', function () {
    ['userDeren' => $userDeren, 'grup' => $grup, 'raka' => $raka] = grupBuatanDwan();

    $this->actingAs($userDeren);

    Livewire::test('pages::grup.show', ['grup' => $grup])
        ->set('nama', 'Diganti')
        ->call('ubahNamaGrup')
        ->assertForbidden();

    Livewire::test('pages::grup.show', ['grup' => $grup])->call('hapusGrup')->assertForbidden();
    Livewire::test('pages::grup.show', ['grup' => $grup])->call('hapusAnggota', $raka->id)->assertForbidden();

    expect($grup->fresh()->nama)->toBe('Liburan');
    expect($raka->fresh())->not->toBeNull();
});

test('menghapus grup ikut menghapus tagihan, pembayaran, dan foto bukti', function () {
    Storage::fake('local');
    ['userDwan' => $userDwan, 'userDeren' => $userDeren, 'grup' => $grup, 'deren' => $deren] = grupBuatanDwan();
    $dwan = $grup->anggota()->where('user_id', $userDwan->id)->first();

    $tagihan = Tagihan::catat($grup, [
        'judul' => 'Villa',
        'tanggal' => now()->toDateString(),
        'dibayar_oleh' => $dwan->id,
        'cara_bagi' => CaraBagi::Rata,
        'total' => 300000,
        'peserta' => $grup->anggota()->orderBy('id')->pluck('id')->all(),
        'item' => [],
    ]);
    $path = UploadedFile::fake()->create('bukti.jpg', 100, 'image/jpeg')->store('bukti', 'local');
    $pembayaran = $tagihan->kirimBukti($deren, $path, $userDeren);
    Storage::disk('local')->assertExists($pembayaran->bukti_path);

    $this->actingAs($userDwan);

    Livewire::test('pages::grup.show', ['grup' => $grup])
        ->call('hapusGrup')
        ->assertRedirect(route('grup.index'));

    expect(Grup::count())->toBe(0);
    expect(Tagihan::count())->toBe(0);
    expect(Pembayaran::count())->toBe(0);
    Storage::disk('local')->assertMissing($pembayaran->bukti_path);
});

test('anggota yang belum tercatat bisa dikeluarkan, yang sudah tercatat tidak', function () {
    ['userDwan' => $userDwan, 'grup' => $grup, 'deren' => $deren, 'raka' => $raka] = grupBuatanDwan();
    $dwan = $grup->anggota()->where('user_id', $userDwan->id)->first();

    Tagihan::catat($grup, [
        'judul' => 'Bensin',
        'tanggal' => now()->toDateString(),
        'dibayar_oleh' => $dwan->id,
        'cara_bagi' => CaraBagi::Rata,
        'total' => 100000,
        'peserta' => [$dwan->id, $deren->id],
        'item' => [],
    ]);

    $this->actingAs($userDwan);

    Livewire::test('pages::grup.show', ['grup' => $grup])->call('hapusAnggota', $deren->id);
    Livewire::test('pages::grup.show', ['grup' => $grup])->call('hapusAnggota', $raka->id);

    expect($deren->fresh())->not->toBeNull();
    expect($raka->fresh())->toBeNull();
    expect($dwan->bisaDihapus())->toBeFalse();
});

test('pembuat grup bisa mengganti nama anggota tanpa akun saja', function () {
    ['userDwan' => $userDwan, 'grup' => $grup, 'deren' => $deren, 'raka' => $raka] = grupBuatanDwan();

    $this->actingAs($userDwan);

    Livewire::test('pages::grup.show', ['grup' => $grup])
        ->call('ubahAnggota', $raka->id)
        ->assertSet('namaAnggotaBaru', 'Raka')
        ->set('namaAnggotaBaru', 'Raka Pratama')
        ->call('simpanAnggota')
        ->assertHasNoErrors();

    expect($raka->fresh()->nama)->toBe('Raka Pratama');

    Livewire::test('pages::grup.show', ['grup' => $grup])
        ->call('ubahAnggota', $deren->id)
        ->assertForbidden();
});

test('pembuat grup bisa mengedit dan menghapus tagihan yang dibayar anggota lain, tapi tidak mengonfirmasinya', function () {
    ['userDwan' => $userDwan, 'grup' => $grup, 'deren' => $deren, 'raka' => $raka] = grupBuatanDwan();

    $tagihan = Tagihan::catat($grup, [
        'judul' => 'Ngegrill',
        'tanggal' => now()->toDateString(),
        'dibayar_oleh' => $deren->id,
        'cara_bagi' => CaraBagi::Rata,
        'total' => 90000,
        'peserta' => $grup->anggota()->orderBy('id')->pluck('id')->all(),
        'item' => [],
    ]);

    $this->actingAs($userDwan);

    $this->get(route('tagihan.edit', [$grup, $tagihan]))->assertOk();

    Livewire::test('pages::tagihan.show', ['grup' => $grup, 'tagihan' => $tagihan])
        ->call('tandaiLunas', $raka->id)
        ->assertForbidden();

    Livewire::test('pages::tagihan.show', ['grup' => $grup, 'tagihan' => $tagihan])
        ->call('hapus')
        ->assertRedirect(route('grup.show', $grup));

    expect(Tagihan::count())->toBe(0);
});
