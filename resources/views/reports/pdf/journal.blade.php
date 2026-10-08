@include('reports.pdf.partials.header', ['title' => __('Jurnal Akuntansi'), 'generatedAt' => $generatedAt, 'filters' => $filters ?? []])
<table>
    <thead>
        <tr>
            <th>Date</th>
            <th>Reference</th>
            <th>Type</th>
            <th>SKU</th>
            <th>Item</th>
            <th>Warehouse</th>
            <th class="right">Qty</th>
            <th class="right">Unit Cost</th>
            <th class="right">Total</th>
            <th>Debit</th>
            <th>Credit</th>
            <th>Description</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($rows as $row)
            @php $r = (array) $row; @endphp
            <tr>
                <td>{{ $r['created_at'] ?? '-' }}</td>
                <td>{{ $r['reference'] ?? '-' }}</td>
                <td>{{ $r['transaction_type'] ?? '-' }}</td>
                <td>{{ $r['sku'] ?? '-' }}</td>
                <td>{{ $r['item_name'] ?? '-' }}</td>
                <td>{{ $r['warehouse_name'] ?? '-' }}</td>
                <td class="right">{{ number_format((int) ($r['quantity'] ?? 0)) }}</td>
                <td class="right">{{ number_format((float) ($r['unit_cost'] ?? 0), 2) }}</td>
                <td class="right">{{ number_format((float) ($r['total_cost'] ?? 0), 2) }}</td>
                <td>{{ $r['debit_account'] ?? '-' }}</td>
                <td>{{ $r['credit_account'] ?? '-' }}</td>
                <td>{{ $r['description'] ?? '-' }}</td>
            </tr>
        @empty
            <tr><td colspan="12" class="empty">{{ __('Tidak ada data.') }}</td></tr>
        @endforelse
    </tbody>
</table>
@if (isset($totals))
    <p class="totals">Total Debit: {{ number_format($totals['debits'] ?? 0, 2) }} &middot; Total Kredit: {{ number_format($totals['credits'] ?? 0, 2) }} &middot; Net: {{ number_format($totals['net'] ?? 0, 2) }} &middot; { __('Baris:') } {{ number_format($totals['rows'] ?? 0) }}</p>
@endif
</body>
</html>
