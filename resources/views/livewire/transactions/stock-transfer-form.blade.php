<div>
    <x-ui.page-header title="{{ $transferId ? 'Edit Transfer Barang' : 'Buat Transfer Barang' }}" subtitle="{{ $transferId ? 'Perbarui transfer stok' : 'Transaksi transfer stok baru' }}">
        <x-slot:actions>
            <a href="{{ $transferId ? route('stock-transfers.show', $transferId) : route('stock-transfers.index') }}" class="app-btn app-btn-secondary">{{ __('Batal') }}</a>
        </x-slot:actions>
    </x-ui.page-header>

    <form wire:submit="save" class="space-y-4">
        <x-ui.card>
            <h2 class="app-card-title mb-4">Informasi Transaksi</h2>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <label for="transfer_date" class="app-label mb-1.5">{{ __('Tanggal') }}<span class="text-rose-500">*</span></label>
                    <input type="date" id="transfer_date" wire:model="transfer_date" class="app-input">
                    @error('transfer_date') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="from_warehouse_id" class="app-label mb-1.5">Dari Warehouse<span class="text-rose-500">*</span></label>
                    <select id="from_warehouse_id" wire:model.live="from_warehouse_id" class="app-select">
                        <option value="">-- Pilih Warehouse Asal --</option>
                        @foreach ($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                        @endforeach
                    </select>
                    @error('from_warehouse_id') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="from_location_id" class="app-label mb-1.5">Dari Location<span class="text-rose-500">*</span></label>
                    <select id="from_location_id" wire:model.live="from_location_id" class="app-select" @disabled($from_warehouse_id === '')>
                        <option value="">{{ $from_warehouse_id === '' ? '-- Pilih Warehouse dulu --' : '-- Pilih Lokasi Asal --' }}</option>
                        @foreach ($fromLocations as $location)
                            <option value="{{ $location->id }}">{{ $location->fullPath() }}</option>
                        @endforeach
                    </select>
                    @error('from_location_id') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="to_warehouse_id" class="app-label mb-1.5">Ke Warehouse<span class="text-rose-500">*</span></label>
                    <select id="to_warehouse_id" wire:model.live="to_warehouse_id" class="app-select">
                        <option value="">-- Pilih Warehouse Tujuan --</option>
                        @foreach ($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                        @endforeach
                    </select>
                    @error('to_warehouse_id') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="to_location_id" class="app-label mb-1.5">Ke Location<span class="text-rose-500">*</span></label>
                    <select id="to_location_id" wire:model="to_location_id" class="app-select" @disabled($to_warehouse_id === '')>
                        <option value="">{{ $to_warehouse_id === '' ? '-- Pilih Warehouse dulu --' : '-- Pilih Lokasi Tujuan --' }}</option>
                        @foreach ($toLocations as $location)
                            <option value="{{ $location->id }}">{{ $location->fullPath() }}</option>
                        @endforeach
                    </select>
                    @error('to_location_id') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2 lg:col-span-3">
                    <label for="notes" class="app-label mb-1.5">{{ __('Catatan') }}</label>
                    <textarea id="notes" wire:model="notes" rows="2" class="app-textarea" placeholder="Catatan transfer..."></textarea>
                    @error('notes') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                </div>
            </div>
        </x-ui.card>

        <x-ui.card padding="p-0">
            <div class="app-card-header flex-col gap-3 sm:flex-row">
                <h2 class="app-card-title">{{ __('Detail Barang') }}</h2>
                <button type="button" wire:click="addRow" class="app-btn app-btn-secondary">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                    Tambah Baris
                </button>
            </div>

            @error('items') <p class="px-4 pt-3 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror

            <div class="overflow-x-auto">
                <table class="app-table">
                    <thead>
                        <tr>
                            <th>{{ __('Barang') }}</th>
                            <th class="text-right">Tersedia</th>
                            <th class="text-right">Qty Transfer</th>
                            <th>{{ __('Catatan') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $index => $row)
                            <tr wire:key="tr-row-{{ $index }}" class="align-top">
                                <td>
                                    <select wire:model="items.{{ $index }}.item_id" wire:change="selectItem({{ $index }})" class="app-select w-56 px-2 py-2 text-sm">
                                        <option value="">-- Pilih Barang --</option>
                                        @foreach ($itemsList as $item)
                                            <option value="{{ $item->id }}">{{ $item->sku }} - {{ $item->name }}</option>
                                        @endforeach
                                    </select>
                                    @error("items.{$index}.item_id") <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                                    @error("items.{$index}.unit_id") <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                                </td>
                                <td class="text-right">
                                    @php $available = (int) ($row['available'] ?? 0); @endphp
                                    <span @class([
                                        'inline-flex min-w-[3rem] justify-end font-semibold',
                                        'text-rose-600 dark:text-rose-400' => $available <= 0,
                                        'text-emerald-600 dark:text-emerald-400' => $available > 0,
                                    ])>{{ number_format($available) }}</span>
                                </td>
                                <td>
                                    <input type="number" min="1" wire:model.live.debounce.200ms="items.{{ $index }}.quantity" class="app-input w-28 px-2 py-2 text-right text-sm">
                                    @error("items.{$index}.quantity") <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                                </td>
                                <td>
                                    <input type="text" wire:model="items.{{ $index }}.notes" class="app-input w-40 px-2 py-2 text-sm">
                                    @error("items.{$index}.notes") <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                                </td>
                                <td class="text-right">
                                    <x-ui.confirm action="removeRow" :params="[$index]" title="Hapus Baris" message="Hapus baris ini?" confirm-label="Hapus" variant="danger" aria-label="Hapus baris" class="app-btn app-btn-ghost p-1.5 text-app-muted hover:text-rose-600 dark:hover:text-rose-400"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></x-ui.confirm>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-ui.card>

        <div class="flex justify-end gap-2">
            <a href="{{ $transferId ? route('stock-transfers.show', $transferId) : route('stock-transfers.index') }}" class="app-btn app-btn-secondary">{{ __('Batal') }}</a>
            <button type="submit" wire:loading.attr="disabled" class="app-btn app-btn-primary disabled:opacity-60">
                <span wire:loading.remove wire:target="save">Simpan Draft</span>
                <span wire:loading wire:target="save">Menyimpan…</span>
            </button>
        </div>
    </form>
</div>
