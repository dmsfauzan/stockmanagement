<div>
    <x-ui.page-header title="Prakiraan Permintaan" subtitle="Proyeksi kebutuhan & safety stock dari riwayat pergerakan">
        <x-slot:actions>
            <button type="button" wire:click="exportCsv" class="app-btn app-btn-secondary">{{ __('Export CSV') }}</button>
            <button type="button" wire:click="exportExcel" class="app-btn app-btn-primary">{{ __('Export Excel') }}</button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card class="mb-4">
        <div class="flex flex-wrap items-center gap-3">
            <select wire:model.live="warehouseFilter" class="app-select sm:w-auto">
                <option value="">{{ __('Semua Warehouse') }}</option>
                @foreach ($warehouses as $w)
                    <option value="{{ $w->id }}">{{ $w->name }}</option>
                @endforeach
            </select>
            <span class="text-xs text-app-muted">{{ __('Service level') }} {{ (int) round($serviceLevel * 100) }}% · {{ __('Lead time') }} {{ $leadTime }} {{ __('hari') }}</span>
        </div>
    </x-ui.card>

    @if ($detail)
        <x-ui.card class="mb-4">
            <div class="flex items-center justify-between">
                <h2 class="app-card-title">{{ $detailItem?->sku }} — {{ $detailItem?->name }}</h2>
                <button type="button" wire:click="closeDetail" class="app-btn app-btn-ghost !p-1.5">&times;</button>
            </div>
            <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-4">
                <div class="app-card p-4"><p class="text-xs font-semibold uppercase text-app-muted">Rata-rata/hari</p><p class="mt-2 text-xl font-semibold text-app-text">{{ number_format($detail['daily_mean'], 2) }}</p></div>
                <div class="app-card p-4"><p class="text-xs font-semibold uppercase text-app-muted">Safety Stock</p><p class="mt-2 text-xl font-semibold text-app-text">{{ number_format($detail['safety_stock']) }}</p></div>
                <div class="app-card p-4"><p class="text-xs font-semibold uppercase text-app-muted">Reorder Point</p><p class="mt-2 text-xl font-semibold text-amber-600 dark:text-amber-400">{{ number_format($detail['reorder_point']) }}</p></div>
                <div class="app-card p-4"><p class="text-xs font-semibold uppercase text-app-muted">Proyeksi {{ $detail['horizon_days'] }} hari</p><p class="mt-2 text-xl font-semibold text-app-text">{{ number_format($detail['expected_total'], 0) }}</p></div>
            </div>
        </x-ui.card>
    @endif

    <x-ui.card padding="p-0">
        <div class="app-card-header">
            <h2 class="app-card-title">{{ __('Kebutuhan Tertinggi') }}</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="app-table">
                <thead><tr><th>SKU</th><th>Item</th><th class="text-right">On Hand</th><th class="text-right">Pakai 30 hari</th><th class="text-right">Rata/hari</th><th class="text-right">Cover (hari)</th><th></th></tr></thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td class="font-medium text-app-text">{{ $row['sku'] }}</td>
                            <td class="text-app-text">{{ $row['item_name'] }} @if ($row['below_min'])<span class="ml-1 rounded bg-rose-100 px-1.5 py-0.5 text-xs text-rose-700 dark:bg-rose-900/40 dark:text-rose-300">{{ __('Di bawah min') }}</span>@endif</td>
                            <td class="text-right">{{ number_format($row['on_hand']) }}</td>
                            <td class="text-right">{{ number_format($row['usage_30']) }}</td>
                            <td class="text-right text-app-muted">{{ number_format($row['daily_mean'], 2) }}</td>
                            <td class="text-right {{ ($row['days_cover'] !== null && $row['days_cover'] < $leadTime) ? 'font-semibold text-rose-600 dark:text-rose-400' : '' }}">{{ $row['days_cover'] !== null ? number_format($row['days_cover'], 1) : '-' }}</td>
                            <td class="text-right"><button type="button" wire:click="showDetail({{ $row['item_id'] }})" class="app-btn app-btn-ghost !py-1.5 text-xs">{{ __('Detail') }}</button></td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-ui.empty-state title="Belum ada data pemakaian" message="Belum ada transaksi keluar untuk diproyeksikan." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-ui.card>
</div>
