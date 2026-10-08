@include('reports.pdf.partials.header', ['title' => 'Laporan Barang Masuk', 'generatedAt' => $generatedAt, 'filters' => $filters ?? []])
<table>
    <thead>
        <tr>
            <th>Receipt No</th>
            <th>Date</th>
            <th>Supplier</th>
            <th>Warehouse</th>
            <th>SKU</th>
            <th>Item</th>
            <th class="right">Qty</th>
            <th>Batch</th>
            <th>Expiry</th>
            <th>Posted At</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($rows as $row)
            @php $r = (array) $row; @endphp
            <tr>
                <td>{{ $r['receipt_number'] ?? $r['receipt_no'] ?? '-' }}</td>
                <td>{{ $r['transaction_date'] ?? '-' }}</td>
                <td>{{ $r['supplier_name'] ?? '-' }}</td>
                <td>{{ $r['warehouse_name'] ?? '-' }}</td>
                <td>{{ $r['sku'] ?? '-' }}</td>
                <td>{{ $r['item_name'] ?? '-' }}</td>
                <td class="right">{{ isset($r['qty']) ? number_format((int) $r['qty']) : '-' }}</td>
                <td>{{ $r['batch_number'] ?? '-' }}</td>
                <td>{{ $r['expiry_date'] ?? '-' }}</td>
                <td>{{ $r['posted_at'] ?? '-' }}</td>
            </tr>
        @empty
            <tr><td colspan="10" class="empty">{{ __('Tidak ada data.') }}</td></tr>
        @endforelse
    </tbody>
</table>
@if (isset($totals))
    <p class="totals">Total Qty: {{ number_format($totals['qty'] ?? 0) }} &middot; Baris: {{ number_format($totals['rows'] ?? 0) }}</p>
@endif
</body>
</html>
