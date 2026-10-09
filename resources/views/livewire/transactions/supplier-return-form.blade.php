<div>
    <x-ui.page-header title="Form Retur Pembelian" :subtitle="$return->number ?? __('Baru')">
        <x-slot:actions>
            <a href="{{ route('supplier-returns.index') }}" class="app-btn app-btn-secondary">{{ __('Kembali') }}</a>
        </x-slot:actions>
    </x-ui.page-header>

    <form wire:submit="save" class="space-y-4">
        <x-ui.card>
            <h2 class="app-card-title mb-3">Header</h2>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div><label class="app-label mb-1.5">{{ __('Tanggal') }}<span class="text-rose-500">*</span></label><input type="date" wire:model="transaction_date" class="app-input">@error('transaction_date')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror</div>
                <div class="lg:col-span-2">
                    <label class="app-label mb-1.5">Dokumen Asal (Barang Masuk, opsional)</label>
                    <select wire:model.live="goods_receipt_id" class="app-select">
                        <option value="">— Tanpa dokumen asal —</option>
                        @foreach ($receipts as $receipt)
                            <option value="{{ $receipt->id }}">{{ $receipt->number }}</option>
                        @endforeach
                    </select>
                    @error('goods_receipt_id')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="app-label mb-1.5">{{ __('Supplier') }}</label>
                    <select wire:model="supplier_id" class="app-select">
                        <option value="">—</option>
                        @foreach ($suppliers as $customer)
                            <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="app-label mb-1.5">{{ __('Gudang') }}<span class="text-rose-500">*</span></label>
                    <select wire:model.live="warehouse_id" class="app-select">
                        <option value="">—</option>
                        @foreach ($warehouses as $wh)
                            <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                        @endforeach
                    </select>
                    @error('warehouse_id')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="app-label mb-1.5">{{ __('Lokasi') }}<span class="text-rose-500">*</span></label>
                    <select wire:model="location_id" class="app-select">
                        <option value="">—</option>
                        @foreach ($locations as $loc)
                            <option value="{{ $loc->id }}">{{ $loc->code }}</option>
                        @endforeach
                    </select>
                    @error('location_id')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div class="lg:col-span-2"><label class="app-label mb-1.5">Alasan</label><input type="text" wire:model="reason" maxlength="150" class="app-input" placeholder="mis. barang rusak"></div>
                <div class="lg:col-span-4"><label class="app-label mb-1.5">{{ __('Catatan') }}</label><textarea wire:model="notes" rows="2" class="app-textarea" placeholder="Catatan retur..."></textarea></div>
            </div>
        </x-ui.card>

        <x-ui.card padding="p-0">
            <div class="flex items-center justify-between border-b border-app-border px-4 py-3">
                <h2 class="app-card-title">{{ __('Detail Barang') }}</h2>
                <button type="button" wire:click="addRow" class="app-btn app-btn-secondary app-btn-sm">Tambah Baris</button>
            </div>
            <div class="overflow-x-auto">
                <table class="app-table">
                    <thead><tr><th>{{ __('Barang') }}</th><th class="text-right">Qty</th><th>Unit</th><th>{{ __('Lokasi') }}</th><th class="text-right">{{ __('Harga') }}</th><th>Batch</th><th>Serial</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($items as $index => $row)
                            <tr>
                                <td>
                                    <select wire:model="items.{{ $index }}.item_id" wire:change="selectItem({{ $index }})" class="app-select w-56 px-2 py-2 text-sm">
                                        <option value="">— Pilih —</option>
                                        @foreach ($itemsList as $it)
                                            <option value="{{ $it->id }}">{{ $it->sku }} — {{ $it->name }}</option>
                                        @endforeach
                                    </select>
                                    @error("items.{$index}.item_id")<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                                </td>
                                <td><input type="number" min="1" wire:model="items.{{ $index }}.quantity" class="app-input w-20 px-2 py-2 text-right text-sm">@error("items.{$index}.quantity")<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror</td>
                                <td><select wire:model="items.{{ $index }}.unit_id" class="app-select w-24 px-2 py-2 text-sm"><option value="">—</option>@foreach ($units as $u)<option value="{{ $u->id }}">{{ $u->code }}</option>@endforeach</select></td>
                                <td><select wire:model="items.{{ $index }}.location_id" class="app-select w-28 px-2 py-2 text-sm"><option value="">—</option>@foreach ($locations as $loc)<option value="{{ $loc->id }}">{{ $loc->code }}</option>@endforeach</select></td>
                                <td><input type="number" min="0" step="0.01" wire:model="items.{{ $index }}.unit_cost" class="app-input w-28 px-2 py-2 text-right text-sm"></td>
                                <td><input type="text" wire:model="items.{{ $index }}.batch_number" class="app-input w-28 px-2 py-2 text-sm"></td>
                                <td><input type="text" wire:model="items.{{ $index }}.serial_number" class="app-input w-28 px-2 py-2 text-sm"></td>
                                <td class="text-right"><button type="button" wire:click="removeRow({{ $index }})" class="app-btn app-btn-ghost !p-1.5 hover:!text-rose-600" title="Hapus baris">✕</button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-ui.card>

        <div class="flex justify-end gap-2">
            <a href="{{ route('supplier-returns.index') }}" class="app-btn app-btn-secondary">{{ __('Batal') }}</a>
            <button type="submit" class="app-btn app-btn-primary">{{ __('Simpan') }}</button>
        </div>
    </form>
</div>
