<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Label — {{ $item->sku }} — {{ config('app.name', 'Warehouse') }}</title>
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
    <div class="no-print mx-auto mb-6 flex max-w-3xl flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('items.show', $item) }}" class="app-btn app-btn-secondary">Kembali</a>
            <button type="button" onclick="window.close(); if(window.opener) window.close(); else history.back();" class="app-btn app-btn-secondary">Batal</button>
            <button type="button" onclick="window.print()" class="app-btn app-btn-primary">Print</button>
        </div>
        <div class="flex items-center gap-1 rounded-lg border border-app-border bg-app-surface p-1">
            @foreach (['qr' => 'QR', 'barcode' => 'Barcode', 'both' => 'Keduanya'] as $key => $label)
                <a href="{{ route('labels.item', $item) }}?format={{ $key }}" @class(['rounded-md px-3 py-1.5 text-xs font-medium transition', 'bg-primary-600 text-white shadow-sm' => $format === $key, 'text-app-muted hover:bg-app-surface-2 hover:text-app-text' => $format !== $key])>{{ $label }}</a>
            @endforeach
        </div>
    </div>

    <div class="mx-auto flex max-w-3xl justify-center">
        <div class="w-[85mm] min-h-[54mm] rounded-xl border border-slate-300 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-800 print:shadow-none">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0 flex-1">
                    <p class="truncate text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $item->brand ?? '—' }}</p>
                    <p class="mt-0.5 line-clamp-2 text-sm font-semibold leading-tight text-slate-900 dark:text-slate-100">{{ $item->name }}</p>
                    <p class="mt-1 font-mono text-xs text-slate-600 dark:text-slate-300">{{ $item->sku }}</p>
                    <p class="font-mono text-[11px] text-slate-500 dark:text-slate-400">{{ $barcodeValue }}</p>
                </div>
                @if (in_array($format, ['qr', 'both'], true))
                    <div class="shrink-0 rounded-lg border border-slate-200 bg-white p-1 dark:border-slate-700">
                        <div class="h-[86px] w-[86px] [&>svg]:h-full [&>svg]:w-full">{!! $qrSvg !!}</div>
                    </div>
                @endif
            </div>

            @if (in_array($format, ['barcode', 'both'], true))
                <div class="mt-3 rounded-lg border border-slate-200 bg-white px-2 py-2 dark:border-slate-700">
                    <div class="overflow-hidden [&>svg]:h-[46px] [&>svg]:w-full">{!! $barcodeSvg !!}</div>
                    <p class="mt-1 text-center font-mono text-[11px] tracking-widest text-slate-700 dark:text-slate-300">{{ $barcodeValue }}</p>
                </div>
            @endif

            <p class="mt-2 truncate text-center font-mono text-[10px] text-slate-400 dark:text-slate-500">{{ $qrData }}</p>
        </div>
    </div>

    <p class="no-print mx-auto mt-6 max-w-3xl text-center text-xs text-app-muted">Ukuran label 85×54 mm. Gunakan Print untuk mencetak. Pilih format QR / Barcode / Keduanya di atas.</p>
</body>
</html>
