@include('reports.pdf.partials.header', ['title' => 'Laporan Stok', 'generatedAt' => $generatedAt, 'filters' => $filters ?? []])
<table>
    <thead>
        <tr>
            <th>SKU</th>
            <th>Item</th>
            <th>Kategori</th>
            <th>Warehouse</th>
            <th>Lokasi</th>
            <th class="right">On Hand</th>
            <th class="right">Min</th>
            <th class="right">Max</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($rows as $row)
            <tr>
                <td>{{ is_array($row) ? $row['sku'] : $row->sku }}</td>
                <td>{{ is_array($row) ? $row['item_name'] : $row->item_name }}</td>
                <td>{{ is_array($row) ? $row['category_name'] : $row->category_name }}</td>
                <td>{{ is_array($row) ? $row['warehouse_name'] : $row->warehouse_name }}</td>
                <td>{{ is_array($row) ? $row['location_path'] : $row->location_path }}</td>
                <td class="right">{{ number_format((int) (is_array($row) ? $row['quantity_on_hand'] : $row->quantity_on_hand)) }}</td>
                <td class="right">{{ (int) (is_array($row) ? $row['min_stock'] : $row->min_stock) }}</td>
                <td class="right">{{ (int) (is_array($row) ? $row['max_stock'] : $row->max_stock) }}</td>
                <td>{{ is_array($row) ? $row['status'] : ($row->stock_status ?? $row->status ?? '-') }}</td>
            </tr>
        @empty
            <tr><td colspan="9" class="empty">Tidak ada data.</td></tr>
        @endforelse
    </tbody>
</table>
@if (isset($totals))
    <p class="totals">Total On Hand: {{ number_format($totals['on_hand'] ?? 0) }} &middot; Total Available: {{ number_format($totals['available'] ?? 0) }} &middot; Baris: {{ number_format($totals['rows'] ?? 0) }}</p>
@endif
</body>
</html>
