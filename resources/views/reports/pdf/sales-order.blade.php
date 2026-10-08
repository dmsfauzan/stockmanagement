@include('reports.pdf.partials.header', ['title' => __('Laporan Sales Order'), 'generatedAt' => $generatedAt, 'filters' => $filters ?? []])
<table>
    <thead>
        <tr>
            <th>No. SO</th>
            <th>{{ __('Tanggal') }}</th>
            <th>Customer</th>
            <th>Warehouse</th>
            <th>SKU</th>
            <th>{{ __('Barang') }}</th>
            <th class="right">Qty</th>
            <th class="right">{ __('Terpenuhi') }</th>
            <th class="right">{{ __('Sisa') }}</th>
            <th class="right">{{ __('Harga') }}</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($rows as $row)
            @php $r = (array) $row; @endphp
            <tr>
                <td>{{ $r['so_number'] ?? '-' }}</td>
                <td>{{ $r['order_date'] ?? '-' }}</td>
                <td>{{ $r['customer_name'] ?? '-' }}</td>
                <td>{{ $r['warehouse_name'] ?? '-' }}</td>
                <td>{{ $r['item_sku'] ?? $r['sku'] ?? '-' }}</td>
                <td>{{ $r['item_name'] ?? '-' }}</td>
                <td class="right">{{ isset($r['quantity']) ? number_format((int) $r['quantity']) : '-' }}</td>
                <td class="right">{{ isset($r['fulfilled_quantity']) ? number_format((int) $r['fulfilled_quantity']) : '-' }}</td>
                <td class="right">{{ isset($r['remaining']) ? number_format((int) $r['remaining']) : '-' }}</td>
                <td class="right">{{ isset($r['unit_price']) ? number_format((float) $r['unit_price']) : '-' }}</td>
                <td>{{ $r['status'] ?? '-' }}</td>
            </tr>
        @empty
            <tr><td colspan="11" class="empty">{{ __('Tidak ada data.') }}</td></tr>
        @endforelse
    </tbody>
</table>
@if (isset($totals))
    <p class="totals">{ __('Baris:') } {{ number_format($totals['rows'] ?? 0) }} &middot; Total Qty: {{ number_format($totals['quantity'] ?? 0) }} &middot; Total Terpenuhi: {{ number_format($totals['fulfilled'] ?? 0) }}</p>
@endif
</body>
</html>
