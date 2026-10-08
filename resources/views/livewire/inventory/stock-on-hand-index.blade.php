<div>
    <x-ui.page-header title="Stock On Hand" subtitle="Saldo stok per item / gudang / lokasi">
        <x-slot:actions>
            <button type="button" wire:click="export" class="app-btn app-btn-primary">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                Export CSV
            </button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card padding="p-0">
        <div class="border-b border-app-border p-4">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-6">
                <label class="relative block lg:col-span-2">
                    <svg class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-app-muted" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.3-4.3M10 18a8 8 0 110-16 8 8 0 010 16z"/></svg>
                    <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari SKU / barcode / nama..." class="app-input pl-9">
                </label>
                <select wire:model.live="warehouseFilter" class="app-select">
                    <option value="">{{ __('Semua Warehouse') }}</option>
                    @foreach ($warehouses as $w)
                        <option value="{{ $w->id }}">{{ $w->name }}</option>
                    @endforeach
                </select>
                <select wire:model.live="categoryFilter" class="app-select">
                    <option value="">Semua Kategori</option>
                    @foreach ($categories as $c)
                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                    @endforeach
                </select>
                <select wire:model.live="locationFilter" class="app-select">
                    <option value="">Semua Location</option>
                    @foreach ($locations as $loc)
                        <option value="{{ $loc->id }}">{{ $loc->code }} — {{ $loc->name }}</option>
                    @endforeach
                </select>
                <select wire:model.live="statusFilter" class="app-select">
                    <option value="">{{ __('Semua Status') }}</option>
                    <option value="normal">Normal</option>
                    <option value="low">Low Stock</option>
                    <option value="out">Out of Stock</option>
                    <option value="over">Overstock</option>
                </select>
            </div>
            <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2 text-xs text-app-muted">
                    <span>Total On Hand (filtered): <strong class="text-app-text">{{ number_format($totals['on_hand']) }}</strong></span>
                    <span class="text-app-border">|</span>
                    <span>Total Available: <strong class="text-app-text">{{ number_format($totals['available']) }}</strong></span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-xs text-app-muted">Sort</span>
                    <select wire:model.live="sortField" class="app-select w-auto py-1.5">
                        <option value="sku">SKU</option>
                        <option value="on_hand">On Hand</option>
                    </select>
                    <select wire:model.live="sortDirection" class="app-select w-auto py-1.5">
                        <option value="asc">Asc</option>
                        <option value="desc">Desc</option>
                    </select>
                    <span class="text-xs text-app-muted">{{ __('Per halaman') }}</span>
                    <select wire:model.live="perPage" class="app-select w-auto py-1.5">
                        @foreach ([10, 25, 50, 100] as $s)
                            <option value="{{ $s }}">{{ $s }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="app-table">
                <thead>
                    <tr>
                        <th><button type="button" wire:click="sortBy('sku')" class="inline-flex items-center gap-1 hover:text-app-text">SKU @if($sortField==='sku')<span>{{ $sortDirection==='asc'?'↑':'↓' }}</span>@endif</button></th>
                        <th>Item</th>
                        <th>{{ __('Kategori') }}</th>
                        <th>Warehouse</th>
                        <th>Location</th>
                        <th class="text-right"><button type="button" wire:click="sortBy('on_hand')" class="inline-flex items-center gap-1 hover:text-app-text">On Hand @if($sortField==='on_hand')<span>{{ $sortDirection==='asc'?'↑':'↓' }}</span>@endif</button></th>
                        <th class="text-right">Reserved</th>
                        <th class="text-right">Available</th>
                        <th class="text-right">Min</th>
                        <th class="text-right">Max</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td class="whitespace-nowrap font-medium text-app-text">{{ $row->sku }}</td>
                            <td class="text-app-text">{{ $row->item_name }}</td>
                            <td class="whitespace-nowrap text-app-muted">{{ $row->category_name }}</td>
                            <td class="whitespace-nowrap text-app-muted">{{ $row->warehouse_name }}</td>
                            <td class="text-app-muted">{{ $row->location_path }}</td>
                            <td class="whitespace-nowrap text-right font-medium text-app-text">{{ number_format($row->quantity_on_hand) }}</td>
                            <td class="whitespace-nowrap text-right text-app-muted">{{ number_format($row->quantity_reserved) }}</td>
                            <td class="whitespace-nowrap text-right text-app-muted">{{ number_format($row->quantity_available) }}</td>
                            <td class="whitespace-nowrap text-right text-app-muted">{{ number_format($row->min_stock) }}</td>
                            <td class="whitespace-nowrap text-right text-app-muted">{{ number_format($row->max_stock) }}</td>
                            <td class="whitespace-nowrap"><x-ui.status-badge :status="$row->stock_status" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="11"><x-ui.empty-state title="Tidak ada stok" message="Tidak ada data yang cocok dengan filter." /></td></tr>
                    @endforelse
                </tbody>
                @if ($rows->count() > 0)
                    <tfoot>
                        <tr class="bg-app-surface-2 font-semibold">
                            <td colspan="5" class="px-4 py-3 text-right text-app-muted">Totals (filtered):</td>
                            <td class="px-4 py-3 text-right text-app-text">{{ number_format($totals['on_hand']) }}</td>
                            <td></td>
                            <td class="px-4 py-3 text-right text-app-text">{{ number_format($totals['available']) }}</td>
                            <td colspan="3"></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
        @if ($rows->hasPages())
            <div class="border-t border-app-border px-4 py-3">{{ $rows->links() }}</div>
        @endif
    </x-ui.card>
</div>
