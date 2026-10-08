<div>
    <x-ui.page-header title="{{ $receiptId ? 'Edit Barang Masuk' : 'Buat Barang Masuk' }}" subtitle="{{ $receiptId ? 'Perbarui transaksi penerimaan' : 'Transaksi penerimaan barang baru' }}">
        <x-slot:actions>
            <a href="{{ $receiptId ? route('goods-receipts.show', $receiptId) : route('goods-receipts.index') }}" class="app-btn app-btn-secondary">{{ __('Batal') }}</a>
        </x-slot:actions>
    </x-ui.page-header>

    <form wire:submit="save" class="space-y-4">
        <x-ui.card>
            <h2 class="app-card-title mb-4">Informasi Transaksi</h2>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <label for="transaction_date" class="app-label mb-1.5">{{ __('Tanggal') }}<span class="text-rose-500">*</span></label>
                    <input type="date" id="transaction_date" wire:model="transaction_date" class="app-input">
                    @error('transaction_date') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="supplier_id" class="app-label mb-1.5">Supplier<span class="text-rose-500">*</span></label>
                    <select id="supplier_id" wire:model="supplier_id" class="app-select">
                        <option value="">-- Pilih Supplier --</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                        @endforeach
                    </select>
                    @error('supplier_id') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
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
                    <label for="po_number" class="app-label mb-1.5">PO Number</label>
                    <input type="text" id="po_number" wire:model="po_number" placeholder="PO-..." class="app-input">
                    @error('po_number') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="delivery_note" class="app-label mb-1.5">Delivery Note</label>
                    <input type="text" id="delivery_note" wire:model="delivery_note" placeholder="DO-..." class="app-input">
                    @error('delivery_note') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="freight_cost" class="app-label mb-1.5">Biaya Kirim</label>
                    <input type="number" id="freight_cost" wire:model="freight_cost" min="0" step="0.01" class="app-input">
                    @error('freight_cost') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="other_cost" class="app-label mb-1.5">Biaya Lain</label>
                    <input type="number" id="other_cost" wire:model="other_cost" min="0" step="0.01" class="app-input">
                    @error('other_cost') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="landed_cost_method" class="app-label mb-1.5">Alokasi Landed Cost</label>
                    <select id="landed_cost_method" wire:model="landed_cost_method" class="app-select">
                        <option value="value">By Value</option>
                        <option value="quantity">By Quantity</option>
                    </select>
                    @error('landed_cost_method') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="received_by" class="app-label mb-1.5">Diterima Oleh</label>
                    <input type="text" id="received_by" wire:model="received_by" placeholder="Nama penerima" class="app-input">
                    @error('received_by') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2 lg:col-span-3">
                    <label for="notes" class="app-label mb-1.5">{{ __('Catatan') }}</label>
                    <textarea id="notes" wire:model="notes" rows="2" class="app-textarea" placeholder="Catatan transaksi..."></textarea>
                    @error('notes') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                </div>
            </div>
        </x-ui.card>

        <x-ui.card padding="p-0">
            <div class="app-card-header flex-col gap-3 sm:flex-row">
                <h2 class="app-card-title">{{ __('Detail Barang') }}</h2>
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
                            <th>{{ __('Barang') }}</th>
                            <th class="text-right">Qty</th>
                            <th class="text-right">{{ __('Harga Satuan') }}</th>
                            <th>{{ __('Satuan') }}</th>
                            <th>{{ __('Lokasi') }}</th>
                            <th>Batch</th>
                            <th>{{ __('Serial') }}</th>
                            <th>Expiry</th>
                            <th>{{ __('Catatan') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $index => $row)
                            <tr wire:key="gr-row-{{ $index }}" class="align-top">
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
                                    <input type="number" min="1" wire:model="items.{{ $index }}.quantity" class="app-input w-20 px-2 py-2 text-right text-sm">
                                    @error("items.{$index}.quantity") <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                                </td>
                                <td>
                                    <input type="number" min="0" step="0.01" wire:model="items.{{ $index }}.unit_cost" class="app-input w-28 px-2 py-2 text-right text-sm" placeholder="0">
                                    @error("items.{$index}.unit_cost") <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                                </td>
                                <td>
                                    <select wire:model="items.{{ $index }}.unit_id" class="app-select w-32 px-2 py-2 text-sm">
                                        <option value="">--</option>
                                        @foreach ($units as $unit)
                                            <option value="{{ $unit->id }}">{{ $unit->code ?: $unit->name }}</option>
                                        @endforeach
                                    </select>
                                    @error("items.{$index}.unit_id") <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                                </td>
                                <td>
                                    <select wire:model="items.{{ $index }}.location_id" class="app-select w-52 px-2 py-2 text-sm" @disabled($warehouse_id === '')>
                                        <option value="">{{ $warehouse_id === '' ? '-- Pilih Warehouse dulu --' : '-- Pilih Lokasi --' }}</option>
                                        @foreach ($locations as $location)
                                            <option value="{{ $location->id }}">{{ $location->fullPath() }}</option>
                                        @endforeach
                                    </select>
                                    @error("items.{$index}.location_id") <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                                </td>
                                <td>
                                    <input type="text" wire:model="items.{{ $index }}.batch_number" class="app-input w-28 px-2 py-2 text-sm">
                                    @error("items.{$index}.batch_number") <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                                </td>
                                <td>
                                    <input type="text" wire:model="items.{{ $index }}.serial_number" class="app-input w-28 px-2 py-2 text-sm">
                                    @error("items.{$index}.serial_number") <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                                </td>
                                <td>
                                    <input type="date" wire:model="items.{{ $index }}.expiry_date" class="app-input w-40 px-2 py-2 text-sm">
                                    @error("items.{$index}.expiry_date") <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
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
            <a href="{{ $receiptId ? route('goods-receipts.show', $receiptId) : route('goods-receipts.index') }}" class="app-btn app-btn-secondary">{{ __('Batal') }}</a>
            <button type="submit" wire:loading.attr="disabled" class="app-btn app-btn-primary disabled:opacity-60">
                <span wire:loading.remove wire:target="save">Simpan Draft</span>
                <span wire:loading wire:target="save">Menyimpan…</span>
            </button>
        </div>
    </form>
</div>
