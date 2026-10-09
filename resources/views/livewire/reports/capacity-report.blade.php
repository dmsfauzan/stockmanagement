<div>
    <x-ui.page-header title="Kapasitas Gudang" subtitle="Utilisasi BIN dan saran penempatan barang">
        <x-slot:actions>
                    <button type="button" wire:click="exportCsv" class="app-btn app-btn-secondary">{{ __('Export CSV') }}</button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card padding="p-0">
        <div class="app-card-header flex-col items-stretch gap-3 sm:flex-row">
            <div class="flex flex-1 flex-wrap items-center gap-3">
                <select wire:model.live="warehouseFilter" class="app-select sm:w-auto">
                    <option value="">{{ __('Semua Warehouse') }}</option>
                    @foreach ($warehouses as $w)
                        <option value="{{ $w->id }}">{{ $w->name }}</option>
                    @endforeach
                </select>
                <label class="flex items-center gap-2 text-sm text-app-text">
                    <input type="checkbox" wire:model.live="onlyConstrained" class="rounded border-app-border">
                    {{ __('Hanya yang padat (>= 80%)') }}
                </label>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="app-table">
                <thead><tr><th>{{ __('Lokasi') }}</th><th>Warehouse</th><th class="text-right">Kapasitas</th><th class="text-right">Terpakai</th><th class="text-right">Sisa</th><th>{{ __('Utilisasi') }}</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td class="text-app-text">{{ $row['location_path'] }}</td>
                            <td class="text-app-muted">{{ $row['warehouse_name'] }}</td>
                            <td class="text-right">{{ number_format($row['capacity']) }}</td>
                            <td class="text-right">{{ number_format($row['used']) }}</td>
                            <td class="text-right">{{ number_format($row['free']) }}</td>
                            <td>
                                <div class="flex items-center gap-2">
                                    <div class="h-2 w-24 overflow-hidden rounded-full bg-app-surface-2">
                                        <div @class(['h-full rounded-full', 'bg-rose-500' => $row['percent'] >= 100, 'bg-amber-500' => $row['percent'] >= 80 && $row['percent'] < 100, 'bg-emerald-500' => $row['percent'] < 80]) style="width: {{ min(100, $row['percent']) }}%"></div>
                                    </div>
                                    <span class="text-xs text-app-muted">{{ $row['percent'] }}%</span>
                                </div>
                            </td>
                            <td><x-ui.status-badge :status="$row['status']" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-ui.empty-state title="Belum ada kapasitas" message="Atur kapasitas pada lokasi untuk melihat utilisasi." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-ui.card>
</div>
