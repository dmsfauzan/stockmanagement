<div>
    <x-ui.page-header title="{{ $opnameId ? 'Edit Stock Opname' : 'Buat Stock Opname' }}" subtitle="{{ $opnameId ? 'Perbarui header opname' : 'Stock opname baru' }}">
        <x-slot:actions>
            <a href="{{ $opnameId ? route('stock-opnames.show', $opnameId) : route('stock-opnames.index') }}" class="app-btn app-btn-secondary">Batal</a>
        </x-slot:actions>
    </x-ui.page-header>

    <form wire:submit="save" class="space-y-4">
        <x-ui.card>
            <h2 class="app-card-title mb-4">Informasi Opname</h2>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <label for="opname_date" class="app-label mb-1.5">Tanggal<span class="text-rose-500">*</span></label>
                    <input type="date" id="opname_date" wire:model="opname_date" class="app-input">
                    @error('opname_date') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
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
                    <label for="location_id" class="app-label mb-1.5">Location</label>
                    <select id="location_id" wire:model.live="location_id" class="app-select" @disabled($warehouse_id === '')>
                        <option value="">{{ $warehouse_id === '' ? '-- Pilih Warehouse dulu --' : '-- Semua Lokasi --' }}</option>
                        @foreach ($locations as $location)
                            <option value="{{ $location->id }}">{{ $location->fullPath() }}</option>
                        @endforeach
                    </select>
                    @error('location_id') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="type" class="app-label mb-1.5">Tipe</label>
                    <select id="type" wire:model.live="type" class="app-select">
                        <option value="full">Full (semua di warehouse/lokasi)</option>
                        <option value="cycle">Cycle Count (per zona/rak)</option>
                    </select>
                    @error('type') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                </div>
                @if($type === 'cycle')
                    <div class="grid grid-cols-1 gap-4 sm:col-span-2 sm:grid-cols-2 lg:col-span-3 lg:grid-cols-2">
                        <div>
                            <label for="zone_id" class="app-label mb-1.5">Zona (opsional)</label>
                            <select id="zone_id" wire:model.live="zone_id" class="app-select">
                                <option value="">-- Semua Zona --</option>
                                @foreach (($zones ?? []) as $zone)
                                    <option value="{{ $zone->id }}">{{ $zone->name }} ({{ $zone->warehouse?->name }})</option>
                                @endforeach
                            </select>
                            @error('zone_id') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="rack_id" class="app-label mb-1.5">Rak (opsional)</label>
                            <select id="rack_id" wire:model.live="rack_id" class="app-select">
                                <option value="">-- Semua Rak --</option>
                                @foreach (($racks ?? []) as $rack)
                                    <option value="{{ $rack->id }}">{{ $rack->name }} ({{ $rack->zone?->name }})</option>
                                @endforeach
                            </select>
                            @error('rack_id') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                        </div>
                    </div>
                @endif
                <div class="sm:col-span-2 lg:col-span-3">
                    <label for="notes" class="app-label mb-1.5">Catatan</label>
                    <textarea id="notes" wire:model="notes" rows="2" class="app-textarea" placeholder="Catatan opname..."></textarea>
                    @error('notes') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                </div>
            </div>
        </x-ui.card>

        @if($opnameId)
            <x-ui.card>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="app-card-title">Daftar Item</h2>
                        <p class="mt-1 text-sm text-app-muted">{{ $opname?->items_count ?? 0 }} item terdaftar dalam opname ini.</p>
                    </div>
                    <div class="flex gap-2">
                        <button type="button" wire:click="generateItems" class="app-btn app-btn-secondary">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            Generate dari Stock
                        </button>
                        @if(($opname?->items_count ?? 0) > 0)
                            <a href="{{ route('stock-opnames.count', $opnameId) }}" class="app-btn app-btn-primary">Mulai Counting</a>
                        @endif
                    </div>
                </div>
            </x-ui.card>
        @else
            @php($counts = App\Models\StockBalance::where('warehouse_id', $warehouse_id)->when($location_id !== '', fn ($q) => $q->where('location_id', $location_id))->count())
            <x-ui.card>
                <h2 class="app-card-title">Daftar Item</h2>
                <p class="mt-1 text-sm text-app-muted">Setelah header disimpan, klik <span class="font-medium text-app-text">Generate dari Stock</span> untuk memuat item dari saldo stok. Tersedia {{ $warehouse_id !== '' ? $counts : 0 }} saldo pada lokasi terpilih.</p>
            </x-ui.card>
        @endif

        <div class="flex justify-end gap-2">
            <a href="{{ $opnameId ? route('stock-opnames.show', $opnameId) : route('stock-opnames.index') }}" class="app-btn app-btn-secondary">Batal</a>
            <button type="submit" wire:loading.attr="disabled" class="app-btn app-btn-primary disabled:opacity-60">
                <span wire:loading.remove wire:target="save">Simpan Draft</span>
                <span wire:loading wire:target="save">Menyimpan…</span>
            </button>
        </div>
    </form>
</div>
