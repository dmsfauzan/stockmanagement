<div>
    <x-ui.page-header title="{{ $itemId ? 'Edit Barang' : 'Tambah Barang' }}" subtitle="{{ $itemId ? 'Perbarui data barang' : 'Tambahkan barang baru' }}">
        <x-slot:actions>
            <a href="{{ route('items.index') }}" class="app-btn app-btn-secondary">Batal</a>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card>
        <form wire:submit="save" class="space-y-4">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="sku" class="app-label mb-1">SKU<span class="text-rose-500">*</span></label>
                    <input type="text" id="sku" wire:model="sku" class="app-input" placeholder="BRG-001">
                    @error('sku') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="barcode" class="app-label mb-1">Barcode</label>
                    <input type="text" id="barcode" wire:model="barcode" class="app-input" placeholder="899xxxxxxxxx">
                    @error('barcode') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label for="name" class="app-label mb-1">Nama<span class="text-rose-500">*</span></label>
                <input type="text" id="name" wire:model="name" class="app-input" placeholder="Nama barang">
                @error('name') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="category_id" class="app-label mb-1">Kategori<span class="text-rose-500">*</span></label>
                    <select id="category_id" wire:model="category_id" class="app-select">
                        <option value="">-- Pilih Kategori --</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                    @error('category_id') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="unit_id" class="app-label mb-1">Satuan<span class="text-rose-500">*</span></label>
                    <select id="unit_id" wire:model="unit_id" class="app-select">
                        <option value="">-- Pilih Satuan --</option>
                        @foreach ($units as $unit)
                            <option value="{{ $unit->id }}">{{ $unit->name }} ({{ $unit->code }})</option>
                        @endforeach
                    </select>
                    @error('unit_id') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="brand" class="app-label mb-1">Brand</label>
                    <input type="text" id="brand" wire:model="brand" class="app-input" placeholder="Brand">
                    @error('brand') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="primary_supplier_id" class="app-label mb-1">Supplier Utama</label>
                    <select id="primary_supplier_id" wire:model="primary_supplier_id" class="app-select">
                        <option value="">-- Pilih Supplier --</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                        @endforeach
                    </select>
                    @error('primary_supplier_id') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <label for="minimum_stock" class="app-label mb-1">Minimum Stock<span class="text-rose-500">*</span></label>
                    <input type="number" id="minimum_stock" wire:model.live="minimum_stock" min="0" class="app-input">
                    @error('minimum_stock') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="maximum_stock" class="app-label mb-1">Maximum Stock<span class="text-rose-500">*</span></label>
                    <input type="number" id="maximum_stock" wire:model.live="maximum_stock" min="0" class="app-input">
                    @error('maximum_stock') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="status" class="app-label mb-1">Status<span class="text-rose-500">*</span></label>
                    <select id="status" wire:model="status" class="app-select">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                    @error('status') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label for="description" class="app-label mb-1">Deskripsi</label>
                <textarea id="description" wire:model="description" rows="3" class="app-input min-h-[84px] resize-y" placeholder="Deskripsi barang..."></textarea>
                @error('description') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <a href="{{ route('items.index') }}" class="app-btn app-btn-secondary">Batal</a>
                <button type="submit" wire:loading.attr="disabled" class="app-btn app-btn-primary gap-2">
                    <span wire:loading.remove wire:target="save">Simpan</span>
                    <span wire:loading wire:target="save">Menyimpan…</span>
                </button>
            </div>
        </form>
    </x-ui.card>
</div>
