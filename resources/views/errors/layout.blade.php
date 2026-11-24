<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex">
        <title>@yield('kode') · {{ config('app.name', 'Urunan') }}</title>
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        {{-- Gaya ditulis langsung supaya halaman error tetap tampil walau aset Vite bermasalah. --}}
        <style>
            *{box-sizing:border-box}
            body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;font-family:ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif;background:#09090b;color:#d4d4d8}
            .kartu{max-width:420px;text-align:center}
            .logo{width:56px;height:56px;margin:0 auto 24px;border-radius:14px;background:#4f46e5;display:grid;place-items:center}
            .kode{font-size:14px;font-weight:600;letter-spacing:.08em;color:#818cf8}
            h1{margin:8px 0 12px;font-size:28px;line-height:1.2;color:#fff}
            p{margin:0;line-height:1.6}
            .tombol{display:inline-block;margin-top:28px;padding:12px 20px;border-radius:12px;background:#4f46e5;color:#fff;font-weight:600;text-decoration:none}
            .tombol:hover{background:#6366f1}
            @media (prefers-color-scheme: light){body{background:#fafafa;color:#52525b}h1{color:#09090b}}
        </style>
    </head>
    <body>
        <main class="kartu">
            <div class="logo" aria-hidden="true">
                <svg width="30" height="30" viewBox="0 0 40 40" fill="#fff"><path d="M18 22V6A16 16 0 1 0 34 22Z"/><path d="M22 18V2a16 16 0 0 1 16 16Z"/></svg>
            </div>
            <div class="kode">@yield('kode')</div>
            <h1>@yield('judul')</h1>
            <p>@yield('pesan')</p>
            <a class="tombol" href="{{ url('/') }}">{{ __('Kembali ke beranda') }}</a>
        </main>
    </body>
</html>
