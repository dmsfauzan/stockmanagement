<div>
    <x-ui.page-header title="{{ $itemId ? 'Edit Barang' : 'Tambah Barang' }}" subtitle="{{ $itemId ? 'Perbarui data barang' : 'Tambahkan barang baru' }}">
        <x-slot:actions>
            <a href="{{ route('items.index') }}" class="app-btn app-btn-secondary">{{ __('Batal') }}</a>
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
                    <label for="barcode" class="app-label mb-1">{{ __('Barcode') }}</label>
                    <input type="text" id="barcode" wire:model="barcode" class="app-input" placeholder="899xxxxxxxxx">
                    @error('barcode') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label for="name" class="app-label mb-1">{{ __('Nama') }}<span class="text-rose-500">*</span></label>
                <input type="text" id="name" wire:model="name" class="app-input" placeholder="Nama barang">
                @error('name') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="category_id" class="app-label mb-1">{{ __('Kategori') }}<span class="text-rose-500">*</span></label>
                    <select id="category_id" wire:model="category_id" class="app-select">
                        <option value="">-- Pilih Kategori --</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                    @error('category_id') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="unit_id" class="app-label mb-1">{{ __('Satuan') }}<span class="text-rose-500">*</span></label>
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

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
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
                    <label for="cost" class="app-label mb-1">{{ __('Harga Satuan') }}</label>
                    <input type="number" id="cost" wire:model="cost" min="0" step="0.01" class="app-input" placeholder="0">
                    @error('cost') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="status" class="app-label mb-1">Status<span class="text-rose-500">*</span></label>
                    <select id="status" wire:model="status" class="app-select">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                    @error('status') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="tracking_type" class="app-label mb-1">Tracking<span class="text-rose-500">*</span></label>
                    <select id="tracking_type" wire:model="tracking_type" class="app-select">
                        <option value="none">Tanpa Lot</option>
                        <option value="batch">Batch / Lot</option>
                        <option value="serial">Serial Number</option>
                    </select>
                    @error('tracking_type') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label for="description" class="app-label mb-1">{{ __('Deskripsi') }}</label>
                <textarea id="description" wire:model="description" rows="3" class="app-input min-h-[84px] resize-y" placeholder="Deskripsi barang..."></textarea>
                @error('description') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="app-label mb-1">Foto Barang (JPG/PNG/WebP/GIF, maks 2MB)</label>
                <div class="flex items-start gap-4">
                    <div class="shrink-0">
                        @if ($image)
                            <img src="{{ $image->temporaryUrl() }}" alt="Preview" class="h-20 w-20 rounded-xl border border-app-border object-cover">
                        @elseif ($existingImagePath)
                            <img src="{{ app(\App\Services\Support\ImageService::class)->thumbUrl($existingImagePath) }}" alt="Foto barang" class="h-20 w-20 rounded-xl border border-app-border object-cover">
                        @else
                            <span class="flex h-20 w-20 items-center justify-center rounded-xl border border-dashed border-app-border bg-app-surface-2/50 text-app-muted">
                                <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"/></svg>
                            </span>
                        @endif
                    </div>
                    <div class="min-w-0 flex-1">
                        <input type="file" wire:model="image" accept="image/*" class="app-input text-sm">
                        @error('image') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                        <div wire:loading wire:target="image" class="mt-1 text-xs text-app-muted">Mengunggah…</div>
                        @if ($existingImagePath)
                            <label class="mt-2 flex items-center gap-2 text-sm text-app-muted">
                                <input type="checkbox" wire:model.live="removeImage" class="rounded">
                                Hapus foto saat ini
                            </label>
                        @endif
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <a href="{{ route('items.index') }}" class="app-btn app-btn-secondary">{{ __('Batal') }}</a>
                <button type="submit" wire:loading.attr="disabled" class="app-btn app-btn-primary gap-2">
                    <span wire:loading.remove wire:target="save">{{ __('Simpan') }}</span>
                    <span wire:loading wire:target="save">Menyimpan…</span>
                </button>
            </div>
        </form>
    </x-ui.card>
</div>
