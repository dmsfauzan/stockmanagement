<div>
    <x-ui.page-header title="Replenishment" subtitle="Saran pembelian dari minimum/maximum stok">
        <x-slot:actions>
            @can('reports.export')
                <x-ui.button variant="secondary" wire:click="exportCsv">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                    Export CSV
                </x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
        <x-ui.stat-card label="Baris Perlu Restock" :value="number_format($totals['rows'])" tone="amber" />
        <x-ui.stat-card label="Total Suggested Qty" :value="number_format($totals['suggested'])" tone="indigo" />
        <x-ui.stat-card label="Dipilih" :value="number_format(count($selected))" tone="slate" />
    </div>

    <x-ui.card padding="p-0">
        <div class="border-b border-app-border p-4">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div class="grid flex-1 grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="relative">
                        <svg class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-app-muted" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.3-4.3M10 18a8 8 0 110-16 8 8 0 010 16z"/></svg>
                        <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari SKU / nama barang..." class="app-input pl-9">
                    </div>
                    <select wire:model.live="warehouseFilter" class="app-select">
                        <option value="">Semua Warehouse</option>
                        @foreach ($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                        @endforeach
                    </select>
                    <select wire:model.live="categoryFilter" class="app-select">
                        <option value="">Semua Kategori</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                    <select wire:model.live="perPage" class="app-select w-28">
                        @foreach ([15, 25, 50, 100] as $size)
                            <option value="{{ $size }}">{{ $size }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-center gap-2">
                    <x-ui.button variant="secondary" wire:click="resetFilters">Reset</x-ui.button>
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
                        <th class="w-10 text-center">
                            <input type="checkbox" wire:model.live="selectAll" class="rounded border-app-border">
                        </th>
                        <th class="rc-sku">SKU</th>
                        <th class="rc-item">Barang</th>
                        <th class="rc-category">Kategori</th>
                        <th class="rc-warehouse">Warehouse</th>
                        <th class="rc-on_hand text-right">On Hand</th>
                        <th class="rc-min text-right">Min</th>
                        <th class="rc-max text-right">Max</th>
                        <th class="rc-suggested text-right">Suggested</th>
                        <th class="rc-supplier">Primary Supplier</th>
                        <th class="rc-price text-right">Price</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td class="text-center">
                                <input type="checkbox" wire:model.live="selected" value="{{ $row['key'] }}" class="rounded border-app-border">
                            </td>
                            <td class="rc-sku whitespace-nowrap font-medium">{{ $row['sku'] }}</td>
                            <td class="rc-item">{{ $row['item_name'] }}</td>
                            <td class="rc-category whitespace-nowrap text-app-muted">{{ $row['category_name'] }}</td>
                            <td class="rc-warehouse whitespace-nowrap text-app-muted">{{ $row['warehouse_name'] }}</td>
                            <td class="rc-on_hand whitespace-nowrap text-right">{{ number_format((int) $row['on_hand']) }}</td>
                            <td class="rc-min whitespace-nowrap text-right text-app-muted">{{ number_format((int) $row['min_stock']) }}</td>
                            <td class="rc-max whitespace-nowrap text-right text-app-muted">{{ number_format((int) $row['max_stock']) }}</td>
                            <td class="rc-suggested whitespace-nowrap text-right font-bold text-amber-600 dark:text-amber-400">{{ number_format((int) $row['suggestedQty']) }}</td>
                            <td class="rc-supplier whitespace-nowrap text-app-muted">{{ $row['primary_supplier_name'] ?? '—' }}</td>
                            <td class="rc-price whitespace-nowrap text-right">{{ number_format((float) $row['best_price'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="11"><x-ui.empty-state title="Tidak ada saran replenishment" message="Semua stok masih di atas minimum atau belum ada data yang cocok." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="flex flex-col gap-3 border-t border-app-border p-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-1 flex-col gap-3 sm:flex-row sm:items-center">
                <select wire:model.live="supplierForPo" class="app-select sm:w-64">
                    <option value="">Supplier (otomatis / pilih)</option>
                    @foreach ($suppliers as $supplier)
                        <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                    @endforeach
                </select>
                <x-ui.button variant="primary" wire:click="createPoFromSelection" :disabled="empty($selected)">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/><path stroke-linecap="round" stroke-linejoin="round" d="M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    Buat PO dari yang dipilih
                </x-ui.button>
                <span class="text-sm text-app-muted">{{ count($selected) }} dipilih</span>
            </div>
            @if ($rows->hasPages())
                <div>{{ $rows->links() }}</div>
            @endif
        </div>
    </x-ui.card>
</div>
