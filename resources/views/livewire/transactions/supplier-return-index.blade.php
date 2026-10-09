<div>
    <x-ui.page-header title="Retur Pembelian" subtitle="Barang dikembalikan ke supplier (stok keluar)">
        <x-slot:actions>
            <button type="button" wire:click="export" class="app-btn app-btn-secondary gap-2">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                {{ __('Ekspor') }}
            </button>
            @can('create', \App\Models\SupplierReturn::class)
                <a href="{{ route('supplier-returns.create') }}" class="app-btn app-btn-primary gap-2">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                    Buat Retur
                </a>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card padding="p-0">
        <div class="border-b border-app-border p-4">
            <div class="flex flex-wrap items-center gap-3">
                <label class="relative block w-full sm:max-w-xs">
                    <svg class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-app-muted" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.3-4.3M10 18a8 8 0 110-16 8 8 0 010 16z"/></svg>
                    <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari nomor..." class="app-input pl-9">
                </label>
                <select wire:model.live="statusFilter" class="app-select w-auto">
                    <option value="">{{ __('Semua Status') }}</option>
                    @foreach (['draft','submitted','approved','rejected','posted'] as $s)
                        <option value="{{ $s }}">{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
                <select wire:model.live="perPage" class="app-select w-auto">
                    @foreach ([10,25,50] as $n)<option value="{{ $n }}">{{ $n }}</option>@endforeach
                </select>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="app-table">
                <thead><tr><th>Number</th><th>{{ __('Tanggal') }}</th><th>{{ __('Supplier') }}</th><th>{{ __('Gudang') }}</th><th class="text-right">Qty</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse ($returns as $row)
                        <tr>
                            <td class="whitespace-nowrap font-medium"><a href="{{ route('supplier-returns.show', $row) }}" class="text-primary-600 hover:underline dark:text-primary-400">{{ $row->number }}</a></td>
                            <td class="whitespace-nowrap text-app-muted">{{ $row->transaction_date?->format('d M Y') }}</td>
                            <td class="text-app-text">{{ $row->supplier?->name ?? '-' }}</td>
                            <td class="text-app-muted">{{ $row->warehouse?->name ?? '-' }}</td>
                            <td class="whitespace-nowrap text-right text-app-muted">{{ number_format($row->items->sum('quantity')) }}</td>
                            <td class="whitespace-nowrap"><x-ui.status-badge :status="$row->status" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-ui.empty-state title="Belum ada retur" message="Belum ada retur pembelian." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($returns->hasPages())
            <div class="border-t border-app-border px-4 py-3">{{ $returns->links() }}</div>
        @endif
    </x-ui.card>
</div>
