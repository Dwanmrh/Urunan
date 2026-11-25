<?php

use App\Models\Grup;
use App\Models\Tagihan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    config(['urunan.admin_emails' => ['admin@urunan.test']]);
});

test('kunjungan dicatat sekali per pengunjung per hari', function () {
    $this->get(route('home'))->assertOk();
    $this->get(route('home'))->assertOk();
    $this->get(route('login'))->assertOk();

    expect(DB::table('kunjungan')->count())->toBe(1);
    expect(DB::table('kunjungan')->first()->user_id)->toBeNull();
});

test('kunjungan pengguna yang login dicatat dengan id-nya', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertOk();

    expect(DB::table('kunjungan')->where('user_id', $user->id)->count())->toBe(1);
});

test('hanya admin yang bisa membuka halaman statistik', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('statistik.index'))
        ->assertForbidden();

    $this->actingAs(User::factory()->create(['email' => 'admin@urunan.test']))
        ->get(route('statistik.index'))
        ->assertOk()
        ->assertSee('Statistik Urunan');
});

test('halaman statistik menghitung yang sedang online dari session aktif', function () {
    $admin = User::factory()->create(['email' => 'admin@urunan.test']);
    $lain = User::factory()->create();

    $sesi = fn (string $id, ?int $idUser, int $waktu): array => [
        'id' => $id,
        'user_id' => $idUser,
        'ip_address' => null,
        'user_agent' => null,
        'payload' => '',
        'last_activity' => $waktu,
    ];

    DB::table('sessions')->insert([
        $sesi('a', $admin->id, now()->getTimestamp()),
        $sesi('b', $lain->id, now()->subMinute()->getTimestamp()),
        $sesi('c', null, now()->getTimestamp()),
        $sesi('d', null, now()->subHour()->getTimestamp()),
    ]);

    $this->actingAs($admin);

    expect(Livewire::test('pages::statistik.index')->instance()->online)
        ->toBe(['pengunjung' => 3, 'pengguna' => 2]);
});

test('statistik menghitung tingkat pelunasan dan pemakaian fitur', function () {
    $admin = User::factory()->create(['email' => 'admin@urunan.test', 'info_bayar' => [['jenis' => 'BCA', 'nomor' => '123456', 'atas_nama' => 'Admin']]]);
    $grup = Grup::buatUntuk($admin, 'Kos');
    $raka = $grup->anggota()->create(['nama' => 'Raka']);
    $grup->anggota()->create(['nama' => 'Bima']);

    $this->actingAs($admin);
    Livewire::test('pages::tagihan.form', ['grup' => $grup])->set('judul', 'Wifi')->set('total', '90.000')->set('ulangi', true)->call('simpan');
    Tagihan::sole()->tandaiLunas($raka, $admin);

    $halaman = Livewire::test('pages::statistik.index');

    expect($halaman->instance()->tingkatPelunasan)->toBe(50)
        ->and($halaman->instance()->fitur)->toBe(['rutinAktif' => 1, 'isiRekening' => 1, 'grupAktif' => 1]);

    $halaman->assertSee(__('Pemakaian fitur'))->assertSee('Kos');
});

test('menu profil tampil dengan pintasan dan tombol keluar', function () {
    $this->actingAs(User::factory()->create(['name' => 'Dwan']))
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(__('Info pembayaran'))
        ->assertSee(__('Belum diisi'))
        ->assertSee('data-test="logout-button"', false);
});
