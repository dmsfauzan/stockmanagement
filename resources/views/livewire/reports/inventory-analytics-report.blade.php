<div>
    <x-ui.page-header title="Analitik Inventori" subtitle="Aging, klasifikasi ABC, perputaran stok, dan barang tidak bergerak">
        <x-slot:actions>
            <button type="button" wire:click="exportCsv" class="app-btn app-btn-secondary">{{ __('Export CSV') }}</button>
            <button type="button" wire:click="exportExcel" class="app-btn app-btn-primary">{{ __('Export Excel') }}</button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card padding="p-0" class="mb-4">
        <div class="flex flex-wrap gap-2 border-b border-app-border px-4">
            @foreach (['aging' => 'Aging', 'abc' => 'ABC', 'turnover' => 'Perputaran', 'slow' => 'Slow / Dead'] as $key => $label)
                <button type="button" wire:click="setTab('{{ $key }}')" class="border-b-2 px-3 py-3 text-sm font-medium transition {{ $tab === $key ? 'border-primary-600 text-primary-600 dark:border-primary-400 dark:text-primary-400' : 'border-transparent text-app-muted hover:text-app-text' }}">{{ $label }}</button>
            @endforeach
        </div>

        <div class="app-card-header flex-col items-stretch gap-3 sm:flex-row">
            <div class="flex flex-1 flex-wrap gap-3">
                <select wire:model.live="warehouseFilter" class="app-select sm:w-auto">
                    <option value="">{{ __('Semua Warehouse') }}</option>
                    @foreach ($warehouses as $w)
                        <option value="{{ $w->id }}">{{ $w->name }}</option>
                    @endforeach
                </select>
                @if ($tab === 'aging')
                    <select wire:model.live="categoryFilter" class="app-select sm:w-auto">
                        <option value="">Semua Kategori</option>
                        @foreach ($categories as $c)
                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                        @endforeach
                    </select>
                @endif
                @if (in_array($tab, ['abc', 'turnover'], true))
                    <input type="date" wire:model.live="fromDate" class="app-input sm:w-auto">
                    <input type="date" wire:model.live="toDate" class="app-input sm:w-auto">
                @endif
            </div>
            <button type="button" wire:click="resetFilters" class="app-btn app-btn-secondary">{{ __('Reset') }}</button>
        </div>

        <div class="overflow-x-auto">
            @if ($tab === 'aging')
                <table class="app-table">
                    <thead><tr><th>SKU</th><th>Item</th><th>Warehouse</th><th class="text-right">Qty</th><th class="text-right">Nilai</th><th>Terakhir Masuk</th><th class="text-right">Umur (hari)</th><th>Bucket</th></tr></thead>
                    <tbody>
                        @forelse ($data['aging'] as $row)
                            <tr>
                                <td class="font-medium text-app-text">{{ $row['sku'] }}</td>
                                <td class="text-app-text">{{ $row['item_name'] }}</td>
                                <td class="text-app-muted">{{ $row['warehouse_name'] }}</td>
                                <td class="text-right">{{ number_format($row['quantity']) }}</td>
                                <td class="text-right">{{ number_format($row['value'], 2) }}</td>
                                <td class="text-app-muted">{{ $row['last_in'] ? \Illuminate\Support\Carbon::parse($row['last_in'])->format('d M Y') : '-' }}</td>
                                <td class="text-right">{{ $row['age_days'] ?? '-' }}</td>
                                <td><x-ui.status-badge :status="$row['bucket']" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="8"><x-ui.empty-state title="Tidak ada data" message="Tidak ada stok untuk dianalisis." /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            @elseif ($tab === 'abc')
                <table class="app-table">
                    <thead><tr><th>SKU</th><th>Item</th><th class="text-right">Pemakaian Qty</th><th class="text-right">Nilai Pemakaian</th><th class="text-right">Share %</th><th class="text-right">Kumulatif %</th><th>Kelas</th></tr></thead>
                    <tbody>
                        @forelse ($data['abc'] as $row)
                            <tr>
                                <td class="font-medium text-app-text">{{ $row['sku'] }}</td>
                                <td class="text-app-text">{{ $row['item_name'] }}</td>
                                <td class="text-right">{{ number_format($row['usage_qty']) }}</td>
                                <td class="text-right">{{ number_format($row['usage_value'], 2) }}</td>
                                <td class="text-right text-app-muted">{{ number_format($row['share'], 2) }}</td>
                                <td class="text-right text-app-muted">{{ number_format($row['cumulative_share'], 2) }}</td>
                                <td><span @class(['app-badge font-semibold', 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' => $row['class'] === 'A', 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300' => $row['class'] === 'B', 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' => $row['class'] === 'C'])>{{ $row['class'] }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="7"><x-ui.empty-state title="Tidak ada pemakaian" message="Belum ada transaksi keluar pada periode ini." /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            @elseif ($tab === 'turnover')
                @php $t = $data['turnover']; @endphp
                <div class="grid grid-cols-1 gap-4 p-5 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="app-card p-4"><p class="text-xs font-semibold uppercase text-app-muted">Perputaran</p><p class="mt-2 text-2xl font-semibold text-app-text">{{ number_format($t['turnover'] ?? 0, 2) }}x</p></div>
                    <div class="app-card p-4"><p class="text-xs font-semibold uppercase text-app-muted">Hari Persediaan</p><p class="mt-2 text-2xl font-semibold text-app-text">{{ $t['days_of_supply'] !== null ? number_format($t['days_of_supply'], 1) : '-' }}</p></div>
                    <div class="app-card p-4"><p class="text-xs font-semibold uppercase text-app-muted">COGS</p><p class="mt-2 text-2xl font-semibold text-app-text">{{ number_format($t['cogs'] ?? 0, 2) }}</p></div>
                    <div class="app-card p-4"><p class="text-xs font-semibold uppercase text-app-muted">Nilai Rata-rata</p><p class="mt-2 text-2xl font-semibold text-app-text">{{ number_format($t['average_value'] ?? 0, 2) }}</p></div>
                </div>
                <div class="px-5 pb-5 text-sm text-app-muted">
                    Periode {{ \Illuminate\Support\Carbon::parse($t['from'])->format('d M Y') }} — {{ \Illuminate\Support\Carbon::parse($t['to'])->format('d M Y') }} ({{ $t['days'] }} hari).
                    Nilai awal {{ number_format($t['opening_value'] ?? 0, 2) }} · nilai akhir {{ number_format($t['closing_value'] ?? 0, 2) }}.
                </div>
            @else
                <div class="px-5 pt-4 text-xs text-app-muted">Barang dengan stok tetapi tidak ada transaksi keluar dalam {{ $slowDays }} hari terakhir.</div>
                <table class="app-table">
                    <thead><tr><th>SKU</th><th>Item</th><th>Warehouse</th><th class="text-right">Qty</th><th>Terakhir Keluar</th><th class="text-right">Idle (hari)</th></tr></thead>
                    <tbody>
                        @forelse ($data['slow'] as $row)
                            <tr>
                                <td class="font-medium text-app-text">{{ $row['sku'] }}</td>
                                <td class="text-app-text">{{ $row['item_name'] }}</td>
                                <td class="text-app-muted">{{ $row['warehouse_name'] }}</td>
                                <td class="text-right">{{ number_format($row['quantity']) }}</td>
                                <td class="text-app-muted">{{ $row['last_out'] ? \Illuminate\Support\Carbon::parse($row['last_out'])->format('d M Y') : __('Belum pernah') }}</td>
                                <td class="text-right">{{ $row['idle_days'] ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><x-ui.empty-state title="Semua barang bergerak" message="Tidak ada slow-moving stock." /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            @endif
        </div>
    </x-ui.card>
</div>
