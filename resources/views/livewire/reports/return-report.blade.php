<div>
    <x-ui.page-header title="Laporan Retur" subtitle="Retur penjualan & pembelian per periode">
        <x-slot:actions>
            <button type="button" wire:click="export" class="app-btn app-btn-secondary gap-2">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                {{ __('Ekspor') }}
            </button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card class="mb-4">
        <div class="flex flex-wrap items-end gap-3">
            <label><span class="app-label">{{ __('Tanggal Mulai') }}</span><input type="date" wire:model.live="fromDate" class="app-input"></label>
            <label><span class="app-label">{{ __('Tanggal Selesai') }}</span><input type="date" wire:model.live="toDate" class="app-input"></label>
            <label><span class="app-label">{{ __('Tipe') }}</span>
                <select wire:model.live="type" class="app-select">
                    <option value="all">{{ __('Semua') }}</option>
                    <option value="customer">Retur Penjualan</option>
                    <option value="supplier">Retur Pembelian</option>
                </select>
            </label>
        </div>
    </x-ui.card>

    <div class="mb-4 grid grid-cols-2 gap-3 sm:max-w-md">
        <x-ui.stat-card label="Total Qty" :value="number_format($totalQty)" tone="slate" />
        <x-ui.stat-card label="Total Nilai" :value="'Rp '.number_format($totalValue, 0, ',', '.')" tone="emerald" />
    </div>

    <x-ui.card padding="p-0">
        <div class="overflow-x-auto">
            <table class="app-table">
                <thead><tr><th>Number</th><th>Tipe</th><th class="rc-date">{{ __('Tanggal') }}</th><th>Pihak</th><th>{{ __('Gudang') }}</th><th class="text-right">Qty</th><th class="text-right">Nilai</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td class="whitespace-nowrap font-medium text-app-text">{{ $row['number'] }}</td>
                            <td class="whitespace-nowrap text-app-muted">{{ $row['type'] === 'customer' ? 'Penjualan' : 'Pembelian' }}</td>
                            <td class="rc-date whitespace-nowrap text-app-muted">{{ $row['date'] }}</td>
                            <td class="text-app-text">{{ $row['party'] }}</td>
                            <td class="text-app-muted">{{ $row['warehouse'] }}</td>
                            <td class="whitespace-nowrap text-right text-app-muted">{{ number_format($row['qty']) }}</td>
                            <td class="whitespace-nowrap text-right text-app-text">{{ number_format($row['value'], 0, ',', '.') }}</td>
                            <td class="whitespace-nowrap"><x-ui.status-badge :status="$row['status']" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><x-ui.empty-state title="Tidak ada data" message="Belum ada retur pada periode ini." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-ui.card>
</div>
