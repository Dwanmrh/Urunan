<?php

namespace App\Providers;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\DevCommands;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureEmails();

        Gate::define('lihat-statistik', fn (User $user): bool => in_array(strtolower($user->email), config('urunan.admin_emails'), true));

        if (filled(config('services.telegram.token'))) {
            DevCommands::artisan('telegram:dengarkan', 'telegram')->blue();
        }
    }

    /**
     * Isi email reset password dan verifikasi dengan gaya Udunan.
     * Bahasanya mengikuti pilihan bahasa penerima.
     */
    protected function configureEmails(): void
    {
        ResetPassword::toMailUsing(function (User $user, string $token): MailMessage {
            $url = url(route('password.reset', [
                'token' => $token,
                'email' => $user->getEmailForPasswordReset(),
            ], false));

            return (new MailMessage)
                ->subject(__('Reset password akun Udunan'))
                ->greeting(__('Halo, :nama!', ['nama' => $user->name]))
                ->line(__('Ada permintaan untuk mengganti password akun Udunan-mu. Tekan tombol di bawah untuk membuat password baru.'))
                ->action(__('Buat password baru'), $url)
                ->line(__('Link ini berlaku :menit menit.', ['menit' => config('auth.passwords.'.config('auth.defaults.passwords').'.expire')]))
                ->line(__('Kalau kamu tidak meminta ini, abaikan saja email ini. Password-mu tetap aman.'));
        });

        VerifyEmail::toMailUsing(function (User $user, string $url): MailMessage {
            return (new MailMessage)
                ->subject(__('Verifikasi email akun Udunan'))
                ->greeting(__('Halo, :nama!', ['nama' => $user->name]))
                ->line(__('Terima kasih sudah mendaftar di Udunan. Tekan tombol di bawah untuk memverifikasi emailmu, lalu kamu bisa langsung mulai patungan.'))
                ->action(__('Verifikasi email'), $url)
                ->line(__('Kalau kamu tidak merasa mendaftar di Udunan, abaikan saja email ini.'));
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        URL::forceHttps(app()->isProduction());

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
