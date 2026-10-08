<div>
    <x-ui.page-header title="Detail Supplier" :subtitle="$supplier->name">
        <x-slot:actions>
            <a href="{{ route('suppliers.index') }}" class="app-btn app-btn-secondary">Kembali</a>
            <a href="{{ route('suppliers.index') }}#edit-{{ $supplier->id }}" class="app-btn app-btn-secondary">Kelola di Daftar</a>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card class="mb-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold text-app-text">{{ $supplier->code }} — {{ $supplier->name }}</h2>
                <p class="mt-1 text-sm text-app-muted">{{ $supplier->address ?? '-' }}</p>
            </div>
            <x-ui.status-badge :status="$supplier->status" />
        </div>
        <dl class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div><dt class="text-xs font-semibold uppercase text-app-muted">Contact Person</dt><dd class="mt-1 text-sm text-app-text">{{ $supplier->contact_person ?? '-' }}</dd></div>
            <div><dt class="text-xs font-semibold uppercase text-app-muted">Telepon</dt><dd class="mt-1 text-sm text-app-text">{{ $supplier->phone ?? '-' }}</dd></div>
            <div><dt class="text-xs font-semibold uppercase text-app-muted">Email</dt><dd class="mt-1 text-sm text-app-text">{{ $supplier->email ?? '-' }}</dd></div>
            <div><dt class="text-xs font-semibold uppercase text-app-muted">Lead Time</dt><dd class="mt-1 text-sm font-medium text-app-text">{{ (int) ($supplier->lead_time_days ?? 7) }}d</dd></div>
            <div><dt class="text-xs font-semibold uppercase text-app-muted">Payment Terms</dt><dd class="mt-1 text-sm text-app-text">{{ $supplier->payment_terms ?? 'NET 30' }}</dd></div>
            <div><dt class="text-xs font-semibold uppercase text-app-muted">Region</dt><dd class="mt-1 text-sm text-app-text">
                    @if($supplier->region)
                        <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-700 ring-1 ring-inset ring-slate-500/10 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-600">{{ $supplier->region }}</span>
                    @else - @endif
                </dd></div>
            <div><dt class="text-xs font-semibold uppercase text-app-muted">Primary Items</dt><dd class="mt-1 text-sm font-semibold text-app-text">{{ $supplier->primary_items_count }} item(s)</dd></div>
            <div><dt class="text-xs font-semibold uppercase text-app-muted">Created</dt><dd class="mt-1 text-sm text-app-muted">{{ to_display_tz($supplier->created_at)?->format('d M Y H:i') ?? '-' }}</dd></div>
            <div><dt class="text-xs font-semibold uppercase text-app-muted">Updated</dt><dd class="mt-1 text-sm text-app-muted">{{ to_display_tz($supplier->updated_at)?->format('d M Y H:i') ?? '-' }}</dd></div>
        </dl>
    </x-ui.card>

    <x-ui.card padding="p-0" class="mb-4">
        <div class="flex flex-wrap gap-2 border-b border-app-border px-4">
            @foreach (['prices' => 'Item Price List', 'orders' => 'PO History', 'performance' => 'Performance'] as $key => $label)
                <button type="button" wire:click="setTab('{{ $key }}')" class="border-b-2 px-3 py-3 text-sm font-medium transition {{ $tab === $key ? 'border-primary-600 text-primary-600 dark:border-primary-400 dark:text-primary-400' : 'border-transparent text-app-muted hover:text-app-text' }}">{{ $label }}</button>
            @endforeach
        </div>
        <div class="p-5">
            @if($tab === 'prices')
                <form wire:submit="addPrice" class="rounded-lg border border-app-border bg-app-surface-2/50 p-4">
                    <h3 class="app-card-title mb-3">Tambah Harga Item</h3>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
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
                            <label class="app-label mb-1">Price (Rp)<span class="text-rose-500">*</span></label>
                            <input type="number" min="0" step="0.01" wire:model="pricePrice" class="app-input">
                            @error('pricePrice') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="app-label mb-1">Lead Time (days)<span class="text-rose-500">*</span></label>
                            <input type="number" min="1" max="365" wire:model="priceLeadTime" class="app-input">
                            @error('priceLeadTime') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:col-span-2 lg:col-span-3">
                            <label class="app-label mb-1">Notes</label>
                            <input type="text" wire:model="priceNotes" maxlength="255" placeholder="Catatan harga..." class="app-input">
                            @error('priceNotes') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                        </div>
                        <div class="flex items-end">
                            <button type="submit" class="app-btn app-btn-primary w-full">Simpan Harga</button>
                        </div>
                    </div>
                </form>

                @if($prices->isEmpty())
                    <x-ui.empty-state title="Belum ada price list" message="Tambahkan harga khusus supplier untuk barang di atas." />
                @else
                    <div class="mt-4 overflow-x-auto">
                        <table class="app-table">
                            <thead>
                                <tr><th>SKU</th><th>Nama Barang</th><th class="text-right">Price</th><th class="text-right">Lead Time</th><th>Notes</th><th class="text-right">Aksi</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($prices as $row)
                                    <tr>
                                        <td class="whitespace-nowrap font-medium text-app-text">{{ $row->item?->sku ?? '-' }}</td>
                                        <td class="text-app-text">{{ $row->item?->name ?? '-' }}</td>
                                        <td class="whitespace-nowrap text-right font-medium text-app-text">Rp {{ number_format((float) $row->price, 0, ',', '.') }}</td>
                                        <td class="whitespace-nowrap text-right text-app-muted">{{ (int) $row->lead_time_days }}d</td>
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
                    <x-ui.empty-state title="Belum ada PO" message="Belum ada purchase order untuk supplier ini." />
                @else
                    <div class="overflow-x-auto">
                        <table class="app-table">
                            <thead><tr><th>Number</th><th>Date</th><th>Warehouse</th><th>Items</th><th>Status</th><th class="text-right">Received / Total</th></tr></thead>
                            <tbody>
                                @foreach ($orders as $po)
                                    <tr>
                                        <td class="whitespace-nowrap font-medium"><a href="{{ route('purchase-orders.show', $po) }}" class="text-primary-600 hover:underline dark:text-primary-400">{{ $po->number }}</a></td>
                                        <td class="whitespace-nowrap text-app-muted">{{ $po->order_date?->format('d M Y') }} <span class="text-xs">→ {{ $po->expected_date?->format('d M Y') ?? '-' }}</span></td>
                                        <td class="text-app-muted">{{ $po->warehouse?->name ?? '-' }}</td>
                                        <td class="max-w-xs truncate text-app-muted">{{ $po->items->map(fn($r) => ($r->item?->sku ?? '?').' ×'.(int)$r->quantity)->implode(', ') ?: '-' }}</td>
                                        <td><x-ui.status-badge :status="$po->status" /></td>
                                        <td class="text-right text-app-text">{{ number_format((int) $po->items->sum('received_quantity')) }} / {{ number_format((int) $po->items->sum('quantity')) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @elseif($tab === 'performance')
                @php
                    $pv = $performance['price_variance'];
                    $score = $performance['score'];
                    $otr = $performance['on_time_rate'];
                    $avgLead = $performance['average_lead_time_actual'];
                @endphp
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <x-ui.stat-card label="Total PO" :value="(string) $performance['total_pos']" hint="Approved {{ $performance['approved_pos'] }} · Received {{ $performance['received_pos'] }}" tone="slate" />
                    <x-ui.stat-card label="On-Time Rate" :value="number_format($otr, 1).'%'" :hint="($otr >= 90 ? 'Excellent' : ($otr >= 60 ? 'Needs attention' : 'At risk')) . ' · Received vs expected_date'" :tone="$otr >= 90 ? 'emerald' : ($otr >= 60 ? 'amber' : 'rose')" />
                    <x-ui.stat-card label="Avg Lead Time (actual)" :value="$avgLead !== null ? number_format($avgLead, 1).'d' : '-'" hint="order_date → max GR posted_at" tone="sky" />
                    <x-ui.stat-card label="Price Variance" :value="$pv !== null ? number_format($pv, 1).'%' : '-'" hint="avg (po_price - catalog)/catalog · negatif = lebih murah" :tone="$pv === null ? 'slate' : (abs($pv) <= 5 ? 'emerald' : (abs($pv) <= 15 ? 'amber' : 'rose'))" />
                    <x-ui.stat-card label="Overall Score" :value="number_format($score, 1)" hint="0.7×on-time + 0.3×price accuracy · 0–100" :tone="$score >= 80 ? 'emerald' : ($score >= 55 ? 'amber' : ($score > 0 ? 'rose' : 'slate'))" />
                    <x-ui.stat-card label="Catalog Coverage" :value="$prices->count().' item(s)'" hint="Baris di supplier_item_prices" tone="indigo" />
                </div>
                <p class="mt-4 text-xs text-app-muted">On-time: setiap PO di mana max GR <code>posted_at</code> ≤ <code>expected_date</code>; jika belum ada GR dianggap on-time (belum jatuh tempo). Lead time actual: <code>order_date → max GR posted_at</code>.</p>
            @endif
        </div>
    </x-ui.card>
</div>
