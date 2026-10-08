@include('reports.pdf.partials.header', ['title' => __('Laporan Barang Keluar'), 'generatedAt' => $generatedAt, 'filters' => $filters ?? []])
<table>
    <thead>
        <tr>
            <th>Issue No</th>
            <th>Date</th>
            <th>Customer / Destination</th>
            <th>Warehouse</th>
            <th>SKU</th>
            <th>Item</th>
            <th class="right">Qty</th>
            <th>Posted At</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($rows as $row)
            @php $r = (array) $row; @endphp
            <tr>
                <td>{{ $r['issue_number'] ?? $r['issue_no'] ?? '-' }}</td>
                <td>{{ $r['transaction_date'] ?? '-' }}</td>
                <td>{{ $r['customer_name'] ?? ($r['destination'] ?? '-') }}</td>
                <td>{{ $r['warehouse_name'] ?? '-' }}</td>
                <td>{{ $r['sku'] ?? '-' }}</td>
                <td>{{ $r['item_name'] ?? '-' }}</td>
                <td class="right">{{ isset($r['qty']) ? number_format((int) $r['qty']) : '-' }}</td>
                <td>{{ $r['posted_at'] ?? '-' }}</td>
            </tr>
        @empty
            <tr><td colspan="8" class="empty">{{ __('Tidak ada data.') }}</td></tr>
        @endforelse
    </tbody>
</table>
@if (isset($totals))
    <p class="totals">Total Qty: {{ number_format($totals['qty'] ?? 0) }} &middot; { __('Baris:') } {{ number_format($totals['rows'] ?? 0) }}</p>
@endif
</body>
</html>
