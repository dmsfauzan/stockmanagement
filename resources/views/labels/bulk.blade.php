<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Label Massal ({{ $rows->count() }}) — {{ config('app.name', 'Warehouse') }}</title>
    @vite('resources/css/app.css')
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: #fff !important; }
            .label-grid { gap: 3mm !important; }
            @page { margin: 6mm; }
        }
    </style>
</head>
<body class="bg-slate-100 p-6 font-sans text-slate-900 antialiased dark:bg-slate-900 dark:text-slate-100">
    <div class="no-print mx-auto mb-6 flex max-w-5xl flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" onclick="window.close(); if(window.opener) window.close(); else history.back();" class="app-btn app-btn-secondary">Batal</button>
            <button type="button" onclick="window.print()" class="app-btn app-btn-primary">Print</button>
        </div>
        <form method="GET" action="{{ route('labels.bulk') }}" class="flex items-center gap-2">
            @foreach ($rows as $row)
                <input type="hidden" name="ids[]" value="{{ $row['item']->id }}">
            @endforeach
            <label class="text-xs font-medium text-app-muted" for="bulk-format">Format</label>
            <select id="bulk-format" name="format" onchange="this.form.submit()" class="app-select w-auto">
                @foreach (['qr' => 'QR', 'barcode' => 'Barcode', 'both' => 'Keduanya'] as $key => $label)
                    <option value="{{ $key }}" @selected($format === $key)>{{ $label }}</option>
                @endforeach
            </select>
            <noscript><button type="submit" class="app-btn app-btn-secondary">Terapkan</button></noscript>
        </form>
    </div>

    <div class="label-grid mx-auto grid max-w-5xl grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($rows as $row)
            <div class="rounded-lg border border-slate-300 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-800 print:break-inside-avoid print:shadow-none">
                <p class="truncate text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $row['item']->brand ?? '—' }}</p>
                <p class="mt-0.5 line-clamp-2 text-xs font-semibold leading-tight text-slate-900 dark:text-slate-100">{{ $row['item']->name }}</p>
                <p class="mt-0.5 font-mono text-[11px] text-slate-600 dark:text-slate-300">{{ $row['item']->sku }}</p>

                <div class="mt-2 flex items-center gap-2">
                    @if (in_array($format, ['qr', 'both'], true))
                        <div class="shrink-0 rounded border border-slate-200 bg-white p-0.5 dark:border-slate-700">
                            <div class="h-[64px] w-[64px] [&>svg]:h-full [&>svg]:w-full">{!! $row['qrSvg'] !!}</div>
                        </div>
                    @endif
                    @if (in_array($format, ['barcode', 'both'], true))
                        <div class="min-w-0 flex-1 overflow-hidden rounded border border-slate-200 bg-white px-1 py-1 dark:border-slate-700">
                            <div class="[&>svg]:h-[34px] [&>svg]:w-full">{!! $row['barcodeSvg'] !!}</div>
                            <p class="mt-0.5 truncate text-center font-mono text-[9px] tracking-wide text-slate-600 dark:text-slate-300">{{ $row['barcodeValue'] }}</p>
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <div class="col-span-full rounded-xl border border-dashed border-slate-300 p-10 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">
                Tidak ada label untuk dicetak.
            </div>
        @endforelse
    </div>
</body>
</html>
