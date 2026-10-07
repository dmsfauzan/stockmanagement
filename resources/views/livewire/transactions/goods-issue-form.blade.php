<div>
    <x-ui.page-header title="{{ $issueId ? 'Edit Barang Keluar' : 'Buat Barang Keluar' }}" subtitle="{{ $issueId ? 'Perbarui transaksi pengeluaran' : 'Transaksi pengeluaran barang baru' }}">
        <x-slot:actions>
            <a href="{{ $issueId ? route('goods-issues.show', $issueId) : route('goods-issues.index') }}" class="app-btn app-btn-secondary">Batal</a>
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
                    <label for="customer_id" class="app-label mb-1.5">Customer / Department</label>
                    <select id="customer_id" wire:model="customer_id" class="app-select">
                        <option value="">-- Tanpa Customer --</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                        @endforeach
                    </select>
                    @error('customer_id') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="destination" class="app-label mb-1.5">Destination<span class="text-rose-500">*</span></label>
                    <input type="text" id="destination" wire:model="destination" placeholder="Tujuan pengiriman / departemen" class="app-input">
                    @error('destination') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="sales_order_number" class="app-label mb-1.5">Sales Order Number</label>
                    <input type="text" id="sales_order_number" wire:model="sales_order_number" placeholder="SO-..." class="app-input">
                    @error('sales_order_number') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="issued_by" class="app-label mb-1.5">Dikeluarkan Oleh</label>
                    <input type="text" id="issued_by" wire:model="issued_by" placeholder="Nama pengeluar" class="app-input">
                    @error('issued_by') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
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
                            <th class="text-right">Qty</th>
                            <th class="text-right">Tersedia</th>
                            <th>Satuan</th>
                            <th>Lokasi</th>
                            <th>Batch</th>
                            <th>Serial</th>
                            <th>Catatan</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $index => $row)
                            @php $available = $stockMap[$row['item_id']] ?? []; $qtyAvailable = (int) ($available[$row['location_id']] ?? 0); @endphp
                            <tr wire:key="gi-row-{{ $index }}" class="align-top">
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
                                <td class="text-right">
                                    <span class="{{ ($row['item_id'] !== '' && $row['location_id'] !== '' && $row['quantity'] > $qtyAvailable) ? 'font-semibold text-rose-600 dark:text-rose-400' : 'text-app-muted' }}">{{ number_format($qtyAvailable) }}</span>
                                    @if($row['item_id'] !== '' && $row['location_id'] !== '' && $row['quantity'] > $qtyAvailable)
                                        <p class="mt-1 text-xs font-medium text-amber-600 dark:text-amber-400">Qty melebihi stok tersedia.</p>
                                    @endif
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
                                    @if (($lotMap[$index] ?? collect())->isNotEmpty())
                                        <button type="button" wire:click="applyFefo({{ $index }})" class="mt-1 rounded bg-primary-50 px-2 py-0.5 text-[11px] font-medium text-primary-700 hover:bg-primary-100 dark:bg-primary-900/30 dark:text-primary-300">FEFO</button>
                                        <div class="mt-1 space-y-0.5 text-[10px] text-app-muted">
                                            @foreach ($lotMap[$index]->take(3) as $lot)
                                                <div>{{ $lot->batch_number ?? $lot->serial_number ?? '-' }} · {{ $lot->expiry_date?->format('d/m/y') ?? '-' }} · {{ (int) $lot->quantity }}</div>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    <input type="text" wire:model="items.{{ $index }}.serial_number" class="app-input w-28 px-2 py-2 text-sm">
                                    @error("items.{$index}.serial_number") <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
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
            <p class="border-t border-app-border px-4 py-2 text-xs text-app-muted">Stok tersedia dihitung dari saldo pada warehouse &amp; lokasi terpilih. Validasi final dilakukan saat posting.</p>
        </x-ui.card>

        <div class="flex justify-end gap-2">
            <a href="{{ $issueId ? route('goods-issues.show', $issueId) : route('goods-issues.index') }}" class="app-btn app-btn-secondary">Batal</a>
            <button type="submit" wire:loading.attr="disabled" class="app-btn app-btn-primary disabled:opacity-60">
                <span wire:loading.remove wire:target="save">Simpan Draft</span>
                <span wire:loading wire:target="save">Menyimpan…</span>
            </button>
        </div>
    </form>
</div>
