@include('reports.pdf.partials.header', ['title' => 'Laporan Kedaluwarsa', 'generatedAt' => $generatedAt, 'filters' => $filters ?? []])
<table>
    <thead>
        <tr>
            <th>SKU</th>
            <th>Item</th>
            <th>Warehouse</th>
            <th>Location</th>
            <th>Batch</th>
            <th>Expiry Date</th>
            <th class="right">Days Left</th>
            <th class="right">Qty Received</th>
            <th>Reference</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($rows as $row)
            @php $r = (array) $row; @endphp
            <tr>
                <td>{{ $r['sku'] ?? '-' }}</td>
                <td>{{ $r['item_name'] ?? '-' }}</td>
                <td>{{ $r['warehouse_name'] ?? '-' }}</td>
                <td>{{ $r['location_code'] ?? '-' }}</td>
                <td>{{ $r['batch_number'] ?? '-' }}</td>
                <td>{{ $r['expiry_date'] ?? '-' }}</td>
                <td class="right">{{ $r['days_left'] ?? '-' }}</td>
                <td class="right">{{ isset($r['quantity_in']) ? number_format((int) $r['quantity_in']) : '-' }}</td>
                <td>{{ $r['reference'] ?? '-' }}</td>
            </tr>
        @empty
            <tr><td colspan="9" class="empty">Tidak ada data.</td></tr>
        @endforelse
    </tbody>
</table>
@if (isset($totals))
    <p class="totals">Total Qty: {{ number_format($totals['qty'] ?? 0) }} &middot; Baris: {{ number_format($totals['rows'] ?? 0) }}</p>
@endif
</body>
</html>
