<?php

use App\Enums\CaraBagi;
use App\Models\Grup;
use App\Models\Tagihan;
use App\Models\User;

test('user yang belum punya grup diajak membuat grup pertama', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Mulai patungan pertamamu');
});

test('beranda menampilkan tagihan yang perlu dibayar dan bukti yang menunggu konfirmasi', function () {
    $userDwan = User::factory()->create(['name' => 'Dwan']);
    $userSinta = User::factory()->create(['name' => 'Sinta']);
    $grup = Grup::buatUntuk($userDwan, 'Ngegrill');
    $dwan = $grup->anggota()->first();
    $sinta = $grup->anggota()->create(['nama' => 'Sinta', 'user_id' => $userSinta->id]);

    $tagihan = Tagihan::catat($grup, [
        'judul' => 'Daging dan arang',
        'tanggal' => now()->toDateString(),
        'dibayar_oleh' => $dwan->id,
        'cara_bagi' => CaraBagi::Rata,
        'total' => 100000,
        'peserta' => [$dwan->id, $sinta->id],
        'item' => [],
    ]);

    $this->actingAs($userSinta)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Yang perlu kamu bayar')
        ->assertSee('Daging dan arang')
        ->assertSee('Rp 50.000');

    $tagihan->kirimBukti($sinta, 'bukti-pembayaran/contoh.jpg', $userSinta);

    $this->actingAs($userDwan)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Bukti bayar menunggu konfirmasimu')
        ->assertSee('Sinta mengirim bukti');
});
