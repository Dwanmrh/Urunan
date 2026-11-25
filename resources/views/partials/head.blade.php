<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />

<title>
    {{ filled($title ?? null) ? __($title).' - '.config('app.name', 'Laravel') : config('app.name', 'Laravel') }}
</title>

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

{{-- Supaya bisa dipasang seperti aplikasi (PWA) di HP, tablet, dan laptop. --}}
<link rel="manifest" href="/manifest.webmanifest">
<meta name="theme-color" content="#4f46e5" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#18181b" media="(prefers-color-scheme: dark)">
<meta name="application-name" content="Udunan">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Udunan">
<meta name="apple-mobile-web-app-status-bar-style" content="default">

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
