@include('reports.pdf.partials.header', ['title' => 'Laporan Transfer', 'generatedAt' => $generatedAt, 'filters' => $filters ?? []])
<table>
    <thead>
        <tr>
            <th>No. Transfer</th>
            <th>{{ __('Tanggal') }}</th>
            <th>{{ __('Dari') }}</th>
            <th>Tujuan</th>
            <th>SKU</th>
            <th>{{ __('Barang') }}</th>
            <th class="right">Qty</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($rows as $row)
            @php $r = (array) $row; @endphp
            <tr>
                <td>{{ $r['tr_number'] ?? '-' }}</td>
                <td>{{ $r['transfer_date'] ?? '-' }}</td>
                <td>{{ ($r['from_warehouse'] ?? '-') }}{{ isset($r['from_location']) && $r['from_location'] ? ' / '.$r['from_location'] : '' }}</td>
                <td>{{ ($r['to_warehouse'] ?? '-') }}{{ isset($r['to_location']) && $r['to_location'] ? ' / '.$r['to_location'] : '' }}</td>
                <td>{{ $r['item_sku'] ?? $r['sku'] ?? '-' }}</td>
                <td>{{ $r['item_name'] ?? '-' }}</td>
                <td class="right">{{ isset($r['quantity']) ? number_format((int) $r['quantity']) : '-' }}</td>
                <td>{{ $r['status'] ?? '-' }}</td>
            </tr>
        @empty
            <tr><td colspan="8" class="empty">{{ __('Tidak ada data.') }}</td></tr>
        @endforelse
    </tbody>
</table>
@if (isset($totals))
    <p class="totals">Baris: {{ number_format($totals['rows'] ?? 0) }} &middot; Total Qty: {{ number_format($totals['qty'] ?? 0) }}</p>
@endif
</body>
</html>
