<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Packing Slip — {{ $pickList->number }}</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #1e293b; margin: 24px; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        p { margin: 2px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid #cbd5e1; padding: 6px 8px; }
        th { background: #f8fafc; text-align: left; }
        td.right { text-align: right; }
    </style>
</head>
<body>
    <h1>{{ $pickList->number }}</h1>
    <p>Gudang: {{ $pickList->warehouse?->name ?? '-' }}</p>
    <p>Barang Keluar: {{ $pickList->goodsIssue?->number ?? '-' }}</p>
    <p>Status: {{ $pickList->status instanceof \App\Enums\PickStatus ? $pickList->status->label() : strtoupper($pickList->status) }}</p>
    <table>
        <thead><tr><th>#</th><th>Barang</th><th>SKU</th><th>Lokasi</th><th>Batch / Serial</th><th class="right">Diminta</th><th class="right">Dipicking</th></tr></thead>
        <tbody>
            @foreach ($pickList->items as $i => $line)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $line->item?->name ?? '-' }}</td>
                    <td>{{ $line->item?->sku ?? '-' }}</td>
                    <td>{{ $line->location?->fullPath() ?? '-' }}</td>
                    <td>{{ $line->serial_number ?? ($line->batch_number ?? '-') }}</td>
                    <td class="right">{{ $line->quantity }}</td>
                    <td class="right">{{ $line->picked_quantity }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <p style="margin-top: 16px; font-size: 10px; color: #64748b;">Cetak: {{ now()->format('d M Y H:i') }}</p>
</body>
</html>
