<div>
    <x-ui.page-header title="Laporan Pergerakan Stok" subtitle="Riwayat keluar-masuk stok dari buku besar">
        <x-slot:actions>
            @can('reports.export')
                <x-ui.button variant="secondary" wire:click="exportCsv">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                    CSV
                </x-ui.button>
                <x-ui.button variant="secondary" wire:click="exportExcel">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                    Excel
                </x-ui.button>
                <x-ui.button variant="secondary" wire:click="exportPdf">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                    PDF
                </x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
        <x-ui.stat-card label="Total In" :value="number_format($totals['in'])" tone="emerald" />
        <x-ui.stat-card label="Total Out" :value="number_format($totals['out'])" tone="rose" />
        <x-ui.stat-card label="Baris Data" :value="number_format($totals['rows'])" tone="slate" />
    </div>

    <x-ui.card padding="p-0">
        <div class="border-b border-app-border p-4">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div class="relative">
                    <svg class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-app-muted" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.3-4.3M10 18a8 8 0 110-16 8 8 0 010 16z"/></svg>
                    <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari SKU / nama barang..." class="app-input pl-9">
                </div>
                <select wire:model.live="itemFilter" class="app-select">
                    <option value="">Semua Barang</option>
                    @foreach ($items as $item)<option value="{{ $item->id }}">{{ $item->sku }} - {{ $item->name }}</option>@endforeach
                </select>
                <select wire:model.live="warehouseFilter" class="app-select">
                    <option value="">Semua Warehouse</option>
                    @foreach ($warehouses as $warehouse)<option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>@endforeach
                </select>
                <select wire:model.live="transactionTypeFilter" class="app-select">
                    <option value="">Semua Tipe</option>
                    @foreach ($transactionTypes as $type)<option value="{{ $type->value }}">{{ $type->label() }}</option>@endforeach
                </select>
                <select wire:model.live="userFilter" class="app-select">
                    <option value="">Semua User</option>
                    @foreach ($users as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach
                </select>
                <input type="date" wire:model.live="fromDate" class="app-input">
                <input type="date" wire:model.live="toDate" class="app-input">
                <select wire:model.live="perPage" class="app-select w-24">
                    @foreach ([15, 25, 50, 100] as $size)<option value="{{ $size }}">{{ $size }}</option>@endforeach
                </select>
            </div>
            <div class="mt-3 flex items-center justify-between">
                <button type="button" wire:click="resetFilters" class="app-btn app-btn-ghost">Reset Filter</button>
                @include('livewire.reports._report-columns')
            </div>
            <div class="mt-3">
                @include('livewire.reports._saved-filters')
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="app-table">
                <thead>
                    <tr>
                        <th class="rc-date">Tanggal</th>
                        <th class="rc-sku">SKU</th>
                        <th class="rc-item">Barang</th>
                        <th class="rc-warehouse">Warehouse</th>
                        <th class="rc-location">Lokasi</th>
                        <th class="rc-transaction_type">Tipe</th>
                        <th class="rc-quantity_in text-right">In</th>
                        <th class="rc-quantity_out text-right">Out</th>
                        <th class="rc-balance_after text-right">Balance</th>
                        <th class="rc-unit_cost text-right">Hrg Satuan</th>
                        <th class="rc-total_cost text-right">Nilai</th>
                        <th class="rc-user">User</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td class="rc-date whitespace-nowrap text-app-muted">{{ $row->created_at }}</td>
                            <td class="rc-sku whitespace-nowrap font-medium">{{ $row->sku }}</td>
                            <td class="rc-item">{{ $row->item_name }}</td>
                            <td class="rc-warehouse whitespace-nowrap text-app-muted">{{ $row->warehouse_name }}</td>
                            <td class="rc-location whitespace-nowrap text-app-muted">{{ $row->location_code ?? '-' }}</td>
                            <td class="rc-transaction_type whitespace-nowrap"><x-ui.status-badge :status="$row->transaction_type" /></td>
                            <td class="rc-quantity_in whitespace-nowrap text-right font-medium text-emerald-600 dark:text-emerald-400">{{ (int) $row->quantity_in ?: '-' }}</td>
                            <td class="rc-quantity_out whitespace-nowrap text-right font-medium text-rose-600 dark:text-rose-400">{{ (int) $row->quantity_out ?: '-' }}</td>
                            <td class="rc-balance_after whitespace-nowrap text-right text-app-muted">{{ (int) $row->balance_after }}</td>
                            <td class="rc-unit_cost whitespace-nowrap text-right text-app-muted">{{ number_format((float) $row->unit_cost, 2) }}</td>
                            <td class="rc-total_cost whitespace-nowrap text-right text-app-muted">{{ number_format((float) $row->total_cost, 2) }}</td>
                            <td class="rc-user whitespace-nowrap text-app-muted">{{ $row->user_name ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="12"><x-ui.empty-state title="Tidak ada pergerakan stok" message="Belum ada data yang cocok dengan filter." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($rows->hasPages())<div class="border-t border-app-border px-4 py-3">{{ $rows->links() }}</div>@endif
    </x-ui.card>
</div>
