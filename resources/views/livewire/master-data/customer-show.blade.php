<div>
    <x-ui.page-header title="{{ __('Detail Customer') }}" :subtitle="$customer->name">
        <x-slot:actions>
            <a href="{{ route('customers.index') }}" class="app-btn app-btn-secondary">{{ __('Kembali') }}</a>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card class="mb-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold text-app-text">{{ $customer->code }} — {{ $customer->name }}</h2>
                <p class="mt-1 text-sm text-app-muted">{{ $customer->address ?? '-' }}</p>
            </div>
            <x-ui.status-badge :status="$customer->status" />
        </div>
        <dl class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div><dt class="text-xs font-semibold uppercase text-app-muted">{{ __('Tipe') }}</dt><dd class="mt-1 text-sm text-app-text">{{ $typeLabel }}</dd></div>
            <div><dt class="text-xs font-semibold uppercase text-app-muted">{{ __('Kontak') }}</dt><dd class="mt-1 text-sm text-app-text">{{ $customer->contact_person ?? '-' }}</dd></div>
            <div><dt class="text-xs font-semibold uppercase text-app-muted">{{ __('Telepon') }}</dt><dd class="mt-1 text-sm text-app-text">{{ $customer->phone ?? '-' }}</dd></div>
            <div><dt class="text-xs font-semibold uppercase text-app-muted">Email</dt><dd class="mt-1 text-sm text-app-text">{{ $customer->email ?? '-' }}</dd></div>
        </dl>
    </x-ui.card>

    <x-ui.card padding="p-0" class="mb-4">
        <div class="flex flex-wrap gap-2 border-b border-app-border px-4">
            @foreach (['prices' => 'Harga Jual', 'orders' => 'SO History'] as $key => $label)
                <button type="button" wire:click="setTab('{{ $key }}')" class="border-b-2 px-3 py-3 text-sm font-medium transition {{ $tab === $key ? 'border-primary-600 text-primary-600 dark:border-primary-400 dark:text-primary-400' : 'border-transparent text-app-muted hover:text-app-text' }}">{{ $label }}</button>
            @endforeach
        </div>
        <div class="p-5">
            @if($tab === 'prices')
                <form wire:submit="addPrice" class="rounded-lg border border-app-border bg-app-surface-2/50 p-4">
                    <h3 class="app-card-title mb-3">{{ __('Tambah Harga Jual') }}</h3>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                        <div class="sm:col-span-2">
                            <label class="app-label mb-1">Item<span class="text-rose-500">*</span></label>
                            <select wire:model="priceItemId" class="app-select">
                                <option value="">-- Pilih Item --</option>
                                @foreach ($itemsList as $it)
                                    <option value="{{ $it->id }}">{{ $it->sku }} — {{ $it->name }}</option>
                                @endforeach
                            </select>
                            @error('priceItemId') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="app-label mb-1">Min Qty<span class="text-rose-500">*</span></label>
                            <input type="number" min="1" wire:model="priceMinQty" class="app-input">
                            @error('priceMinQty') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="app-label mb-1">Price (Rp)<span class="text-rose-500">*</span></label>
                            <input type="number" min="0" step="0.01" wire:model="pricePrice" class="app-input">
                            @error('pricePrice') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="app-label mb-1">Notes</label>
                            <input type="text" wire:model="priceNotes" maxlength="255" placeholder="Catatan harga..." class="app-input">
                            @error('priceNotes') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                        </div>
                        <div class="flex items-end">
                            <button type="submit" class="app-btn app-btn-primary w-full">{{ __('Simpan Harga') }}</button>
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-app-muted">{{ __('Min Qty menentukan tier harga; SO otomatis memakai tier terbesar yang <= qty.') }}</p>
                </form>

                @if($prices->isEmpty())
                    <x-ui.empty-state title="{{ __('Belum ada price list') }}" message="{{ __('Tambahkan harga khusus customer untuk barang di atas.') }}" />
                @else
                    <div class="mt-4 overflow-x-auto">
                        <table class="app-table">
                            <thead>
                                <tr><th>SKU</th><th>{{ __('Nama') }}</th><th class="text-right">Min Qty</th><th class="text-right">Price</th><th>Notes</th><th class="text-right">{{ __('Aksi') }}</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($prices as $row)
                                    <tr>
                                        <td class="whitespace-nowrap font-medium text-app-text">{{ $row->item?->sku ?? '-' }}</td>
                                        <td class="text-app-text">{{ $row->item?->name ?? '-' }}</td>
                                        <td class="whitespace-nowrap text-right text-app-muted">{{ number_format((int) $row->min_quantity) }}</td>
                                        <td class="whitespace-nowrap text-right font-medium text-app-text">Rp {{ number_format((float) $row->price, 0, ',', '.') }}</td>
                                        <td class="text-app-muted">{{ $row->notes ?? '-' }}</td>
                                        <td class="text-right"><x-ui.confirm action="deletePrice" :params="[$row->id]" title="Hapus Harga" :message="'Hapus harga untuk ' . ($row->item?->sku ?? '#'.$row->id) . '?'" confirm-label="Hapus" variant="danger" class="app-btn app-btn-ghost !p-1.5 hover:!text-rose-600 dark:hover:!text-rose-400"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></x-ui.confirm></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @elseif($tab === 'orders')
                @if($orders->isEmpty())
                    <x-ui.empty-state title="{{ __('Belum ada SO') }}" message="{{ __('Belum ada sales order untuk customer ini.') }}" />
                @else
                    <div class="overflow-x-auto">
                        <table class="app-table">
                            <thead><tr><th>Number</th><th>{{ __('Tanggal') }}</th><th>{{ __('Gudang') }}</th><th>Items</th><th>Status</th></tr></thead>
                            <tbody>
                                @foreach ($orders as $so)
                                    <tr>
                                        <td class="whitespace-nowrap font-medium"><a href="{{ route('sales-orders.show', $so) }}" class="text-primary-600 hover:underline dark:text-primary-400">{{ $so->number }}</a></td>
                                        <td class="whitespace-nowrap text-app-muted">{{ $so->order_date?->format('d M Y') }}</td>
                                        <td class="text-app-muted">{{ $so->warehouse?->name ?? '-' }}</td>
                                        <td class="max-w-xs truncate text-app-muted">{{ $so->items->map(fn ($r) => ($r->item?->sku ?? '?').' x'.(int) $r->quantity)->implode(', ') ?: '-' }}</td>
                                        <td><x-ui.status-badge :status="$so->status" /></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @endif
        </div>
    </x-ui.card>
</div>
