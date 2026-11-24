<?php

use App\Enums\CaraBagi;
use App\Models\Grup;
use App\Models\Tagihan;
use App\Models\User;
use App\Notifications\BuktiBayarDitolak;
use App\Notifications\BuktiBayarMasuk;
use App\Notifications\PembayaranDikonfirmasi;
use App\Notifications\TagihanBaru;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * Grup berisi Dwan (pembuat), Sinta (punya akun), dan Raka (tanpa akun).
 *
 * @return array{userDwan: User, userSinta: User, grup: Grup}
 */
function grupDenganDuaAkun(): array
{
    $userDwan = User::factory()->create(['name' => 'Dwan']);
    $userSinta = User::factory()->create(['name' => 'Sinta']);
    $grup = Grup::buatUntuk($userDwan, 'Makan malam');
    $grup->anggota()->create(['nama' => 'Sinta', 'user_id' => $userSinta->id]);
    $grup->anggota()->create(['nama' => 'Raka']);

    return compact('userDwan', 'userSinta', 'grup');
}

test('anggota yang punya akun dikabari saat ada tagihan baru, pencatatnya tidak', function () {
    Notification::fake();
    ['userDwan' => $userDwan, 'userSinta' => $userSinta, 'grup' => $grup] = grupDenganDuaAkun();

    $this->actingAs($userDwan);

    Livewire::test('pages::tagihan.form', ['grup' => $grup])
        ->set('judul', 'Makan malam')
        ->set('total', '90.000')
        ->call('simpan')
        ->assertHasNoErrors();

    Notification::assertSentTo($userSinta, TagihanBaru::class, fn (TagihanBaru $notifikasi): bool => $notifikasi->jumlah === 30000);
    Notification::assertNotSentTo($userDwan, TagihanBaru::class);
});

test('pembayar dikabari saat bukti masuk, pengirim dikabari saat dikonfirmasi atau ditolak', function () {
    Notification::fake();
    Storage::fake('local');
    ['userDwan' => $userDwan, 'userSinta' => $userSinta, 'grup' => $grup] = grupDenganDuaAkun();
    $dwan = $grup->anggota()->where('user_id', $userDwan->id)->first();
    $sinta = $grup->anggota()->where('user_id', $userSinta->id)->first();

    $tagihan = Tagihan::catat($grup, [
        'judul' => 'Makan malam',
        'tanggal' => now()->toDateString(),
        'dibayar_oleh' => $dwan->id,
        'cara_bagi' => CaraBagi::Rata,
        'total' => 90000,
        'peserta' => $grup->anggota()->orderBy('id')->pluck('id')->all(),
        'item' => [],
    ]);

    $path = UploadedFile::fake()->create('bukti.jpg', 100, 'image/jpeg')->store('bukti-pembayaran', 'local');
    $pembayaran = $tagihan->kirimBukti($sinta, $path, $userSinta);

    Notification::assertSentTo($userDwan, BuktiBayarMasuk::class);

    $tagihan->tolak($pembayaran);

    Notification::assertSentTo($userSinta, BuktiBayarDitolak::class);

    $pembayaran = $tagihan->kirimBukti($sinta, $path, $userSinta);
    $tagihan->konfirmasi($pembayaran);

    Notification::assertSentTo($userSinta, PembayaranDikonfirmasi::class);
});

test('halaman notifikasi menampilkan pesan, membuka tagihan, dan bisa menandai semua dibaca', function () {
    ['userDwan' => $userDwan, 'userSinta' => $userSinta, 'grup' => $grup] = grupDenganDuaAkun();
    $dwan = $grup->anggota()->where('user_id', $userDwan->id)->first();

    $tagihan = Tagihan::catat($grup, [
        'judul' => 'Makan malam',
        'tanggal' => now()->toDateString(),
        'dibayar_oleh' => $dwan->id,
        'cara_bagi' => CaraBagi::Rata,
        'total' => 90000,
        'peserta' => $grup->anggota()->orderBy('id')->pluck('id')->all(),
        'item' => [],
    ], $userDwan);

    $userSinta->notify(new TagihanBaru($tagihan, 30000));

    expect($userSinta->unreadNotifications()->count())->toBe(2);

    $this->actingAs($userSinta);

    $this->get(route('notifikasi.index'))->assertOk()->assertSee('Bagianmu Rp 30.000');

    $pertama = $userSinta->notifications()->latest()->first();

    Livewire::test('pages::notifikasi.index')
        ->call('buka', $pertama->id)
        ->assertRedirect(route('tagihan.show', [$grup, $tagihan], absolute: false));

    expect($pertama->fresh()->read_at)->not->toBeNull();
    expect($userSinta->unreadNotifications()->count())->toBe(1);

    Livewire::test('pages::notifikasi.index')->call('tandaiSemuaDibaca');

    expect($userSinta->unreadNotifications()->count())->toBe(0);
});

test('user bisa menghapus satu notifikasi miliknya, tapi tidak milik orang lain', function () {
    ['userDwan' => $userDwan, 'userSinta' => $userSinta, 'grup' => $grup] = grupDenganDuaAkun();
    $tagihan = Tagihan::factory()->for($grup)->create();
    $userDwan->notify(new TagihanBaru($tagihan, 10000));
    $userSinta->notify(new TagihanBaru($tagihan, 10000));
    $milikSinta = $userSinta->notifications()->first();

    $this->actingAs($userDwan);

    Livewire::test('pages::notifikasi.index')
        ->call('hapus', $userDwan->notifications()->first()->id)
        ->call('hapus', $milikSinta->id);

    expect($userDwan->notifications()->count())->toBe(0);
    expect($userSinta->notifications()->count())->toBe(1);
});

test('hapus yang sudah dibaca menyisakan notifikasi yang belum dibaca', function () {
    ['userDwan' => $userDwan, 'grup' => $grup] = grupDenganDuaAkun();
    $tagihan = Tagihan::factory()->for($grup)->create();
    $userDwan->notify(new TagihanBaru($tagihan, 10000));
    $userDwan->notify(new TagihanBaru($tagihan, 20000));
    $userDwan->notifications()->first()->markAsRead();

    $this->actingAs($userDwan);

    Livewire::test('pages::notifikasi.index')
        ->assertSee(__('Hapus yang sudah dibaca'))
        ->call('hapusYangSudahDibaca');

    expect($userDwan->notifications()->count())->toBe(1);
    expect($userDwan->unreadNotifications()->count())->toBe(1);
});
