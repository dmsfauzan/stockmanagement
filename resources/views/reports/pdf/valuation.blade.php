@include('reports.pdf.partials.header', ['title' => 'Laporan Valuasi', 'generatedAt' => $generatedAt, 'filters' => $filters ?? []])
<table>
    <thead>
        <tr>
            <th>SKU</th>
            <th>Item</th>
            <th>Kategori</th>
            <th>Warehouse</th>
            <th class="right">Qty</th>
            <th class="right">Harga Rata-rata</th>
            <th class="right">Total Nilai</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($rows as $row)
            @php $r = (array) $row; @endphp
            <tr>
                <td>{{ $r['sku'] ?? '-' }}</td>
                <td>{{ $r['item_name'] ?? '-' }}</td>
                <td>{{ $r['category_name'] ?? '-' }}</td>
                <td>{{ $r['warehouse_name'] ?? '-' }}</td>
                <td class="right">{{ number_format((int) ($r['quantity'] ?? 0)) }}</td>
                <td class="right">{{ number_format((float) ($r['average_cost'] ?? 0), 2) }}</td>
                <td class="right">{{ number_format((float) ($r['total_value'] ?? 0), 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="7" class="empty">Tidak ada data.</td></tr>
        @endforelse
    </tbody>
</table>
@if (isset($totals))
    <p class="totals">Total Qty: {{ number_format($totals['qty'] ?? 0) }} &middot; Total Nilai: {{ number_format($totals['value'] ?? 0, 2) }} &middot; Baris: {{ number_format($totals['rows'] ?? 0) }}</p>
@endif
</body>
</html>
