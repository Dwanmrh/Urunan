<?php

use App\Models\Grup;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;

test('user yang belum verifikasi email diarahkan ke halaman cek email', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('verification.notice'));

    $this->actingAs($user)->get(route('verification.notice'))
        ->assertOk()
        ->assertSee('Cek emailmu')
        ->assertSee($user->email);
});

test('pendaftar baru dikirimi email verifikasi', function () {
    Notification::fake();

    $this->post(route('register.store'), [
        'name' => 'Sinta',
        'email' => 'sinta@contoh.test',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    Notification::assertSentTo(User::where('email', 'sinta@contoh.test')->first(), VerifyEmail::class);
});

test('email reset password memakai gaya dan bahasa Urunan', function () {
    $user = User::factory()->create(['name' => 'Dwan']);

    $html = (string) (new ResetPassword('token-uji'))->toMail($user)->render();

    expect($html)
        ->toContain('Halo, Dwan!')
        ->toContain('Buat password baru')
        ->toContain('Link ini berlaku 60 menit.')
        ->toContain('token-uji')
        ->toContain('#4f46e5')
        ->not->toContain('laravel.com/img');
});

test('email mengikuti bahasa pilihan penerima', function () {
    Notification::fake();

    $user = User::factory()->create(['name' => 'Dwan']);
    $user->forceFill(['locale' => 'en'])->save();

    $user->sendPasswordResetNotification('token-uji');

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notifikasi, array $saluran, User $penerima, string $bahasa) {
        return $bahasa === 'en';
    });
});

test('halaman lupa password tampil dengan gaya baru', function () {
    $this->get(route('password.request'))
        ->assertOk()
        ->assertSee('Lupa password')
        ->assertSee('Kirim link reset password');
});

test('beranda menampilkan saldo bersih dan aktivitas terbaru', function () {
    $user = User::factory()->create();
    Grup::buatUntuk($user, 'Kos');

    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Saldo bersih')
        ->assertSee('Aktivitas terbaru');
});
