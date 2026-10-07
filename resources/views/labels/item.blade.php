@php
    $sizes = \App\Http\Controllers\LabelController::sizes();
    $dim = $sizes[$size] ?? $sizes['85x54'];
    $qrPx = match ($size) {
        '50x30' => 56,
        '70x40' => 68,
        '100x50' => 92,
        default => 86,
    };
    $barcodePx = match ($size) {
        '50x30' => 30,
        '70x40' => 38,
        '100x50' => 44,
        default => 46,
    };
@endphp
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
            .label-card { box-shadow: none !important; }
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
        <div class="flex flex-wrap items-center gap-2">
            <div class="flex items-center gap-1 rounded-lg border border-app-border bg-app-surface p-1">
                @foreach (['qr' => 'QR', 'barcode' => 'Barcode', 'both' => 'Keduanya'] as $key => $label)
                    <a href="{{ route('labels.item', $item) }}?format={{ $key }}&size={{ $size }}" @class(['rounded-md px-3 py-1.5 text-xs font-medium transition', 'bg-primary-600 text-white shadow-sm' => $format === $key, 'text-app-muted hover:bg-app-surface-2 hover:text-app-text' => $format !== $key])>{{ $label }}</a>
                @endforeach
            </div>
            <form method="GET" action="{{ route('labels.item', $item) }}" class="flex items-center gap-2">
                <input type="hidden" name="format" value="{{ $format }}">
                <select name="size" onchange="this.form.submit()" class="app-select w-auto">
                    @foreach ($sizes as $key => $meta)
                        <option value="{{ $key }}" @selected($size === $key)>{{ $meta['label'] }}</option>
                    @endforeach
                </select>
                <noscript><button type="submit" class="app-btn app-btn-secondary">Terapkan</button></noscript>
            </form>
        </div>
    </div>

    <div class="mx-auto flex max-w-3xl justify-center">
        <div class="label-card rounded-xl border border-slate-300 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-800" style="width: {{ $dim['width'] }}mm; min-height: {{ $dim['height'] }}mm;">
            <div class="flex items-start justify-between gap-2">
                <div class="min-w-0 flex-1">
                    <p class="truncate text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $item->brand ?? '—' }}</p>
                    <p class="mt-0.5 line-clamp-2 text-[13px] font-semibold leading-tight text-slate-900 dark:text-slate-100">{{ $item->name }}</p>
                    <p class="mt-0.5 font-mono text-[11px] text-slate-600 dark:text-slate-300">{{ $item->sku }}</p>
                    <p class="font-mono text-[10px] text-slate-500 dark:text-slate-400">{{ $barcodeValue }}</p>
                </div>
                @if (in_array($format, ['qr', 'both'], true))
                    <div class="shrink-0 rounded border border-slate-200 bg-white p-0.5 dark:border-slate-700">
                        <div class="[&>svg]:h-full [&>svg]:w-full" style="width: {{ $qrPx }}px; height: {{ $qrPx }}px;">{!! $qrSvg !!}</div>
                    </div>
                @endif
            </div>

            @if (in_array($format, ['barcode', 'both'], true))
                <div class="mt-2 rounded border border-slate-200 bg-white px-1.5 py-1 dark:border-slate-700">
                    <div class="overflow-hidden [&>svg]:w-full" style="height: {{ $barcodePx }}px;">{!! $barcodeSvg !!}</div>
                    <p class="mt-0.5 text-center font-mono text-[10px] tracking-widest text-slate-700 dark:text-slate-300">{{ $barcodeValue }}</p>
                </div>
            @endif
        </div>
    </div>

    <p class="no-print mx-auto mt-6 max-w-3xl text-center text-xs text-app-muted">Ukuran label {{ $dim['label'] }}. Gunakan Print untuk mencetak.</p>
</body>
</html>
