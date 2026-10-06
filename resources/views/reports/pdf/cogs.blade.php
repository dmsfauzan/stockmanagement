@include('reports.pdf.partials.header', ['title' => 'Laporan COGS', 'generatedAt' => $generatedAt, 'filters' => $filters ?? []])
<table>
    <thead>
        <tr>
            <th>Date</th>
            <th>Reference</th>
            <th>SKU</th>
            <th>Item</th>
            <th>Warehouse</th>
            <th class="right">Qty Out</th>
            <th class="right">Harga Satuan</th>
            <th class="right">Total COGS</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($rows as $row)
            @php $r = (array) $row; @endphp
            <tr>
                <td>{{ $r['created_at'] ?? '-' }}</td>
                <td>{{ ($r['reference_type'] ?? '').' #'.($r['reference_id'] ?? '') }}</td>
                <td>{{ $r['sku'] ?? '-' }}</td>
                <td>{{ $r['item_name'] ?? '-' }}</td>
                <td>{{ $r['warehouse_name'] ?? '-' }}</td>
                <td class="right">{{ number_format((int) ($r['quantity_out'] ?? 0)) }}</td>
                <td class="right">{{ number_format((float) ($r['unit_cost'] ?? 0), 2) }}</td>
                <td class="right">{{ number_format((float) ($r['total_cost'] ?? 0), 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="8" class="empty">Tidak ada data.</td></tr>
        @endforelse
    </tbody>
</table>
@if (isset($totals))
    <p class="totals">Total COGS: {{ number_format($totals['cogs'] ?? 0, 2) }} &middot; Total Qty: {{ number_format($totals['qty'] ?? 0) }} &middot; Baris: {{ number_format($totals['rows'] ?? 0) }}</p>
@endif
</body>
</html>
