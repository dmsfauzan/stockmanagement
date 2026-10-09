<div>
    <x-ui.page-header title="Pick List" subtitle="Daftar picking untuk pengeluaran barang">
        <x-slot:actions>
            @can('picking.create')
                <a href="{{ route('picking.create') }}" class="app-btn app-btn-primary">{{ __('Buat Pick List') }}</a>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card padding="p-0">
        <div class="app-card-header flex-col items-stretch gap-3 sm:flex-row">
            <div class="flex flex-1 flex-wrap gap-3">
                <label class="relative block w-full sm:max-w-xs">
                    <svg class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-app-muted" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.3-4.3M10 18a8 8 0 110-16 8 8 0 010 16z"/></svg>
                    <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari nomor..." class="app-input pl-9">
                </label>
                <select wire:model.live="statusFilter" class="app-select sm:w-auto">
                    <option value="">{{ __('Semua Status') }}</option>
                    @foreach (\App\Enums\PickStatus::cases() as $status)
                        <option value="{{ $status->value }}">{{ $status->label() }}</option>
                    @endforeach
                </select>
                <select wire:model.live="warehouseFilter" class="app-select sm:w-auto">
                    <option value="">{{ __('Semua Warehouse') }}</option>
                    @foreach ($warehouses as $w)
                        <option value="{{ $w->id }}">{{ $w->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="app-table">
                <thead><tr><th>Nomor</th><th>Warehouse</th><th>Barang Keluar</th><th>Status</th><th>Assignee</th><th></th></tr></thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td class="font-medium text-app-text">{{ $row->number }}</td>
                            <td class="text-app-muted">{{ $row->warehouse?->name ?? '-' }}</td>
                            <td class="text-app-muted">{{ $row->goodsIssue?->number ?? '-' }}</td>
                            <td><x-ui.status-badge :status="$row->status instanceof \App\Enums\PickStatus ? $row->status->value : $row->status" /></td>
                            <td class="text-app-muted">{{ $row->assignee?->name ?? '-' }}</td>
                            <td class="text-right"><a href="{{ route('picking.show', $row) }}" class="app-btn app-btn-ghost !py-1.5 text-xs">{{ __('Lihat') }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-ui.empty-state title="Belum ada pick list" message="Buat pick list dari barang keluar yang sudah disetujui." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($rows->hasPages())
            <div class="border-t border-app-border px-4 py-3">{{ $rows->links() }}</div>
        @endif
    </x-ui.card>
</div>
