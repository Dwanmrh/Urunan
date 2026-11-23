<?php

use App\Enums\CaraBagi;
use App\Enums\StatusPembayaran;
use App\Models\AnggotaGrup;
use App\Models\Grup;
use App\Models\Tagihan;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * Siapkan tagihan Rp 200.000 yang dibayar Dwan untuk 4 orang, dengan Sinta punya akun.
 *
 * @return array{userDwan: User, userSinta: User, grup: Grup, tagihan: Tagihan, sinta: AnggotaGrup}
 */
function tagihanDenganSinta(): array
{
    $userDwan = User::factory()->create(['name' => 'Dwan']);
    $userSinta = User::factory()->create(['name' => 'Sinta']);
    $grup = Grup::buatUntuk($userDwan, 'Makan malam');
    $dwan = $grup->anggota()->first();
    $sinta = $grup->anggota()->create(['nama' => 'Sinta', 'user_id' => $userSinta->id]);
    $grup->anggota()->create(['nama' => 'Raka']);
    $grup->anggota()->create(['nama' => 'Budi']);

    $tagihan = Tagihan::catat($grup, [
        'judul' => 'Makan malam',
        'tanggal' => now()->toDateString(),
        'dibayar_oleh' => $dwan->id,
        'cara_bagi' => CaraBagi::Rata,
        'total' => 200000,
        'peserta' => $grup->anggota()->orderBy('id')->pluck('id')->all(),
        'item' => [],
    ]);

    return compact('userDwan', 'userSinta', 'grup', 'tagihan', 'sinta');
}

test('anggota bisa mengirim bukti dan statusnya menunggu sampai pembayar mengonfirmasi', function () {
    Storage::fake('local');
    ['userDwan' => $userDwan, 'userSinta' => $userSinta, 'grup' => $grup, 'tagihan' => $tagihan, 'sinta' => $sinta] = tagihanDenganSinta();

    $this->actingAs($userSinta);

    Livewire::test('pages::tagihan.show', ['grup' => $grup, 'tagihan' => $tagihan])
        ->set('bukti', UploadedFile::fake()->create('bukti.jpg', 200, 'image/jpeg'))
        ->call('kirimBukti')
        ->assertHasNoErrors();

    $pembayaran = $tagihan->pembayaran()->first();

    expect($pembayaran->status)->toBe(StatusPembayaran::Menunggu);
    expect($pembayaran->jumlah)->toBe(50000);
    Storage::disk('local')->assertExists($pembayaran->bukti_path);
    expect($grup->saldo()[$sinta->id])->toBe(-50000);

    $this->get(route('pembayaran.bukti', [$grup, $pembayaran]))->assertOk();

    $this->actingAs($userDwan);

    Livewire::test('pages::tagihan.show', ['grup' => $grup, 'tagihan' => $tagihan])
        ->call('konfirmasi', $pembayaran->id)
        ->assertHasNoErrors();

    expect($pembayaran->fresh()->status)->toBe(StatusPembayaran::Diterima);
    expect($grup->saldo()[$sinta->id])->toBe(0);
});

test('pembayar bisa menolak bukti dan filenya ikut dihapus', function () {
    Storage::fake('local');
    ['userDwan' => $userDwan, 'grup' => $grup, 'tagihan' => $tagihan, 'sinta' => $sinta, 'userSinta' => $userSinta] = tagihanDenganSinta();

    $path = UploadedFile::fake()->create('bukti.jpg', 200, 'image/jpeg')->store('bukti-pembayaran', 'local');
    $pembayaran = $tagihan->kirimBukti($sinta, $path, $userSinta);

    $this->actingAs($userDwan);

    Livewire::test('pages::tagihan.show', ['grup' => $grup, 'tagihan' => $tagihan])
        ->call('tolak', $pembayaran->id);

    expect($tagihan->pembayaran()->count())->toBe(0);
    Storage::disk('local')->assertMissing($path);
});

test('anggota tidak bisa mengonfirmasi bukti miliknya sendiri', function () {
    Storage::fake('local');
    ['grup' => $grup, 'tagihan' => $tagihan, 'sinta' => $sinta, 'userSinta' => $userSinta] = tagihanDenganSinta();

    $path = UploadedFile::fake()->create('bukti.jpg', 200, 'image/jpeg')->store('bukti-pembayaran', 'local');
    $pembayaran = $tagihan->kirimBukti($sinta, $path, $userSinta);

    $this->actingAs($userSinta);

    Livewire::test('pages::tagihan.show', ['grup' => $grup, 'tagihan' => $tagihan])
        ->call('konfirmasi', $pembayaran->id)
        ->assertForbidden();

    expect($pembayaran->fresh()->status)->toBe(StatusPembayaran::Menunggu);
});

test('bukti tidak bisa dibuka oleh orang di luar grup', function () {
    Storage::fake('local');
    ['grup' => $grup, 'tagihan' => $tagihan, 'sinta' => $sinta, 'userSinta' => $userSinta] = tagihanDenganSinta();

    $path = UploadedFile::fake()->create('bukti.jpg', 200, 'image/jpeg')->store('bukti-pembayaran', 'local');
    $pembayaran = $tagihan->kirimBukti($sinta, $path, $userSinta);

    $this->actingAs(User::factory()->create())
        ->get(route('pembayaran.bukti', [$grup, $pembayaran]))
        ->assertNotFound();
});
