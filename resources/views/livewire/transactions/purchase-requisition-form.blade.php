<div>
    <x-ui.page-header title="Form Requisition" subtitle="Buat pengajuan pembelian baru">
        <x-slot:actions>
            <a href="{{ route('requisitions.index') }}" class="app-btn app-btn-secondary">{{ __('Batal') }}</a>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card class="max-w-3xl">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label class="app-label mb-1.5">{{ __('Tanggal Permintaan') }}<span class="text-rose-500">*</span></label>
                <input type="date" wire:model="request_date" class="app-input">
                @error('request_date') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="app-label mb-1.5">{{ __('Tanggal Dibutuhkan') }}</label>
                <input type="date" wire:model="required_date" class="app-input">
                @error('required_date') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="app-label mb-1.5">Warehouse<span class="text-rose-500">*</span></label>
                <select wire:model="warehouse_id" class="app-select">
                    <option value="">-- Pilih --</option>
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                    @endforeach
                </select>
                @error('warehouse_id') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2">
                <label class="app-label mb-1.5">{{ __('Catatan') }}</label>
                <textarea wire:model="notes" rows="2" class="app-textarea"></textarea>
            </div>
        </div>

        <div class="mt-6">
            <h3 class="app-card-title mb-3">{{ __('Detail Barang') }}</h3>
            <div class="overflow-x-auto">
                <table class="app-table">
                    <thead><tr><th>{{ __('Barang') }}</th><th class="text-right">{{ __('Qty') }}</th><th>{{ __('Satuan') }}</th><th class="text-right">{{ __('Estimasi Harga') }}</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($items as $index => $row)
                            <tr wire:key="req-row-{{ $index }}">
                                <td>
                                    <select wire:model="items.{{ $index }}.item_id" wire:change="selectItem({{ $index }})" class="app-select w-56 px-2 py-2 text-sm">
                                        <option value="">-- Pilih Barang --</option>
                                        @foreach ($itemsList as $item)
                                            <option value="{{ $item->id }}">{{ $item->sku }} — {{ $item->name }}</option>
                                        @endforeach
                                    </select>
                                    @error("items.{$index}.item_id") <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                                </td>
                                <td>
                                    <input type="number" min="1" wire:model="items.{{ $index }}.quantity" class="app-input w-20 px-2 py-2 text-right text-sm">
                                    @error("items.{$index}.quantity") <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                                </td>
                                <td>
                                    <select wire:model="items.{{ $index }}.unit_id" class="app-select w-32 px-2 py-2 text-sm">
                                        <option value="">--</option>
                                        @foreach ($units as $unit)
                                            <option value="{{ $unit->id }}">{{ $unit->code }}</option>
                                        @endforeach
                                    </select>
                                    @error("items.{$index}.unit_id") <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                                </td>
                                <td>
                                    <input type="number" min="0" step="0.01" wire:model="items.{{ $index }}.estimated_price" class="app-input w-28 px-2 py-2 text-right text-sm">
                                    @error("items.{$index}.estimated_price") <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                                </td>
                                <td class="text-right">
                                    <button type="button" wire:click="removeRow({{ $index }})" class="app-btn app-btn-ghost !p-1.5 hover:!text-rose-600">✕</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-3">
                <button type="button" wire:click="addRow" class="app-btn app-btn-secondary">{{ __('Tambah Baris') }}</button>
            </div>
        </div>

        <div class="mt-6 flex justify-end gap-2">
            <button type="button" wire:click="save(false)" wire:loading.attr="disabled" class="app-btn app-btn-secondary">{{ __('Simpan Draf') }}</button>
            <button type="button" wire:click="save(true)" wire:loading.attr="disabled" class="app-btn app-btn-primary">{{ __('Simpan & Ajukan') }}</button>
        </div>
    </x-ui.card>
</div>
