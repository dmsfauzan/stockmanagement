@include('reports.pdf.partials.header', ['title' => 'Laporan Pergerakan Stok', 'generatedAt' => $generatedAt, 'filters' => $filters ?? []])
<table>
    <thead>
        <tr>
            <th>Date</th>
            <th>SKU</th>
            <th>Item</th>
            <th>Warehouse</th>
            <th>Location</th>
            <th>Type</th>
            <th class="right">Qty In</th>
            <th class="right">Qty Out</th>
            <th class="right">Balance</th>
            <th class="right">Unit Cost</th>
            <th class="right">Total Cost</th>
            <th>User</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($rows as $row)
            @php $r = (array) $row; @endphp
            <tr>
                <td>{{ $r['created_at'] ?? '-' }}</td>
                <td>{{ $r['sku'] ?? '-' }}</td>
                <td>{{ $r['item_name'] ?? '-' }}</td>
                <td>{{ $r['warehouse_name'] ?? '-' }}</td>
                <td>{{ $r['location_code'] ?? '-' }}</td>
                <td>{{ $r['transaction_type'] ?? '-' }}</td>
                <td class="right">{{ (int) ($r['quantity_in'] ?? 0) ?: '-' }}</td>
                <td class="right">{{ (int) ($r['quantity_out'] ?? 0) ?: '-' }}</td>
                <td class="right">{{ (int) ($r['balance_after'] ?? 0) }}</td>
                <td class="right">{{ number_format((float) ($r['unit_cost'] ?? 0), 2) }}</td>
                <td class="right">{{ number_format((float) ($r['total_cost'] ?? 0), 2) }}</td>
                <td>{{ $r['user_name'] ?? '-' }}</td>
            </tr>
        @empty
            <tr><td colspan="12" class="empty">Tidak ada data.</td></tr>
        @endforelse
    </tbody>
</table>
@if (isset($totals))
    <p class="totals">Total In: {{ number_format($totals['in'] ?? 0) }} &middot; Total Out: {{ number_format($totals['out'] ?? 0) }} &middot; Baris: {{ number_format($totals['rows'] ?? 0) }}</p>
@endif
</body>
</html>
