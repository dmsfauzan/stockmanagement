<div>
    <x-ui.page-header title="{{ $adjustmentId ? 'Edit Stock Adjustment' : 'Buat Stock Adjustment' }}" subtitle="{{ $adjustmentId ? 'Perbarui penyesuaian stok' : 'Transaksi penyesuaian stok baru' }}">
        <x-slot:actions>
            <a href="{{ $adjustmentId ? route('stock-adjustments.show', $adjustmentId) : route('stock-adjustments.index') }}" class="app-btn app-btn-secondary">Batal</a>
        </x-slot:actions>
    </x-ui.page-header>

    <form wire:submit="save" class="space-y-4">
        <x-ui.card>
            <h2 class="app-card-title mb-4">Informasi Transaksi</h2>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <label for="transaction_date" class="app-label mb-1.5">Tanggal<span class="text-rose-500">*</span></label>
                    <input type="date" id="transaction_date" wire:model="transaction_date" class="app-input">
                    @error('transaction_date') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="warehouse_id" class="app-label mb-1.5">Warehouse<span class="text-rose-500">*</span></label>
                    <select id="warehouse_id" wire:model.live="warehouse_id" class="app-select">
                        <option value="">-- Pilih Warehouse --</option>
                        @foreach ($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                        @endforeach
                    </select>
                    @error('warehouse_id') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="location_id" class="app-label mb-1.5">Location<span class="text-rose-500">*</span></label>
                    <select id="location_id" wire:model.live="location_id" class="app-select" @disabled($warehouse_id === '')>
                        <option value="">{{ $warehouse_id === '' ? '-- Pilih Warehouse dulu --' : '-- Pilih Lokasi --' }}</option>
                        @foreach ($locations as $location)
                            <option value="{{ $location->id }}">{{ $location->fullPath() }}</option>
                        @endforeach
                    </select>
                    @error('location_id') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="reason" class="app-label mb-1.5">Reason<span class="text-rose-500">*</span></label>
                    <select id="reason" wire:model="reason" class="app-select">
                        <option value="">-- Pilih Alasan --</option>
                        @foreach ($reasons as $option)
                            <option value="{{ $option }}">{{ $option }}</option>
                        @endforeach
                    </select>
                    @error('reason') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="attachment" class="app-label mb-1.5">Attachment</label>
                    <input type="file" id="attachment" wire:model="attachment" class="app-input">
                    <div wire:loading wire:target="attachment" class="mt-1 text-xs text-app-muted">Mengunggah…</div>
                    @if($existingAttachment)
                        <p class="mt-1 text-xs text-app-muted">File saat ini: {{ basename($existingAttachment) }}</p>
                    @endif
                    @error('attachment') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2 lg:col-span-3">
                    <label for="notes" class="app-label mb-1.5">Catatan</label>
                    <textarea id="notes" wire:model="notes" rows="2" class="app-textarea" placeholder="Catatan transaksi..."></textarea>
                    @error('notes') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                </div>
            </div>
        </x-ui.card>

        <x-ui.card padding="p-0">
            <div class="app-card-header flex-col gap-3 sm:flex-row">
                <h2 class="app-card-title">Detail Barang</h2>
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                    <label class="relative block">
                        <svg class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-app-muted" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5"/></svg>
                        <input type="text" wire:model="barcodeInput" wire:keydown.enter="addByBarcode" wire:blur="addByBarcode" placeholder="Scan barcode / SKU lalu Enter" class="app-input w-64 pl-9">
                    </label>
                    <button type="button" wire:click="addRow" class="app-btn app-btn-secondary">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                        Tambah Baris
                    </button>
                </div>
            </div>

            @error('items') <p class="px-4 pt-3 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror

            <div class="overflow-x-auto">
                <table class="app-table">
                    <thead>
                        <tr>
                            <th>Barang</th>
                            <th class="text-right">System Qty</th>
                            <th class="text-right">Actual Qty</th>
                            <th class="text-right">Difference</th>
                            <th>Catatan</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $index => $row)
                            <tr wire:key="adj-row-{{ $index }}" class="align-top">
                                <td>
                                    <select wire:model="items.{{ $index }}.item_id" wire:change="selectItem({{ $index }})" class="app-select w-56 px-2 py-2 text-sm">
                                        <option value="">-- Pilih Barang --</option>
                                        @foreach ($itemsList as $item)
                                            <option value="{{ $item->id }}">{{ $item->sku }} - {{ $item->name }}</option>
                                        @endforeach
                                    </select>
                                    @error("items.{$index}.item_id") <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                                </td>
                                <td>
                                    <input type="number" readonly wire:model="items.{{ $index }}.system_quantity" class="app-input w-24 bg-app-surface-2 px-2 py-2 text-right text-sm text-app-muted">
                                    @error("items.{$index}.system_quantity") <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                                </td>
                                <td>
                                    <input type="number" min="0" wire:model.live.debounce.200ms="items.{{ $index }}.actual_quantity" class="app-input w-24 px-2 py-2 text-right text-sm">
                                    @error("items.{$index}.actual_quantity") <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                                </td>
                                <td class="text-right">
                                    @php $diff = (int) ($row['difference'] ?? 0); @endphp
                                    <span @class([
                                        'inline-flex min-w-[3rem] justify-end font-semibold',
                                        'text-emerald-600 dark:text-emerald-400' => $diff > 0,
                                        'text-rose-600 dark:text-rose-400' => $diff < 0,
                                        'text-app-muted' => $diff === 0,
                                    ])>
                                        {{ $diff > 0 ? '+' : '' }}{{ number_format($diff) }}
                                    </span>
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
            <a href="{{ $adjustmentId ? route('stock-adjustments.show', $adjustmentId) : route('stock-adjustments.index') }}" class="app-btn app-btn-secondary">Batal</a>
            <button type="submit" wire:loading.attr="disabled" class="app-btn app-btn-primary disabled:opacity-60">
                <span wire:loading.remove wire:target="save">Simpan Draft</span>
                <span wire:loading wire:target="save">Menyimpan…</span>
            </button>
        </div>
    </form>
</div>
