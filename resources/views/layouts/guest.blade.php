<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#0f172a">
        <meta name="color-scheme" content="light dark">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="WSM">
        <link rel="manifest" href="/manifest.webmanifest">
        <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
        <link rel="icon" type="image/png" sizes="192x192" href="/icons/icon-192.png">

        <title>{{ config('app.name', 'Stock Management') }}</title>

        <script>
            (function () {
                try {
                    var t = localStorage.getItem('app-theme');
                    if (t === 'dark' || (!t && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                        document.documentElement.classList.add('dark');
                    }
                } catch (e) {}
            })();
        </script>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-app-text antialiased">
        <div class="relative flex min-h-screen flex-col items-center justify-center overflow-hidden bg-app-bg px-4 py-10 sm:px-6">
            <div class="pointer-events-none absolute inset-x-0 top-0 h-64 bg-gradient-to-b from-primary-500/10 to-transparent dark:from-primary-500/10"></div>

            <div class="relative w-full max-w-md">
                <div class="mb-6 flex items-center justify-center gap-3">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary-600 text-base font-bold text-white shadow-sm">WS</span>
                    <div class="text-left">
                        <p class="text-sm font-semibold tracking-tight text-app-text">Stock Management</p>
                        <p class="text-xs text-app-muted">Warehouse System</p>
                    </div>
                </div>

                <div class="app-card p-6 sm:p-8">
                    {{ $slot }}
                </div>

                <p class="mt-6 text-center text-xs text-app-muted">
                    &copy; {{ date('Y') }} {{ config('app.name', 'Stock Management') }}
                </p>
            </div>
        </div>
    </body>
</html>
