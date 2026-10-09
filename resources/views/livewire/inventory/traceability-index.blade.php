<div>
    <x-ui.page-header title="Lacak Lot / Serial" subtitle="Telusuri riwayat lengkap satu batch atau serial number" />

    <x-ui.card class="mb-4">
        <form wire:submit="search" class="flex flex-wrap items-end gap-3">
            <label>
                <span class="app-label">{{ __('Tipe') }}</span>
                <select wire:model="mode" class="app-select sm:w-auto">
                    <option value="batch">Batch / Lot</option>
                    <option value="serial">Serial Number</option>
                </select>
            </label>
            <label class="min-w-[16rem] flex-1">
                <span class="app-label">{{ __('Nomor') }}</span>
                <input type="text" wire:model="query" class="app-input" placeholder="{{ $mode === 'serial' ? 'SN-...' : 'BATCH-...' }}">
            </label>
            <button type="submit" class="app-btn app-btn-primary">{{ __('Telusuri') }}</button>
        </form>
    </x-ui.card>

    @if ($result)
        <div class="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-4">
            <div class="app-card p-4"><p class="text-xs font-semibold uppercase text-app-muted">Event</p><p class="mt-2 text-2xl font-semibold text-app-text">{{ number_format($result['summary']['events']) }}</p></div>
            <div class="app-card p-4"><p class="text-xs font-semibold uppercase text-app-muted">Total Masuk</p><p class="mt-2 text-2xl font-semibold text-emerald-600 dark:text-emerald-400">{{ number_format($result['summary']['in']) }}</p></div>
            <div class="app-card p-4"><p class="text-xs font-semibold uppercase text-app-muted">Total Keluar</p><p class="mt-2 text-2xl font-semibold text-rose-600 dark:text-rose-400">{{ number_format($result['summary']['out']) }}</p></div>
            <div class="app-card p-4"><p class="text-xs font-semibold uppercase text-app-muted">Saldo</p><p class="mt-2 text-2xl font-semibold text-app-text">{{ number_format($result['summary']['balance']) }}</p></div>
        </div>

        <x-ui.card padding="p-0">
            <div class="overflow-x-auto">
                <table class="app-table">
                    <thead>
                        <tr>
                            <th>{{ __('Tanggal') }}</th>
                            <th>Type</th>
                            <th>Barang</th>
                            <th>Warehouse / Lokasi</th>
                            <th class="text-right">In</th>
                            <th class="text-right">Out</th>
                            <th>Referensi</th>
                            <th>User</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($result['events'] as $event)
                            <tr>
                                <td class="whitespace-nowrap text-app-muted">{{ $event['date'] ? \Illuminate\Support\Carbon::parse($event['date'])->format('d M Y H:i') : '-' }}</td>
                                <td><x-ui.status-badge :status="$event['transaction_type']" /></td>
                                <td class="text-app-text">{{ $event['sku'] }} — {{ $event['item_name'] }}</td>
                                <td class="text-app-muted">{{ $event['warehouse'] }}{{ $event['location'] ? ' / '.$event['location'] : '' }}</td>
                                <td class="text-right text-emerald-600 dark:text-emerald-400">{{ $event['quantity_in'] > 0 ? number_format($event['quantity_in']) : '-' }}</td>
                                <td class="text-right text-rose-600 dark:text-rose-400">{{ $event['quantity_out'] > 0 ? number_format($event['quantity_out']) : '-' }}</td>
                                <td class="text-app-muted">{{ $event['reference_label'] }}{{ $event['reference_number'] ? ' '.$event['reference_number'] : '' }}</td>
                                <td class="text-app-muted">{{ $event['user'] ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8"><x-ui.empty-state title="Tidak ada riwayat" message="Tidak ada pergerakan untuk nomor ini." /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-ui.card>
    @endif
</div>
