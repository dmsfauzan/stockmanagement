<div>
    <x-ui.page-header title="Form Perakitan" subtitle="Rakit atau bongkar barang kit">
        <x-slot:actions>
            <a href="{{ route('assembly-orders.index') }}" class="app-btn app-btn-secondary">{{ __('Batal') }}</a>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card class="max-w-3xl">
        <form class="space-y-4">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="app-label mb-1.5">{{ __('Tipe') }}<span class="text-rose-500">*</span></label>
                    <select wire:model.live="type" class="app-select">
                        @foreach ($types as $type)
                            <option value="{{ $type->value }}">{{ $type->label() }}</option>
                        @endforeach
                    </select>
                    @error('type') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="app-label mb-1.5">{{ __('Tanggal') }}<span class="text-rose-500">*</span></label>
                    <input type="date" wire:model="assembly_date" class="app-input">
                    @error('assembly_date') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="app-label mb-1.5">{{ __('Barang Kit') }}<span class="text-rose-500">*</span></label>
                    <select wire:model.live="item_id" class="app-select">
                        <option value="">-- Pilih Barang --</option>
                        @foreach ($itemsList as $item)
                            <option value="{{ $item->id }}">{{ $item->sku }} — {{ $item->name }}</option>
                        @endforeach
                    </select>
                    @error('item_id') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="app-label mb-1.5">{{ __('Qty') }}<span class="text-rose-500">*</span></label>
                    <input type="number" min="1" wire:model.live="quantity" class="app-input">
                    @error('quantity') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="app-label mb-1.5">Warehouse<span class="text-rose-500">*</span></label>
                    <select wire:model.live="warehouse_id" class="app-select">
                        <option value="">-- Pilih --</option>
                        @foreach ($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                        @endforeach
                    </select>
                    @error('warehouse_id') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="app-label mb-1.5">{{ __('Lokasi') }}<span class="text-rose-500">*</span></label>
                    <select wire:model="location_id" class="app-select">
                        <option value="">-- Pilih --</option>
                        @foreach ($locations as $location)
                            <option value="{{ $location->id }}">{{ $location->code }}</option>
                        @endforeach
                    </select>
                    @error('location_id') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="sm:col-span-2">
                <label class="app-label mb-1.5">{{ __('Catatan') }}</label>
                <textarea wire:model="notes" rows="2" class="app-textarea"></textarea>
            </div>

            @if ($item_id !== '')
                <div class="rounded-lg border border-app-border p-4">
                    <h3 class="app-card-title mb-3">{{ __('Ketersediaan Komponen') }}</h3>
                    @if ($components->isEmpty())
                        <p class="text-sm text-app-muted">{{ __('Barang ini belum memiliki BOM.') }}</p>
                    @else
                        <table class="app-table">
                            <thead><tr><th>{{ __('Komponen') }}</th><th class="text-right">{{ __('Butuh') }}</th><th class="text-right">{{ __('Tersedia') }}</th></tr></thead>
                            <tbody>
                                @foreach ($components as $component)
                                    @php $need = (int) $component->quantity * max(1, (int) ($quantity ?? 1)); $have = (int) ($availability[(int) $component->component_item_id] ?? 0); @endphp
                                    <tr>
                                        <td class="text-app-text">{{ $component->component?->sku }} — {{ $component->component?->name }}</td>
                                        <td class="text-right">{{ number_format($need) }}</td>
                                        <td @class(['text-right', 'font-semibold text-rose-600' => $have < $need])>{{ number_format($have) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        <p class="mt-2 text-xs text-app-muted">{{ __('Stok kit saat ini') }}: {{ number_format($kitOnHand) }}</p>
                    @endif
                </div>
            @endif

            <div class="flex justify-end gap-2">
                <button type="button" wire:click="save(false)" wire:loading.attr="disabled" class="app-btn app-btn-secondary">{{ __('Simpan Draf') }}</button>
                @can('assembly.post')
                    <button type="button" wire:click="save(true)" wire:loading.attr="disabled" class="app-btn app-btn-primary">{{ __('Simpan & Posting') }}</button>
                @endcan
            </div>
        </form>
    </x-ui.card>
</div>
