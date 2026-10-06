@include('reports.pdf.partials.header', ['title' => 'Perbandingan Gudang', 'generatedAt' => $generatedAt, 'filters' => $filters ?? []])
<table>
    <thead>
        <tr>
            <th>Kode</th>
            <th>Gudang</th>
            <th class="right">Total Item</th>
            <th class="right">On Hand</th>
            <th class="right">Available</th>
            <th class="right">Low</th>
            <th class="right">Out</th>
            <th class="right">Expired</th>
            <th class="right">H-30</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($rows as $row)
            @php $r = is_array($row) ? (object) $row : $row; @endphp
            <tr>
                <td>{{ $r->code }}</td>
                <td>{{ $r->name }}</td>
                <td class="right">{{ number_format((int) $r->total_items) }}</td>
                <td class="right">{{ number_format((int) $r->total_on_hand) }}</td>
                <td class="right">{{ number_format((int) $r->total_available) }}</td>
                <td class="right">{{ number_format((int) $r->low_count) }}</td>
                <td class="right">{{ number_format((int) $r->out_count) }}</td>
                <td class="right">{{ number_format((int) $r->expired_count) }}</td>
                <td class="right">{{ number_format((int) $r->soon_count) }}</td>
            </tr>
        @empty
            <tr><td colspan="9" class="empty">Tidak ada data.</td></tr>
        @endforelse
    </tbody>
</table>
@if (isset($totals))
    <p class="totals">On Hand: {{ number_format($totals['on_hand'] ?? 0) }} &middot; Available: {{ number_format($totals['available'] ?? 0) }} &middot; Low: {{ number_format($totals['low'] ?? 0) }} &middot; Out: {{ number_format($totals['out'] ?? 0) }} &middot; Expired: {{ number_format($totals['expired'] ?? 0) }} &middot; H-30: {{ number_format($totals['soon'] ?? 0) }}</p>
@endif
</body>
</html>
