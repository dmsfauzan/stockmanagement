<div>
    <x-ui.page-header title="Laporan Stok" subtitle="Posisi stok per barang, gudang, dan lokasi">
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
        <x-ui.stat-card label="Total On Hand" :value="number_format($totals['on_hand'])" tone="indigo" />
        <x-ui.stat-card label="Total Available" :value="number_format($totals['available'])" tone="emerald" />
        <x-ui.stat-card label="Baris Data" :value="number_format($totals['rows'])" tone="slate" />
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
                    <select wire:model.live="locationFilter" class="app-select">
                        <option value="">Semua Lokasi</option>
                        @foreach ($locations as $location)
                            <option value="{{ $location->id }}">{{ $location->code }}</option>
                        @endforeach
                    </select>
                    <select wire:model.live="categoryFilter" class="app-select">
                        <option value="">Semua Kategori</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-center gap-3">
                    <select wire:model.live="statusFilter" class="app-select">
                        <option value="">Semua Status</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}">{{ $status->label() }}</option>
                        @endforeach
                    </select>
                    <select wire:model.live="perPage" class="app-select w-24">
                        @foreach ([15, 25, 50, 100] as $size)
                            <option value="{{ $size }}">{{ $size }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="app-table">
                <thead>
                    <tr>
                        <th>
                            <button type="button" wire:click="sortBy('sku')" class="inline-flex items-center gap-1 hover:text-app-text">
                                SKU @if ($sortField === 'sku')<span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif
                            </button>
                        </th>
                        <th>Barang</th>
                        <th>Kategori</th>
                        <th>Warehouse</th>
                        <th>Lokasi</th>
                        <th class="text-right">
                            <button type="button" wire:click="sortBy('on_hand')" class="inline-flex items-center gap-1 hover:text-app-text">
                                On Hand @if ($sortField === 'on_hand')<span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>@endif
                            </button>
                        </th>
                        <th class="text-right">Min</th>
                        <th class="text-right">Max</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td class="whitespace-nowrap font-medium">{{ $row->sku }}</td>
                            <td>{{ $row->item_name }}</td>
                            <td class="whitespace-nowrap text-app-muted">{{ $row->category_name }}</td>
                            <td class="whitespace-nowrap text-app-muted">{{ $row->warehouse_name }}</td>
                            <td class="text-app-muted">{{ $row->location_path }}</td>
                            <td class="whitespace-nowrap text-right font-medium">{{ number_format((int) $row->quantity_on_hand) }}</td>
                            <td class="whitespace-nowrap text-right text-app-muted">{{ (int) $row->min_stock }}</td>
                            <td class="whitespace-nowrap text-right text-app-muted">{{ (int) $row->max_stock }}</td>
                            <td class="whitespace-nowrap">
                                <x-ui.status-badge :status="$row->stock_status" :label="$row->stock_status->label()" />
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9"><x-ui.empty-state title="Tidak ada data stok" message="Belum ada data yang cocok dengan filter." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($rows->hasPages())
            <div class="border-t border-app-border px-4 py-3">{{ $rows->links() }}</div>
        @endif
    </x-ui.card>
</div>
