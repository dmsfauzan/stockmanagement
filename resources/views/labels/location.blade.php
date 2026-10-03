<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Label Lokasi — {{ $location->code }} — {{ config('app.name', 'Warehouse') }}</title>
    @vite('resources/css/app.css')
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: #fff !important; }
            @page { margin: 8mm; }
        }
    </style>
</head>
<body class="bg-slate-100 p-6 font-sans text-slate-900 antialiased dark:bg-slate-900 dark:text-slate-100">
    <div class="no-print mx-auto mb-6 flex max-w-3xl items-center justify-between gap-3">
        <div class="flex items-center gap-2">
            <button type="button" onclick="window.close(); if(window.opener) window.close(); else history.back();" class="app-btn app-btn-secondary">Batal</button>
            <button type="button" onclick="window.print()" class="app-btn app-btn-primary">Print</button>
        </div>
    </div>

    <div class="mx-auto flex max-w-3xl justify-center">
        <div class="w-[85mm] min-h-[54mm] rounded-xl border border-slate-300 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-800 print:shadow-none">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Lokasi</p>
            <p class="mt-0.5 text-sm font-semibold leading-tight text-slate-900 dark:text-slate-100">{{ $location->fullPath() }}</p>
            <p class="mt-1 font-mono text-xs text-slate-600 dark:text-slate-300">{{ $location->code }}</p>
            <div class="mt-3 flex justify-center">
                <div class="rounded-lg border border-slate-200 bg-white p-1 dark:border-slate-700">
                    <div class="h-[140px] w-[140px] [&>svg]:h-full [&>svg]:w-full">{!! $qrSvg !!}</div>
                </div>
            </div>
            <p class="mt-2 truncate text-center font-mono text-[10px] text-slate-400 dark:text-slate-500">{{ $qrData }}</p>
        </div>
    </div>
</body>
</html>
