<x-layouts::auth :title="__('Verifikasi email')">
    <div class="flex flex-col gap-6">
        <x-auth-header
            ikon="envelope"
            :title="__('Cek emailmu')"
            :description="__('Kami sudah mengirim link verifikasi ke :email. Buka email itu lalu tekan tombol Verifikasi Email untuk mulai memakai Urunan.', ['email' => auth()->user()->email])"
        />

        @if (session('status') === 'verification-link-sent')
            <x-auth-session-status :status="__('Link verifikasi baru sudah dikirim. Cek juga folder Spam atau Promosi.')" />
        @endif

        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <flux:button type="submit" variant="primary" class="w-full" data-test="kirim-ulang-verifikasi">
                {{ __('Kirim ulang email verifikasi') }}
            </flux:button>
        </form>

        <div class="rounded-xl bg-zinc-50 px-4 py-3 text-xs leading-relaxed text-zinc-500 dark:bg-white/5">
            {{ __('Tidak menemukan emailnya? Tunggu satu sampai dua menit, cek folder Spam atau Promosi, atau masuk dengan Google supaya tidak perlu verifikasi.') }}
        </div>

        <form method="POST" action="{{ route('logout') }}" class="text-center">
            @csrf
            <button type="submit" class="text-sm text-zinc-500 underline-offset-4 hover:text-zinc-800 hover:underline dark:hover:text-white">
                {{ __('Keluar dan pakai email lain') }}
            </button>
        </form>
    </div>
</x-layouts::auth>
