<div>
    <x-ui.page-header title="Karantina" subtitle="Stok yang ditahan untuk inspeksi mutu">
        <x-slot:actions>
            <span class="app-badge border border-amber-200 bg-amber-50 px-3 py-1.5 text-sm font-semibold text-amber-700 dark:border-amber-800 dark:bg-amber-900/30 dark:text-amber-300">
                {{ __('Total Karantina') }}
                <span class="ml-1 rounded-full bg-amber-500 px-2 py-0.5 text-xs font-bold text-white">{{ number_format($summary['total']) }}</span>
            </span>
            <span class="app-badge border border-app-border px-3 py-1.5 text-sm font-semibold text-app-muted">
                {{ number_format($summary['items']) }} {{ __('item') }}
            </span>
            <button type="button" wire:click="export" class="app-btn app-btn-primary">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                Export CSV
            </button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card padding="p-0">
        <div class="app-card-header flex-col items-stretch sm:flex-row">
            <div class="flex flex-1 flex-col gap-3 sm:flex-row sm:items-center">
                <label class="relative block w-full sm:max-w-xs">
                    <svg class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-app-muted" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.3-4.3M10 18a8 8 0 110-16 8 8 0 010 16z"/></svg>
                    <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari SKU / nama..." class="app-input pl-9">
                </label>
                <select wire:model.live="warehouseFilter" class="app-select sm:w-auto">
                    <option value="">{{ __('Semua Warehouse') }}</option>
                    @foreach ($warehouses as $w)
                        <option value="{{ $w->id }}">{{ $w->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-xs text-app-muted">{{ __('Per halaman') }}</span>
                <select wire:model.live="perPage" class="app-select w-auto">
                    @foreach ([10, 25, 50, 100] as $s)
                        <option value="{{ $s }}">{{ $s }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="app-table">
                <thead>
                    <tr>
                        <th>SKU</th>
                        <th>Item</th>
                        <th>Warehouse</th>
                        <th>Location</th>
                        <th class="text-right">On Hand</th>
                        <th class="text-right">Karantina</th>
                        <th class="text-right">Available</th>
                        <th>{{ __('Aksi') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr wire:key="q-{{ $row->id }}">
                            <td class="whitespace-nowrap font-medium text-app-text">{{ $row->sku }}</td>
                            <td class="text-app-text">{{ $row->item_name }}</td>
                            <td class="whitespace-nowrap text-app-muted">{{ $row->warehouse_name }}</td>
                            <td class="text-app-muted">{{ $row->location_path }}</td>
                            <td class="whitespace-nowrap text-right text-app-text">{{ number_format($row->quantity_on_hand) }}</td>
                            <td class="whitespace-nowrap text-right font-semibold text-amber-600 dark:text-amber-400">{{ number_format($row->quantity_quarantine) }}</td>
                            <td class="whitespace-nowrap text-right text-app-muted">{{ number_format($row->quantity_available) }}</td>
                            <td>
                                <div class="flex flex-wrap items-center gap-2">
                                    <input type="number" min="1" max="{{ $row->quantity_quarantine }}" wire:model="quantities.{{ $row->id }}.quantity" class="app-input w-20 px-2 py-1.5 text-right text-sm" placeholder="Qty">
                                    <button type="button" wire:click="releaseRow({{ $row->id }})" class="app-btn app-btn-primary !py-1.5 text-xs">{{ __('Loloskan') }}</button>
                                    <button type="button" wire:click="rejectRow({{ $row->id }})" class="app-btn app-btn-secondary !py-1.5 text-xs hover:!text-rose-600">{{ __('Tolak') }}</button>
                                </div>
                                @error("quantities.{$row->id}.quantity") <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><x-ui.empty-state title="Tidak ada stok karantina" message="Semua stok sudah lolos inspeksi." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($rows->hasPages())
            <div class="border-t border-app-border px-4 py-3">{{ $rows->links() }}</div>
        @endif
    </x-ui.card>
</div>
