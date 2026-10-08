<div>
    <x-ui.page-header title="Stock Movement" subtitle="Riwayat pergerakan stok">
        <x-slot:actions>
            <button type="button" wire:click="export" class="app-btn app-btn-primary">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                Export CSV
            </button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card padding="p-0">
        <div class="border-b border-app-border p-4">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <label class="relative block">
                    <svg class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-app-muted" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.3-4.3M10 18a8 8 0 110-16 8 8 0 010 16z"/></svg>
                    <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari SKU / nama..." class="app-input pl-9">
                </label>
                <select wire:model.live="warehouseFilter" class="app-select">
                    <option value="">{{ __('Semua Warehouse') }}</option>
                    @foreach ($warehouses as $w)
                        <option value="{{ $w->id }}">{{ $w->name }}</option>
                    @endforeach
                </select>
                <select wire:model.live="locationFilter" class="app-select">
                    <option value="">Semua Location</option>
                    @foreach ($locations as $loc)
                        <option value="{{ $loc->id }}">{{ $loc->code }} — {{ $loc->name }}</option>
                    @endforeach
                </select>
                <select wire:model.live="transactionTypeFilter" class="app-select">
                    <option value="">Semua Type</option>
                    <option value="opening">Opening</option>
                    <option value="incoming">Incoming</option>
                    <option value="outgoing">Outgoing</option>
                    <option value="transfer_in">Transfer In</option>
                    <option value="transfer_out">Transfer Out</option>
                    <option value="adjustment_in">Adjustment In</option>
                    <option value="adjustment_out">Adjustment Out</option>
                </select>
            </div>
            <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <select wire:model.live="userFilter" class="app-select">
                    <option value="">Semua User</option>
                    @foreach ($users as $u)
                        <option value="{{ $u->id }}">{{ $u->name }}</option>
                    @endforeach
                </select>
                <input type="date" wire:model.live="fromDate" class="app-input" placeholder="From">
                <input type="date" wire:model.live="toDate" class="app-input" placeholder="To">
                <div class="flex items-center gap-2">
                    <span class="text-xs text-app-muted">{{ __('Per halaman') }}</span>
                    <select wire:model.live="perPage" class="app-select flex-1">
                        @foreach ([10, 25, 50, 100] as $s)
                            <option value="{{ $s }}">{{ $s }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="button" wire:click="clearDates" class="app-btn app-btn-secondary">{{ __('Reset tanggal') }}</button>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="app-table">
                <thead>
                    <tr>
                        <th><button type="button" wire:click="sortBy('created_at')" class="inline-flex items-center gap-1 hover:text-app-text">Tanggal @if($sortField==='created_at')<span>{{ $sortDirection==='asc'?'↑':'↓' }}</span>@endif</button></th>
                        <th>Reference</th>
                        <th>Type</th>
                        <th><button type="button" wire:click="sortBy('sku')" class="inline-flex items-center gap-1 hover:text-app-text">SKU @if($sortField==='sku')<span>{{ $sortDirection==='asc'?'↑':'↓' }}</span>@endif</button></th>
                        <th>Item</th>
                        <th>Warehouse</th>
                        <th>Location</th>
                        <th class="text-right">Qty In</th>
                        <th class="text-right">Qty Out</th>
                        <th class="text-right">Balance After</th>
                        <th>User</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td class="whitespace-nowrap text-app-muted">{{ to_display_tz($row->created_at)?->format('d M Y H:i') }}</td>
                            <td class="whitespace-nowrap text-app-text">{{ $row->reference_type }}#{{ $row->reference_id }}</td>
                            <td class="whitespace-nowrap"><x-ui.status-badge :status="$row->transaction_type" /></td>
                            <td class="whitespace-nowrap font-medium text-app-text">{{ $row->sku ?? '-' }}</td>
                            <td class="text-app-text">{{ $row->item_name ?? '-' }}</td>
                            <td class="whitespace-nowrap text-app-muted">{{ $row->warehouse_name ?? '-' }}</td>
                            <td class="whitespace-nowrap text-app-muted">{{ $row->location_code ?? '-' }}</td>
                            <td class="whitespace-nowrap text-right text-emerald-600 dark:text-emerald-400">{{ $row->quantity_in > 0 ? number_format($row->quantity_in) : '-' }}</td>
                            <td class="whitespace-nowrap text-right text-rose-600 dark:text-rose-400">{{ $row->quantity_out > 0 ? number_format($row->quantity_out) : '-' }}</td>
                            <td class="whitespace-nowrap text-right font-medium text-app-text">{{ number_format($row->balance_after) }}</td>
                            <td class="whitespace-nowrap text-app-muted">{{ $row->user_name ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="11"><x-ui.empty-state title="Tidak ada movement" message="Belum ada pergerakan stok yang cocok dengan filter." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($rows->hasPages())
            <div class="border-t border-app-border px-4 py-3">{{ $rows->links() }}</div>
        @endif
    </x-ui.card>
</div>
