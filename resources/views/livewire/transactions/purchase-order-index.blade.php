<div>
    <x-ui.page-header title="Purchase Order" subtitle="Daftar pesanan pembelian ke supplier">
        <x-slot:actions>
            @can('create', App\Models\PurchaseOrder::class)
                <a href="{{ route('purchase-orders.create') }}" class="app-btn app-btn-primary">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                    Buat PO
                </a>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card padding="p-0">
        <div class="app-card-header flex-col items-stretch gap-3">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex flex-1 flex-col gap-3 sm:flex-row sm:flex-wrap">
                    <label class="relative block w-full sm:max-w-xs">
                        <svg class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-app-muted" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.3-4.3M10 18a8 8 0 110-16 8 8 0 010 16z"/></svg>
                        <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari number / supplier..." class="app-input pl-9">
                    </label>
                    <select wire:model.live="statusFilter" class="app-select sm:w-auto">
                        <option value="">Semua Status</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status }}">{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                    <select wire:model.live="warehouseFilter" class="app-select sm:w-auto">
                        <option value="">Semua Warehouse</option>
                        @foreach ($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-xs text-app-muted">Per halaman</span>
                    <select wire:model.live="perPage" class="app-select w-auto">
                        @foreach ([10, 25, 50, 100] as $size)
                            <option value="{{ $size }}">{{ $size }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <label class="flex items-center gap-2 text-sm">
                    <span class="text-app-muted">Dari</span>
                    <input type="date" wire:model.live="dateFrom" class="app-input w-auto">
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <span class="text-app-muted">Sampai</span>
                    <input type="date" wire:model.live="dateTo" class="app-input w-auto">
                </label>
                @if($dateFrom || $dateTo)
                    <button type="button" wire:click="$set('dateFrom',''); $set('dateTo','')" class="text-xs font-medium app-link">Reset tanggal</button>
                @endif
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="app-table">
                <thead>
                    <tr>
                        <th>Number</th>
                        <th>
                            <button type="button" wire:click="sortByDate" class="inline-flex items-center gap-1 hover:text-app-text">
                                Date
                                <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                            </button>
                        </th>
                        <th>Supplier</th>
                        <th>Warehouse</th>
                        <th class="text-right">Items</th>
                        <th>Received / Total</th>
                        <th>Status</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($orders as $order)
                        @php
                            $total = (int) ($order->total_quantity ?? 0);
                            $received = (int) ($order->total_received ?? 0);
                            $pct = $total > 0 ? round($received / $total * 100) : 0;
                        @endphp
                        <tr>
                            <td class="whitespace-nowrap font-medium text-app-text">{{ $order->number }}</td>
                            <td class="whitespace-nowrap text-app-muted">{{ $order->order_date?->format('d M Y') }}</td>
                            <td class="whitespace-nowrap text-app-text">{{ $order->supplier?->name ?? '-' }}</td>
                            <td class="whitespace-nowrap text-app-muted">{{ $order->warehouse?->name ?? '-' }}</td>
                            <td class="whitespace-nowrap text-right text-app-muted">{{ $order->items_count }}</td>
                            <td class="whitespace-nowrap">
                                <div class="text-xs font-medium text-app-text">{{ number_format($received) }} / {{ number_format($total) }}</div>
                                <div class="mt-1 h-1.5 w-32 overflow-hidden rounded-full bg-app-surface-2">
                                    <div class="h-full rounded-full bg-primary-500" style="width: {{ min(100, $pct) }}%"></div>
                                </div>
                            </td>
                            <td class="whitespace-nowrap"><x-ui.status-badge :status="$order->status" /></td>
                            <td class="whitespace-nowrap text-right">
                                <a href="{{ route('purchase-orders.show', $order) }}" class="app-btn app-btn-secondary px-2.5 py-1.5 text-xs">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <x-ui.empty-state title="Tidak ada purchase order" message="Belum ada PO yang cocok dengan filter." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($orders->hasPages())
            <div class="border-t border-app-border px-4 py-3">
                {{ $orders->links() }}
            </div>
        @endif
    </x-ui.card>
</div>
