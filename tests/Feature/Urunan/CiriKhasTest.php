<?php

use App\Actions\Urunan\PesanTagih;
use App\Actions\Urunan\RaporGrup;
use App\Enums\WarnaGrup;
use App\Models\Grup;
use App\Models\Pembayaran;
use App\Models\Tagihan;
use App\Models\TagihanRutin;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * Grup berisi Dwan (pembuat), Sinta (punya akun), dan Raka (tanpa akun), plus satu tagihan Rp 90.000 dari Dwan.
 *
 * @return array{userDwan: User, userSinta: User, grup: Grup, tagihan: Tagihan}
 */
function grupDenganTagihan(): array
{
    $userDwan = User::factory()->create(['name' => 'Dwan']);
    $userSinta = User::factory()->create(['name' => 'Sinta']);
    $grup = Grup::buatUntuk($userDwan, 'Nongkrong');
    $grup->anggota()->create(['nama' => 'Sinta', 'user_id' => $userSinta->id]);
    $grup->anggota()->create(['nama' => 'Raka']);

    test()->actingAs($userDwan);

    Livewire::test('pages::tagihan.form', ['grup' => $grup])
        ->set('judul', 'Kopi')
        ->set('total', '90.000')
        ->call('simpan');

    return ['userDwan' => $userDwan, 'userSinta' => $userSinta, 'grup' => $grup, 'tagihan' => Tagihan::sole()];
}

test('grup dibuat dengan emoji dan warna pilihan', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::grup.index')
        ->set('nama', 'Spotify Family')
        ->set('emoji', '🎵')
        ->set('warna', 'pink')
        ->call('buatGrup')
        ->assertHasNoErrors();

    $grup = Grup::sole();
    expect($grup->emoji)->toBe('🎵')->and($grup->warna)->toBe(WarnaGrup::Pink);
});

test('emoji dan warna di luar daftar ditolak', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::grup.index')
        ->set('nama', 'Iseng')
        ->set('emoji', '<script>')
        ->set('warna', 'merah')
        ->call('buatGrup')
        ->assertHasErrors(['emoji', 'warna']);
});

test('pembuat grup bisa mengubah emoji dan warna', function () {
    ['userDwan' => $userDwan, 'grup' => $grup] = grupDenganTagihan();

    Livewire::test('pages::grup.show', ['grup' => $grup])
        ->set('nama', 'Nongkrong Jumat')
        ->set('emoji', '☕')
        ->set('warna', 'hijau')
        ->call('ubahNamaGrup')
        ->assertHasNoErrors();

    expect($grup->fresh())->nama->toBe('Nongkrong Jumat')->emoji->toBe('☕')->warna->toBe(WarnaGrup::Hijau);
});

test('anggota yang sudah tercatat keluar grup jadi anggota tanpa akun', function () {
    ['userSinta' => $userSinta, 'grup' => $grup] = grupDenganTagihan();

    $this->actingAs($userSinta);
    Livewire::test('pages::grup.show', ['grup' => $grup])
        ->call('keluarGrup')
        ->assertRedirect(route('grup.index'));

    expect($grup->punyaAnggota($userSinta))->toBeFalse()
        ->and($grup->anggota()->where('nama', 'Sinta')->whereNull('user_id')->exists())->toBeTrue();
});

test('anggota yang belum tercatat keluar grup langsung terhapus, pembuat tidak bisa keluar', function () {
    $userDwan = User::factory()->create();
    $userBaru = User::factory()->create(['name' => 'Baru']);
    $grup = Grup::buatUntuk($userDwan, 'Trip');
    $grup->anggota()->create(['nama' => 'Baru', 'user_id' => $userBaru->id]);

    $this->actingAs($userBaru);
    Livewire::test('pages::grup.show', ['grup' => $grup])->call('keluarGrup');
    expect($grup->anggota()->where('nama', 'Baru')->exists())->toBeFalse();

    $this->actingAs($userDwan);
    Livewire::test('pages::grup.show', ['grup' => $grup])->call('keluarGrup')->assertForbidden();
});

test('tagihan rutin bisa diedit dan berlaku untuk tagihan berikutnya', function () {
    ['grup' => $grup] = grupDenganTagihan();

    Livewire::test('pages::tagihan.form', ['grup' => $grup])
        ->set('judul', 'Wifi')
        ->set('total', '300.000')
        ->set('ulangi', true)
        ->call('simpan');

    $rutin = TagihanRutin::sole();
    $idSinta = $grup->anggota()->where('nama', 'Sinta')->value('id');
    $idDwan = $grup->anggota()->where('nama', 'Dwan')->value('id');

    Livewire::test('pages::grup.show', ['grup' => $grup])
        ->call('ubahRutin', $rutin->id)
        ->assertSet('judulRutin', 'Wifi')
        ->set('judulRutin', 'Wifi 50 Mbps')
        ->set('totalRutin', '350.000')
        ->set('pesertaRutin', [(string) $idDwan, (string) $idSinta])
        ->call('simpanRutin')
        ->assertHasNoErrors();

    expect($rutin->fresh())
        ->judul->toBe('Wifi 50 Mbps')
        ->total->toBe(350000)
        ->peserta->toBe([$idDwan, $idSinta]);
});

test('pengirim bisa menarik bukti yang masih menunggu', function () {
    Storage::fake('local');
    ['userSinta' => $userSinta, 'grup' => $grup, 'tagihan' => $tagihan] = grupDenganTagihan();

    $this->actingAs($userSinta);
    Livewire::test('pages::tagihan.show', ['grup' => $grup, 'tagihan' => $tagihan])
        ->set('bukti', UploadedFile::fake()->image('bukti.jpg'))
        ->call('kirimBukti')
        ->call('batalkanBukti');

    expect(Pembayaran::count())->toBe(0)
        ->and(Storage::disk('local')->allFiles('bukti-pembayaran'))->toBe([]);
});

test('pembayar bisa membatalkan pelunasan yang salah, anggota lain tidak', function () {
    ['userDwan' => $userDwan, 'userSinta' => $userSinta, 'grup' => $grup, 'tagihan' => $tagihan] = grupDenganTagihan();
    $raka = $grup->anggota()->where('nama', 'Raka')->first();
    $tagihan->tandaiLunas($raka, $userDwan);

    $this->actingAs($userSinta);
    Livewire::test('pages::tagihan.show', ['grup' => $grup, 'tagihan' => $tagihan])
        ->call('batalkanPelunasan', $raka->id)
        ->assertForbidden();

    $this->actingAs($userDwan);
    Livewire::test('pages::tagihan.show', ['grup' => $grup, 'tagihan' => $tagihan])
        ->call('batalkanPelunasan', $raka->id);

    expect($grup->saldo()[$raka->id])->toBe(-30000);
});

test('info pembayaran bisa ditambah, diubah, dihapus, maksimal tiga', function () {
    $user = User::factory()->create(['name' => 'Dwan']);
    $this->actingAs($user);

    $halaman = Livewire::test('pages::settings.pembayaran');

    foreach (['BCA', 'GoPay', 'DANA'] as $jenis) {
        $halaman->call('tambah')->call('pilihJenis', $jenis)->set('nomor', '0812-3456-7890')->call('simpan')->assertHasNoErrors();
    }

    $halaman->call('tambah');
    expect($user->fresh()->infoBayar())->toHaveCount(3);

    $halaman->call('ubah', 1)->set('nomor', '089912345678')->call('simpan')->assertHasNoErrors();
    expect($user->fresh()->infoBayar()[1]['nomor'])->toBe('089912345678');

    $halaman->call('hapus', 0);
    expect(array_column($user->fresh()->infoBayar(), 'jenis'))->toBe(['GoPay', 'DANA']);

    $halaman->call('tambah')->call('pilihJenis', 'BRI')->set('nomor', 'abc')->call('simpan')->assertHasErrors('nomor');
    $halaman->call('pilihJenis', 'OVO')->set('nomor', '12345678')->call('simpan')->assertHasErrors('nomor');
    $halaman->call('pilihJenis', 'BRI')->set('nomor', '1234567890')->set('atasNama', '')->call('simpan')->assertHasErrors('atasNama');
    $halaman->call('pilihLainnya')->set('jenis', 'Bank Jatim')->set('nomor', '1234567890')->set('atasNama', 'Dwan')->call('simpan')->assertHasNoErrors();
    expect(array_column($user->fresh()->infoBayar(), 'jenis'))->toBe(['GoPay', 'DANA', 'Bank Jatim']);
    $halaman->call('tambah');
    $halaman->call('pilihJenis', 'Bank Palsu')->assertStatus(422);
});

test('yang berutang melihat rekening pembayar di halaman tagihan', function () {
    ['userDwan' => $userDwan, 'userSinta' => $userSinta, 'grup' => $grup, 'tagihan' => $tagihan] = grupDenganTagihan();
    $userDwan->forceFill(['info_bayar' => [['jenis' => 'BCA', 'nomor' => '1234567890', 'atas_nama' => 'Dwan']]])->save();

    $this->actingAs($userSinta);
    $this->get(route('tagihan.show', [$grup, $tagihan]))
        ->assertOk()
        ->assertSee('1234567890')
        ->assertSee(__('Kirim bukti bayar'));
});

test('pesan tagih berisi yang belum lunas, rekening, dan link untuk tiap gaya', function () {
    ['userDwan' => $userDwan, 'tagihan' => $tagihan] = grupDenganTagihan();
    $userDwan->forceFill(['info_bayar' => [['jenis' => 'DANA', 'nomor' => '0812', 'atas_nama' => '']]])->save();

    foreach (PesanTagih::GAYA as $gaya) {
        $pesan = PesanTagih::buat($tagihan->fresh(), $gaya);

        expect($pesan)
            ->toContain('Sinta: Rp 30.000')
            ->toContain('Raka: Rp 30.000')
            ->toContain('DANA 0812')
            ->toContain(route('tagihan.show', [$tagihan->grup_id, $tagihan->id]))
            ->not->toContain('Dwan: Rp');
    }

    expect(PesanTagih::linkWhatsapp("a b\nc"))->toBe('https://wa.me/?text=a%20b%0Ac');
});

test('rapor grup memberi lencana penalang, tercepat, dan paling ditunggu', function () {
    ['userDwan' => $userDwan, 'grup' => $grup, 'tagihan' => $tagihan] = grupDenganTagihan();
    $sinta = $grup->anggota()->where('nama', 'Sinta')->first();
    $tagihan->tandaiLunas($sinta, $userDwan);

    $rapor = collect(RaporGrup::untuk($grup))->keyBy('kunci');

    expect($rapor['dompet-darurat']['nama'])->toBe('Dwan')
        ->and($rapor['gercep']['nama'])->toBe('Sinta')
        ->and($rapor['ditunggu']['nama'])->toBe('Raka')
        ->and($rapor->has('bebas-utang'))->toBeFalse();

    $this->get(route('grup.show', $grup))->assertOk()->assertSee(__('Si Paling Gercep'))->assertSee('3 anggota');
});

test('konfirmasi pembayaran memicu konfeti', function () {
    ['userDwan' => $userDwan, 'grup' => $grup, 'tagihan' => $tagihan] = grupDenganTagihan();
    $raka = $grup->anggota()->where('nama', 'Raka')->first();

    Livewire::test('pages::tagihan.show', ['grup' => $grup, 'tagihan' => $tagihan])
        ->call('tandaiLunas', $raka->id)
        ->assertDispatched('konfeti');
});

test('setiap warna masuk tepat satu kelompok, dan warna terang memakai teks gelap', function () {
    $dikelompokkan = collect(WarnaGrup::kelompok())->flatten();

    expect($dikelompokkan)->toHaveCount(count(WarnaGrup::cases()))
        ->and($dikelompokkan->unique())->toHaveCount(count(WarnaGrup::cases()));

    foreach (WarnaGrup::cases() as $warna) {
        expect($warna->gradasi())->not->toBeEmpty()
            ->and($warna->lembut())->not->toBeEmpty()
            ->and($warna->teks())->toBe($warna->terang() ? 'text-zinc-900' : 'text-white');
    }

    expect(WarnaGrup::Stroberi->terang())->toBeTrue()
        ->and(WarnaGrup::Navy->terang())->toBeFalse()
        ->and(WarnaGrup::Maroon->terang())->toBeFalse();
});

test('emoji lama tetap valid dan grup bisa memakai warna kalem', function () {
    foreach (['🎉', '🍜', '🍕', '☕', '🏠', '📶', '🎬', '🎵', '✈️', '🏖️', '⛺', '⚽', '🏸', '🎮', '🎂', '🛒', '🚗', '📚', '💼', '🕌'] as $emoji) {
        expect(WarnaGrup::emoji())->toContain($emoji);
    }

    $this->actingAs(User::factory()->create());

    Livewire::test('pages::grup.index')
        ->set('nama', 'Kopi Senja')
        ->set('emoji', '☕')
        ->set('warna', 'kopi')
        ->call('buatGrup')
        ->assertHasNoErrors();

    expect(Grup::sole()->warna)->toBe(WarnaGrup::Kopi);
});
