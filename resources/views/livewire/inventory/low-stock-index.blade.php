<div>
    <x-ui.page-header title="Low Stock" subtitle="Item yang mencapai / di bawah minimum stock">
        <x-slot:actions>
            <span class="app-badge border border-rose-200 bg-rose-50 px-3 py-1.5 text-sm font-semibold text-rose-700 dark:border-rose-800 dark:bg-rose-900/30 dark:text-rose-300">
                Out of Stock
                <span class="ml-1 rounded-full bg-rose-600 px-2 py-0.5 text-xs font-bold text-white dark:bg-rose-500">{{ number_format($badgeOut) }}</span>
            </span>
            <span class="app-badge border border-amber-200 bg-amber-50 px-3 py-1.5 text-sm font-semibold text-amber-700 dark:border-amber-800 dark:bg-amber-900/30 dark:text-amber-300">
                Low Stock
                <span class="ml-1 rounded-full bg-amber-500 px-2 py-0.5 text-xs font-bold text-white">{{ number_format($badgeLow) }}</span>
            </span>
            <button type="button" wire:click="export" class="app-btn app-btn-primary">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                Export CSV
            </button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card padding="p-0">
        <div class="app-card-header flex-col items-stretch sm:flex-row">
            <div class="flex flex-1 flex-col gap-3 sm:flex-row sm:items-center">
                <label class="relative block w-full sm:max-w-xs">
                    <svg class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-app-muted" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.3-4.3M10 18a8 8 0 110-16 8 8 0 010 16z"/></svg>
                    <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari SKU / barcode / nama..." class="app-input pl-9">
                </label>
                <select wire:model.live="warehouseFilter" class="app-select sm:w-auto">
                    <option value="">{{ __('Semua Warehouse') }}</option>
                    @foreach ($warehouses as $w)
                        <option value="{{ $w->id }}">{{ $w->name }}</option>
                    @endforeach
                </select>
                <select wire:model.live="categoryFilter" class="app-select sm:w-auto">
                    <option value="">Semua Kategori</option>
                    @foreach ($categories as $c)
                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-xs text-app-muted">{{ __('Per halaman') }}</span>
                <select wire:model.live="perPage" class="app-select w-auto">
                    @foreach ([10, 25, 50, 100] as $s)
                        <option value="{{ $s }}">{{ $s }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="app-table">
                <thead>
                    <tr>
                        <th>SKU</th>
                        <th>Item</th>
                        <th>Warehouse</th>
                        <th>Location</th>
                        <th class="text-right">Current Stock</th>
                        <th class="text-right">Minimum Stock</th>
                        <th class="text-right">Difference</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        @php $diff = (int) $row->quantity_on_hand - (int) $row->min_stock; @endphp
                        <tr>
                            <td class="whitespace-nowrap font-medium text-app-text">{{ $row->sku }}</td>
                            <td class="text-app-text">{{ $row->item_name }}</td>
                            <td class="whitespace-nowrap text-app-muted">{{ $row->warehouse_name }}</td>
                            <td class="whitespace-nowrap text-app-muted">{{ $row->location_code }}</td>
                            <td class="whitespace-nowrap text-right font-medium text-app-text">{{ number_format($row->quantity_on_hand) }}</td>
                            <td class="whitespace-nowrap text-right text-app-muted">{{ number_format($row->min_stock) }}</td>
                            <td class="whitespace-nowrap text-right font-semibold {{ $diff < 0 ? 'text-rose-600 dark:text-rose-400' : 'text-app-text' }}">{{ number_format($diff) }}</td>
                            <td class="whitespace-nowrap">
                                @if ((int) $row->quantity_on_hand <= 0)
                                    <x-ui.status-badge status="out" label="Out of Stock" />
                                @else
                                    <x-ui.status-badge status="low" label="Low Stock" />
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><x-ui.empty-state title="Tidak ada low stock" message="Semua stok berada di atas minimum." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($rows->hasPages())
            <div class="border-t border-app-border px-4 py-3">{{ $rows->links() }}</div>
        @endif
    </x-ui.card>
</div>
