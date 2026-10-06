@include('reports.pdf.partials.header', ['title' => 'Laporan Adjustment', 'generatedAt' => $generatedAt, 'filters' => $filters ?? []])
<table>
    <thead>
        <tr>
            <th>No. Adjustment</th>
            <th>Tanggal</th>
            <th>Warehouse</th>
            <th>Lokasi</th>
            <th>SKU</th>
            <th>Barang</th>
            <th class="right">System</th>
            <th class="right">Actual</th>
            <th class="right">Selisih</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($rows as $row)
            @php $r = (array) $row; @endphp
            <tr>
                <td>{{ $r['adj_number'] ?? '-' }}</td>
                <td>{{ $r['transaction_date'] ?? '-' }}</td>
                <td>{{ $r['warehouse_name'] ?? '-' }}</td>
                <td>{{ $r['location_code'] ?? '-' }}</td>
                <td>{{ $r['item_sku'] ?? $r['sku'] ?? '-' }}</td>
                <td>{{ $r['item_name'] ?? '-' }}</td>
                <td class="right">{{ isset($r['system_quantity']) ? number_format((int) $r['system_quantity']) : '-' }}</td>
                <td class="right">{{ isset($r['actual_quantity']) ? number_format((int) $r['actual_quantity']) : '-' }}</td>
                <td class="right">{{ isset($r['difference']) ? number_format((int) $r['difference']) : '-' }}</td>
                <td>{{ $r['status'] ?? '-' }}</td>
            </tr>
        @empty
            <tr><td colspan="10" class="empty">Tidak ada data.</td></tr>
        @endforelse
    </tbody>
</table>
@if (isset($totals))
    <p class="totals">Baris: {{ number_format($totals['rows'] ?? 0) }} &middot; Selisih Bersih: {{ number_format($totals['net'] ?? 0) }} &middot; Total Variance: {{ number_format($totals['abs'] ?? 0) }}</p>
@endif
</body>
</html>
