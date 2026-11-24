<?php

use App\Enums\Frekuensi;
use App\Models\Grup;
use App\Models\Tagihan;
use App\Models\TagihanRutin;
use App\Models\User;
use App\Notifications\PengingatBayar;
use App\Notifications\TagihanBaru;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

/**
 * Grup kos berisi Dwan (pembuat), Sinta (punya akun), dan Raka (tanpa akun).
 *
 * @return array{userDwan: User, userSinta: User, grup: Grup}
 */
function grupKos(): array
{
    $userDwan = User::factory()->create(['name' => 'Dwan']);
    $userSinta = User::factory()->create(['name' => 'Sinta']);
    $grup = Grup::buatUntuk($userDwan, 'Kos Melati');
    $grup->anggota()->create(['nama' => 'Sinta', 'user_id' => $userSinta->id]);
    $grup->anggota()->create(['nama' => 'Raka']);

    return compact('userDwan', 'userSinta', 'grup');
}

test('tagihan bagi rata bisa dijadikan tagihan rutin bulanan', function () {
    ['userDwan' => $userDwan, 'grup' => $grup] = grupKos();
    $this->actingAs($userDwan);

    Livewire::test('pages::tagihan.form', ['grup' => $grup])
        ->set('judul', 'Wifi kos')
        ->set('tanggal', '31/01/2026')
        ->set('total', '300.000')
        ->set('ulangi', true)
        ->set('frekuensi', 'bulanan')
        ->assertSee('28/02/2026')
        ->call('simpan')
        ->assertHasNoErrors();

    $rutin = TagihanRutin::sole();

    expect($rutin->judul)->toBe('Wifi kos')
        ->and($rutin->total)->toBe(300000)
        ->and($rutin->frekuensi)->toBe(Frekuensi::Bulanan)
        ->and($rutin->jadwal_berikutnya->toDateString())->toBe('2026-02-28')
        ->and($rutin->peserta)->toHaveCount(3)
        ->and(Tagihan::sole()->tagihan_rutin_id)->toBe($rutin->id);
});

test('tagihan per item tidak bisa dijadikan rutin', function () {
    ['userDwan' => $userDwan, 'grup' => $grup] = grupKos();
    $this->actingAs($userDwan);

    Livewire::test('pages::tagihan.form', ['grup' => $grup])
        ->set('judul', 'Makan')
        ->set('caraBagi', 'per_item')
        ->set('item.0.nama', 'Nasi')
        ->set('item.0.harga', '30.000')
        ->set('ulangi', true)
        ->call('simpan')
        ->assertHasNoErrors();

    expect(TagihanRutin::count())->toBe(0);
});

test('perintah harian mencatat tagihan yang jatuh tempo dan mengabari anggota', function () {
    Notification::fake();
    ['userDwan' => $userDwan, 'userSinta' => $userSinta, 'grup' => $grup] = grupKos();
    $this->actingAs($userDwan);

    Livewire::test('pages::tagihan.form', ['grup' => $grup])
        ->set('judul', 'Wifi kos')
        ->set('tanggal', '31/01/2026')
        ->set('total', '300.000')
        ->set('ulangi', true)
        ->call('simpan');

    Carbon::setTestNow('2026-03-31 08:00:00');
    $this->artisan('urunan:tagihan-rutin')->assertSuccessful();

    $tanggal = Tagihan::orderBy('tanggal')->pluck('tanggal')->map->toDateString()->all();
    expect($tanggal)->toBe(['2026-01-31', '2026-02-28', '2026-03-31']);
    expect(TagihanRutin::sole()->jadwal_berikutnya->toDateString())->toBe('2026-04-30');
    Notification::assertSentToTimes($userSinta, TagihanBaru::class, 3);

    $this->artisan('urunan:tagihan-rutin')->assertSuccessful();
    expect(Tagihan::count())->toBe(3);

    Carbon::setTestNow();
});

test('tagihan rutin yang dijeda tidak dicatat dan lanjut tanpa menagih masa jeda', function () {
    ['userDwan' => $userDwan, 'grup' => $grup] = grupKos();
    $this->actingAs($userDwan);

    Livewire::test('pages::tagihan.form', ['grup' => $grup])
        ->set('judul', 'Spotify')
        ->set('tanggal', '01/01/2026')
        ->set('total', '90.000')
        ->set('ulangi', true)
        ->set('frekuensi', 'mingguan')
        ->call('simpan');

    $rutin = TagihanRutin::sole();

    Livewire::test('pages::grup.show', ['grup' => $grup])
        ->assertSee('Spotify')
        ->call('alihkanRutin', $rutin->id);

    Carbon::setTestNow('2026-02-01 08:00:00');
    $this->artisan('urunan:tagihan-rutin');
    expect(Tagihan::count())->toBe(1);

    Livewire::test('pages::grup.show', ['grup' => $grup])->call('alihkanRutin', $rutin->id);
    expect($rutin->fresh()->aktif)->toBeTrue()
        ->and($rutin->fresh()->jadwal_berikutnya->gte(Carbon::parse('2026-02-01')))->toBeTrue();

    Carbon::setTestNow();
});

test('hanya pembuat, pembayar, atau pembuat grup yang bisa menghentikan tagihan rutin', function () {
    ['userDwan' => $userDwan, 'userSinta' => $userSinta, 'grup' => $grup] = grupKos();
    $this->actingAs($userDwan);

    Livewire::test('pages::tagihan.form', ['grup' => $grup])
        ->set('judul', 'Wifi kos')
        ->set('total', '300.000')
        ->set('ulangi', true)
        ->call('simpan');

    $rutin = TagihanRutin::sole();

    $this->actingAs($userSinta);
    Livewire::test('pages::grup.show', ['grup' => $grup])
        ->call('hapusRutin', $rutin->id)
        ->assertForbidden();

    $this->actingAs($userDwan);
    Livewire::test('pages::grup.show', ['grup' => $grup])->call('hapusRutin', $rutin->id);

    expect(TagihanRutin::count())->toBe(0)
        ->and(Tagihan::count())->toBe(1);
});

test('pembayar bisa mengingatkan yang belum bayar, paling sering sekali sehari', function () {
    Notification::fake();
    ['userDwan' => $userDwan, 'userSinta' => $userSinta, 'grup' => $grup] = grupKos();
    $this->actingAs($userDwan);

    Livewire::test('pages::tagihan.form', ['grup' => $grup])
        ->set('judul', 'Listrik')
        ->set('total', '150.000')
        ->call('simpan');

    $tagihan = Tagihan::sole();

    Livewire::test('pages::tagihan.show', ['grup' => $grup, 'tagihan' => $tagihan])
        ->assertSee(__('Ingatkan yang belum bayar'))
        ->call('ingatkan')
        ->call('ingatkan');

    Notification::assertSentToTimes($userSinta, PengingatBayar::class, 1);
    Notification::assertNotSentTo($userDwan, PengingatBayar::class);

    $this->actingAs($userSinta);
    Livewire::test('pages::tagihan.show', ['grup' => $grup, 'tagihan' => $tagihan])
        ->assertDontSee(__('Ingatkan yang belum bayar'))
        ->call('ingatkan')
        ->assertForbidden();
});

test('pengingat otomatis dikirim setelah tiga hari dan tidak ke yang sudah lunas', function () {
    Notification::fake();
    ['userDwan' => $userDwan, 'userSinta' => $userSinta, 'grup' => $grup] = grupKos();
    $this->actingAs($userDwan);

    Livewire::test('pages::tagihan.form', ['grup' => $grup])
        ->set('judul', 'Listrik')
        ->set('tanggal', now()->format('d/m/Y'))
        ->set('total', '150.000')
        ->call('simpan');

    $this->artisan('urunan:ingatkan');
    Notification::assertNotSentTo($userSinta, PengingatBayar::class);

    $this->travel(3)->days();
    $this->artisan('urunan:ingatkan');
    Notification::assertSentToTimes($userSinta, PengingatBayar::class, 1);

    $this->travel(1)->days();
    $this->artisan('urunan:ingatkan');
    Notification::assertSentToTimes($userSinta, PengingatBayar::class, 1);

    $sinta = $grup->anggota()->where('user_id', $userSinta->id)->first();
    Tagihan::sole()->tandaiLunas($sinta, $userDwan);

    $this->travel(7)->days();
    $this->artisan('urunan:ingatkan');
    Notification::assertSentToTimes($userSinta, PengingatBayar::class, 1);
});
