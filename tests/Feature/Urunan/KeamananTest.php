<?php

use App\Enums\CaraBagi;
use App\Models\Grup;
use App\Models\Tagihan;
use App\Models\User;
use App\Notifications\TagihanBaru;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

test('setiap respons membawa header keamanan dasar', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});

test('ganti bahasa tidak mengarahkan ke situs lain', function () {
    $this->withHeader('referer', 'https://situs-jahat.example/tiru')
        ->get(route('bahasa.ganti', 'en'))
        ->assertRedirect(route('home'));
});

test('halaman yang tidak ada menampilkan halaman 404 Udunan', function () {
    $this->get('/halaman-yang-tidak-ada')->assertNotFound()->assertSee('Halaman tidak ditemukan');
});

test('tanggal tagihan diisi dengan format HH/BB/TTTT', function () {
    $user = User::factory()->create();
    $grup = Grup::buatUntuk($user, 'Kos');
    $grup->anggota()->create(['nama' => 'Raka']);

    $this->actingAs($user);

    Livewire::test('pages::tagihan.form', ['grup' => $grup])
        ->assertSet('tanggal', now()->format('d/m/Y'))
        ->set('judul', 'Listrik')
        ->set('tanggal', '2026-11-24')
        ->set('total', '100.000')
        ->call('simpan')
        ->assertHasErrors(['tanggal' => 'date_format'])
        ->set('tanggal', '31/12/1999')
        ->call('simpan')
        ->assertHasErrors(['tanggal' => 'after_or_equal'])
        ->set('tanggal', '05/11/2026')
        ->call('simpan')
        ->assertHasNoErrors();

    expect(Tagihan::first()->tanggal->toDateString())->toBe('2026-11-05');
});

test('form edit menampilkan tanggal dalam format HH/BB/TTTT', function () {
    $user = User::factory()->create();
    $grup = Grup::buatUntuk($user, 'Kos');

    $tagihan = Tagihan::catat($grup, [
        'judul' => 'Air',
        'tanggal' => '2026-03-09',
        'dibayar_oleh' => $grup->anggota()->first()->id,
        'cara_bagi' => CaraBagi::Rata,
        'total' => 50000,
        'peserta' => $grup->anggota()->pluck('id')->all(),
        'item' => [],
    ]);

    $this->actingAs($user);

    Livewire::test('pages::tagihan.form', ['grup' => $grup, 'tagihan' => $tagihan])->assertSet('tanggal', '09/03/2026');
});

test('bukti bayar berupa SVG ditolak', function () {
    $userDwan = User::factory()->create();
    $userSinta = User::factory()->create();
    $grup = Grup::buatUntuk($userDwan, 'Makan');
    $grup->anggota()->create(['nama' => 'Sinta', 'user_id' => $userSinta->id]);

    $tagihan = Tagihan::catat($grup, [
        'judul' => 'Makan',
        'tanggal' => now()->toDateString(),
        'dibayar_oleh' => $grup->anggota()->where('user_id', $userDwan->id)->value('id'),
        'cara_bagi' => CaraBagi::Rata,
        'total' => 100000,
        'peserta' => $grup->anggota()->pluck('id')->all(),
        'item' => [],
    ]);

    $this->actingAs($userSinta);

    Livewire::test('pages::tagihan.show', ['grup' => $grup, 'tagihan' => $tagihan])
        ->set('bukti', UploadedFile::fake()->createWithContent('bukti.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'))
        ->call('kirimBukti')
        ->assertHasErrors('bukti');

    expect($tagihan->pembayaran()->count())->toBe(0);
});

test('membuka notifikasi tagihan yang sudah dihapus tidak berujung error', function () {
    $user = User::factory()->create();
    $grup = Grup::buatUntuk($user, 'Kos');
    $tagihan = Tagihan::factory()->create(['grup_id' => $grup->id, 'dibayar_oleh' => $grup->anggota()->first()->id]);

    $user->notify(new TagihanBaru($tagihan, 10000));
    $tagihan->delete();

    $this->actingAs($user);

    Livewire::test('pages::notifikasi.index')
        ->call('buka', $user->notifications()->first()->id)
        ->assertNoRedirect();
});

test('membuat grup dibatasi supaya tidak bisa di-spam', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    foreach (range(1, 12) as $urutan) {
        Livewire::test('pages::grup.index')->set('nama', 'Grup '.$urutan)->call('buatGrup');
    }

    expect(Grup::count())->toBe(10);
});
