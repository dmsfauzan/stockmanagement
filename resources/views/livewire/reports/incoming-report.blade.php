<div>
    <x-ui.page-header title="Laporan Barang Masuk" subtitle="Transaksi barang masuk yang telah diposting">
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

    <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
        <x-ui.stat-card label="Total Qty" :value="number_format($totals['qty'])" tone="emerald" />
        <x-ui.stat-card label="Baris Detail" :value="number_format($totals['rows'])" tone="slate" />
    </div>

    <x-ui.card padding="p-0">
        <div class="border-b border-app-border p-4">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <div class="relative">
                    <svg class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-app-muted" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.3-4.3M10 18a8 8 0 110-16 8 8 0 010 16z"/></svg>
                    <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari nomor / SKU / nama..." class="app-input pl-9">
                </div>
                <select wire:model.live="supplierFilter" class="app-select">
                    <option value="">Semua Supplier</option>
                    @foreach ($suppliers as $supplier)<option value="{{ $supplier->id }}">{{ $supplier->name }}</option>@endforeach
                </select>
                <select wire:model.live="warehouseFilter" class="app-select">
                    <option value="">Semua Warehouse</option>
                    @foreach ($warehouses as $warehouse)<option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>@endforeach
                </select>
                <input type="date" wire:model.live="fromDate" class="app-input">
                <input type="date" wire:model.live="toDate" class="app-input">
            </div>
            <div class="mt-3 flex items-center justify-between">
                <button type="button" wire:click="resetFilters" class="app-btn app-btn-ghost">Reset Filter</button>
                <div class="flex items-center gap-3">
                    <select wire:model.live="perPage" class="app-select w-24">
                        @foreach ([15, 25, 50, 100] as $size)<option value="{{ $size }}">{{ $size }}</option>@endforeach
                    </select>
                    @include('livewire.reports._report-columns')
                </div>
            </div>
            <div class="mt-3">
                @include('livewire.reports._saved-filters')
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="app-table">
                <thead>
                    <tr>
                        <th class="rc-number">Receipt No</th>
                        <th class="rc-date">Date</th>
                        <th class="rc-supplier">Supplier</th>
                        <th class="rc-warehouse">Warehouse</th>
                        <th class="rc-sku">SKU</th>
                        <th class="rc-item">Item</th>
                        <th class="rc-quantity text-right">Qty</th>
                        <th class="rc-batch">Batch</th>
                        <th class="rc-expiry">Expiry</th>
                        <th class="rc-posted_at">Posted At</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td class="rc-number whitespace-nowrap font-medium">{{ $row->receipt_number }}</td>
                            <td class="rc-date whitespace-nowrap text-app-muted">{{ $row->transaction_date instanceof \Illuminate\Support\Carbon ? $row->transaction_date->format('d M Y') : $row->transaction_date }}</td>
                            <td class="rc-supplier whitespace-nowrap text-app-muted">{{ $row->supplier_name ?? '-' }}</td>
                            <td class="rc-warehouse whitespace-nowrap text-app-muted">{{ $row->warehouse_name }}</td>
                            <td class="rc-sku whitespace-nowrap font-medium">{{ $row->sku }}</td>
                            <td class="rc-item">{{ $row->item_name }}</td>
                            <td class="rc-quantity whitespace-nowrap text-right font-medium">{{ number_format((int) $row->qty) }}</td>
                            <td class="rc-batch whitespace-nowrap text-app-muted">{{ $row->batch_number ?? '-' }}</td>
                            <td class="rc-expiry whitespace-nowrap text-app-muted">{{ $row->expiry_date ?? '-' }}</td>
                            <td class="rc-posted_at whitespace-nowrap text-app-muted">{{ $row->posted_at ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="10"><x-ui.empty-state title="Tidak ada data barang masuk" message="Belum ada transaksi yang cocok dengan filter." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($rows->hasPages())<div class="border-t border-app-border px-4 py-3">{{ $rows->links() }}</div>@endif
    </x-ui.card>
</div>
