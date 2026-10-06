<div>
    <x-ui.page-header title="Barang Keluar" subtitle="Daftar transaksi pengeluaran barang">
        <x-slot:actions>
            <button type="button" wire:click="export" class="app-btn app-btn-secondary gap-2">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                Export
            </button>
            @can('create', App\Models\GoodsIssue::class)
                <a href="{{ route('goods-issues.create') }}" class="app-btn app-btn-primary">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                    Buat Barang Keluar
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
                        <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari number / destination / SO..." class="app-input pl-9">
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
                        <th>Customer</th>
                        <th>Destination</th>
                        <th>Warehouse</th>
                        <th>Status</th>
                        <th class="text-right">Items</th>
                        <th>Created</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($issues as $issue)
                        <tr>
                            <td class="whitespace-nowrap font-medium text-app-text">{{ $issue->number }}</td>
                            <td class="whitespace-nowrap text-app-muted">{{ $issue->transaction_date?->format('d M Y') }}</td>
                            <td class="whitespace-nowrap text-app-text">{{ $issue->customer?->name ?? '-' }}</td>
                            <td class="text-app-muted">{{ $issue->destination }}</td>
                            <td class="whitespace-nowrap text-app-muted">{{ $issue->warehouse?->name ?? '-' }}</td>
                            <td class="whitespace-nowrap"><x-ui.status-badge :status="$issue->status" /></td>
                            <td class="whitespace-nowrap text-right text-app-muted">{{ $issue->issue_items_count }}</td>
                            <td class="whitespace-nowrap text-app-muted">{{ $issue->created_at?->format('d M Y H:i') }}</td>
                            <td class="whitespace-nowrap text-right">
                                <a href="{{ route('goods-issues.show', $issue) }}" class="app-btn app-btn-secondary px-2.5 py-1.5 text-xs">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9">
                                <x-ui.empty-state title="Tidak ada barang keluar" message="Belum ada transaksi yang cocok dengan filter." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($issues->hasPages())
            <div class="border-t border-app-border px-4 py-3">
                {{ $issues->links() }}
            </div>
        @endif
    </x-ui.card>
</div>
