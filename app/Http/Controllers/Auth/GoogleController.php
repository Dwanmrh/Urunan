<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Throwable;

class GoogleController extends Controller
{
    /**
     * Apakah login Google sudah diatur di .env.
     */
    public static function aktif(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }

    /**
     * Arahkan ke halaman pilih akun Google.
     */
    public function redirect(): SymfonyRedirectResponse
    {
        abort_unless(self::aktif(), 404);

        return Socialite::driver('google')->redirect();
    }

    /**
     * Terima jawaban Google, lalu masukkan atau daftarkan user.
     *
     * Akun yang emailnya sudah terdaftar disambungkan, bukan dibuat ulang.
     * Akun yang memakai 2FA tetap diminta kode 2FA.
     */
    public function callback(Request $request): RedirectResponse
    {
        abort_unless(self::aktif(), 404);

        try {
            $akunGoogle = Socialite::driver('google')->user();
        } catch (Throwable) {
            return $this->gagal(__('Masuk dengan Google dibatalkan atau gagal. Coba lagi.'));
        }

        $email = Str::lower((string) $akunGoogle->getEmail());
        $emailTerverifikasi = (bool) ($akunGoogle->user['email_verified'] ?? false);

        if ($email === '' || ! $emailTerverifikasi) {
            return $this->gagal(__('Email akun Google ini belum terverifikasi. Pakai akun Google lain atau daftar dengan email.'));
        }

        $user = User::where('google_id', $akunGoogle->getId())->first()
            ?? User::where('email', $email)->first();

        if ($user !== null && $user->google_id === null && $user->email_verified_at === null) {
            $this->amankanAkunBelumTerverifikasi($user);
        }

        if ($user === null) {
            $user = User::create([
                'name' => $akunGoogle->getName() ?: Str::before($email, '@'),
                'email' => $email,
                'password' => Str::random(40),
            ]);
        }

        $user->forceFill([
            'google_id' => $akunGoogle->getId(),
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        if ($user->hasEnabledTwoFactorAuthentication()) {
            $request->session()->put([
                'login.id' => $user->getKey(),
                'login.remember' => true,
            ]);

            return redirect()->route('two-factor.login');
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(config('fortify.home'));
    }

    /**
     * Kembali ke halaman login dengan pesan.
     */
    private function gagal(string $pesan): RedirectResponse
    {
        return redirect()->route('login')->with('status', $pesan);
    }

    /**
     * Akun dengan email ini pernah didaftarkan tapi emailnya belum pernah dibuktikan,
     * jadi pendaftarnya belum tentu pemilik email. Pemilik asli kini terbukti lewat Google,
     * maka cara masuk lama dicabut: kata sandi diganti, 2FA dan passkey dihapus, sesi lain diakhiri.
     */
    private function amankanAkunBelumTerverifikasi(User $user): void
    {
        $user->forceFill([
            'password' => Str::random(40),
            'remember_token' => null,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        DB::table('passkeys')->where('user_id', $user->id)->delete();

        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }
    }
}
