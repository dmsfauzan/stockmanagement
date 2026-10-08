@php
    $sizes = \App\Http\Controllers\LabelController::sizes();
    $dim = $sizes[$size] ?? $sizes['85x54'];
    $colsClass = match ($size) {
        '50x30' => 'grid-cols-2 md:grid-cols-3 lg:grid-cols-5',
        '100x50' => 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3',
        default => 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3',
    };
    $qrPx = match ($size) {
        '50x30' => 44,
        '70x40' => 52,
        '100x50' => 64,
        default => 56,
    };
    $barcodePx = match ($size) {
        '50x30' => 24,
        '70x40' => 30,
        '100x50' => 38,
        default => 30,
    };
@endphp
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
            .label-grid { gap: 2mm !important; }
            @page { margin: 5mm; }
            .label-card { box-shadow: none !important; }
        }
    </style>
</head>
<body class="bg-slate-100 p-4 font-sans text-slate-900 antialiased dark:bg-slate-900 dark:text-slate-100">
    <div class="no-print mx-auto mb-4 flex max-w-6xl flex-wrap gap-3">
        <form method="GET" action="{{ route('labels.bulk') }}" class="flex flex-wrap gap-2">
            @foreach ($rows as $row)
                <input type="hidden" name="ids[]" value="{{ $row['item']->id }}">
            @endforeach
            <input type="text" name="search" value="{{ $filter['search'] ?? '' }}" placeholder="Cari SKU/Barcode/Nama" class="app-input w-52">
            @if (isset($filters['categories']))
                <select name="category_id" class="app-select w-auto">
                    <option value="">Semua Kategori</option>
                    @foreach ($filters['categories'] as $category)
                        <option value="{{ $category->id }}" @selected(($filter['category_id'] ?? '') === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            @endif
            @if (isset($filters['warehouses']))
                <select name="warehouse_id" class="app-select w-auto">
                    <option value="">Semua Gudang</option>
                    @foreach ($filters['warehouses'] as $warehouse)
                        <option value="{{ $warehouse->id }}" @selected(($filter['warehouse_id'] ?? '') === (string) $warehouse->id)>{{ $warehouse->name }}</option>
                    @endforeach
                </select>
            @endif
            <select name="format" class="app-select w-auto">
                @foreach (['qr' => 'QR', 'barcode' => 'Barcode', 'both' => 'Keduanya'] as $key => $label)
                    <option value="{{ $key }}" @selected($format === $key)>{{ $label }}</option>
                @endforeach
            </select>
            <select name="size" class="app-select w-auto">
                @foreach ($sizes as $key => $meta)
                    <option value="{{ $key }}" @selected($size === $key)>{{ $meta['label'] }}</option>
                @endforeach
            </select>
            <button type="submit" class="app-btn app-btn-primary">Terapkan</button>
        </form>
        <div class="ml-auto flex gap-2">
            <button type="button" onclick="window.close(); if(window.opener) window.close(); else history.back();" class="app-btn app-btn-secondary">{{ __('Batal') }}</button>
            <button type="button" onclick="window.print()" class="app-btn app-btn-primary">Print {{ $rows->count() }} Label</button>
        </div>
    </div>

    <p class="no-print mx-auto mb-3 max-w-6xl text-xs text-app-muted">Ukuran: {{ $dim['label'] }} · {{ $rows->count() }} label.</p>

    <div class="label-grid mx-auto grid max-w-6xl gap-2 {{ $colsClass }}">
        @forelse ($rows as $row)
            <div class="label-card rounded-lg border border-slate-300 bg-white p-2 shadow-sm dark:border-slate-700 dark:bg-slate-800 flex flex-col justify-between print:break-inside-avoid" style="min-height: {{ $dim['height'] }}mm;">
                <div>
                    <p class="truncate text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $row['item']->brand ?? '—' }}</p>
                    <p class="mt-0.5 line-clamp-2 text-xs font-semibold leading-tight text-slate-900 dark:text-slate-100">{{ $row['item']->name }}</p>
                    <p class="mt-0.5 font-mono text-[11px] text-slate-600 dark:text-slate-300">{{ $row['item']->sku }}</p>
                </div>
                <div class="mt-2 flex items-center gap-2">
                    @if (in_array($format, ['qr', 'both'], true))
                        <div class="shrink-0 rounded border border-slate-200 bg-white p-0.5 dark:border-slate-700">
                            <div class="[&>svg]:h-full [&>svg]:w-full" style="width: {{ $qrPx }}px; height: {{ $qrPx }}px;">{!! $row['qrSvg'] !!}</div>
                        </div>
                    @endif
                    @if (in_array($format, ['barcode', 'both'], true))
                        <div class="min-w-0 flex-1 overflow-hidden rounded border border-slate-200 bg-white px-1 py-1 dark:border-slate-700">
                            <div class="[&>svg]:w-full" style="height: {{ $barcodePx }}px;">{!! $row['barcodeSvg'] !!}</div>
                            <p class="mt-0.5 truncate text-center font-mono text-[9px] tracking-wide text-slate-600 dark:text-slate-300">{{ $row['barcodeValue'] }}</p>
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <div class="col-span-full rounded-xl border border-dashed border-slate-300 p-10 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">
                Tidak ada label — ubah filter atau pilih barang dari halaman daftar.
            </div>
        @endforelse
    </div>
</body>
</html>
