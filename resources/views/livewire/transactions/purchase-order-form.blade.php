<div>
    <x-ui.page-header title="{{ $purchaseOrderId ? 'Edit Purchase Order' : 'Buat Purchase Order' }}" subtitle="{{ $purchaseOrderId ? 'Perbarui pesanan pembelian' : 'Pesanan pembelian baru' }}">
        <x-slot:actions>
            <a href="{{ $purchaseOrderId ? route('purchase-orders.show', $purchaseOrderId) : route('purchase-orders.index') }}" class="app-btn app-btn-secondary">Batal</a>
        </x-slot:actions>
    </x-ui.page-header>

    <form wire:submit="save" class="space-y-4">
        <x-ui.card>
            <h2 class="app-card-title mb-4">Informasi PO</h2>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <label for="order_date" class="app-label mb-1.5">Tanggal PO<span class="text-rose-500">*</span></label>
                    <input type="date" id="order_date" wire:model="order_date" class="app-input">
                    @error('order_date') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="expected_date" class="app-label mb-1.5">Estimasi Datang</label>
                    <input type="date" id="expected_date" wire:model="expected_date" class="app-input">
                    @error('expected_date') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
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
                    <select id="warehouse_id" wire:model="warehouse_id" class="app-select">
                        <option value="">-- Pilih Warehouse --</option>
                        @foreach ($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                        @endforeach
                    </select>
                    @error('warehouse_id') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2 lg:col-span-3">
                    <label for="notes" class="app-label mb-1.5">Catatan</label>
                    <textarea id="notes" wire:model="notes" rows="2" class="app-textarea" placeholder="Catatan PO..."></textarea>
                    @error('notes') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                </div>
            </div>
        </x-ui.card>

        <x-ui.card padding="p-0">
            <div class="app-card-header flex-col gap-3 sm:flex-row">
                <h2 class="app-card-title">Detail Barang</h2>
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
                            <th>Barang</th>
                            <th class="text-right">Qty</th>
                            <th class="text-right">Diterima</th>
                            <th>Satuan</th>
                            <th class="text-right">Harga Satuan</th>
                            <th>Catatan</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $index => $row)
                            <tr wire:key="po-row-{{ $index }}" class="align-top">
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
                                    <input type="number" min="1" wire:model="items.{{ $index }}.quantity" class="app-input w-24 px-2 py-2 text-right text-sm">
                                    @error("items.{$index}.quantity") <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                                </td>
                                <td>
                                    <input type="number" readonly value="{{ (int) ($row['received_quantity'] ?? 0) }}" class="app-input w-24 cursor-not-allowed bg-app-surface-2 px-2 py-2 text-right text-sm text-app-muted">
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
                                    <input type="number" min="0" step="0.01" wire:model="items.{{ $index }}.unit_price" class="app-input w-32 px-2 py-2 text-right text-sm">
                                    @error("items.{$index}.unit_price") <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
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
            <a href="{{ $purchaseOrderId ? route('purchase-orders.show', $purchaseOrderId) : route('purchase-orders.index') }}" class="app-btn app-btn-secondary">Batal</a>
            <button type="submit" wire:loading.attr="disabled" class="app-btn app-btn-primary disabled:opacity-60">
                <span wire:loading.remove wire:target="save">Simpan Draft</span>
                <span wire:loading wire:target="save">Menyimpan…</span>
            </button>
        </div>
    </form>
</div>
