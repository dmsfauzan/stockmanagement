<div>
    <x-ui.page-header title="Detail Barang" subtitle="{{ $itemModel->name }}">
        <x-slot:actions>
            <a href="{{ route('items.index') }}" class="app-btn app-btn-secondary">Kembali</a>
            @can('view', $itemModel)
                <span class="flex items-center gap-1.5">
                    <a href="{{ route('labels.item', $itemModel) }}?format=qr" target="_blank" rel="noopener" class="app-btn app-btn-secondary !py-1.5 text-xs">Cetak Label (QR)</a>
                    <a href="{{ route('labels.item', $itemModel) }}?format=barcode" target="_blank" rel="noopener" class="app-btn app-btn-secondary !py-1.5 text-xs">Cetak (Barcode)</a>
                    <a href="{{ route('labels.item', $itemModel) }}?format=both" target="_blank" rel="noopener" class="app-btn app-btn-secondary !py-1.5 text-xs">Keduanya</a>
                </span>
            @endcan
            @can('update', $itemModel)
                <a href="{{ route('items.edit', $itemModel) }}" class="app-btn app-btn-primary">Edit</a>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card padding="p-0" class="mb-4">
        <div class="flex flex-wrap gap-2 border-b border-app-border px-4">
            @foreach (['info' => 'Info', 'inventory' => 'Inventory', 'movement' => 'Movement', 'summary' => 'Summary'] as $key => $label)
                <button type="button" wire:click="setTab('{{ $key }}')" class="border-b-2 px-3 py-3 text-sm font-medium transition {{ $tab === $key ? 'border-primary-600 text-primary-600 dark:border-primary-400 dark:text-primary-400' : 'border-transparent text-app-muted hover:text-app-text' }}">{{ $label }}</button>
            @endforeach
        </div>

        <div class="p-5">
            @if ($tab === 'info')
                <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div><dt class="text-xs font-semibold uppercase text-app-muted">SKU</dt><dd class="mt-1 text-sm font-medium text-app-text">{{ $itemModel->sku }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase text-app-muted">Barcode</dt><dd class="mt-1 text-sm text-app-text">{{ $itemModel->barcode ?? '-' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase text-app-muted">Nama</dt><dd class="mt-1 text-sm text-app-text">{{ $itemModel->name }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase text-app-muted">Brand</dt><dd class="mt-1 text-sm text-app-text">{{ $itemModel->brand ?? '-' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase text-app-muted">Kategori</dt><dd class="mt-1 text-sm text-app-text">{{ $itemModel->category?->name ?? '-' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase text-app-muted">Satuan</dt><dd class="mt-1 text-sm text-app-text">{{ $itemModel->unit?->name ?? '-' }} {{ $itemModel->unit?->code ? '(' . $itemModel->unit->code . ')' : '' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase text-app-muted">Supplier</dt><dd class="mt-1 text-sm text-app-text">{{ $itemModel->primarySupplier?->name ?? '-' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase text-app-muted">Status</dt><dd class="mt-1"><x-ui.status-badge :status="$itemModel->status" /></dd></div>
                    <div><dt class="text-xs font-semibold uppercase text-app-muted">Min / Max</dt><dd class="mt-1 text-sm text-app-text">{{ $itemModel->minimum_stock }} / {{ $itemModel->maximum_stock }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-xs font-semibold uppercase text-app-muted">Deskripsi</dt><dd class="mt-1 text-sm text-app-text">{{ $itemModel->description ?? '-' }}</dd></div>
                </dl>
            @elseif ($tab === 'inventory')
                @if ($balances->isEmpty())
                    <x-ui.empty-state title="Belum ada stok" message="Tidak ada stock balance untuk barang ini." />
                @else
                    <div class="overflow-x-auto">
                        <table class="app-table">
                            <thead>
                                <tr>
                                    <th>Warehouse</th>
                                    <th>Location</th>
                                    <th class="text-right">On Hand</th>
                                    <th class="text-right">Reserved</th>
                                    <th class="text-right">Available</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($balances as $balance)
                                    <tr>
                                        <td>{{ $balance->warehouse?->name ?? '-' }}</td>
                                        <td class="text-app-muted">{{ $balance->location?->fullPath() ?? '-' }}</td>
                                        <td class="text-right font-medium">{{ $balance->quantity_on_hand }}</td>
                                        <td class="text-right text-app-muted">{{ $balance->quantity_reserved }}</td>
                                        <td class="text-right text-app-muted">{{ $balance->quantity_on_hand - $balance->quantity_reserved }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @elseif ($tab === 'movement')
                @if ($movements->isEmpty())
                    <x-ui.empty-state title="Belum ada movement" message="Tidak ada pergerakan stok untuk barang ini." />
                @else
                    <div class="overflow-x-auto">
                        <table class="app-table">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Type</th>
                                    <th>Warehouse / Location</th>
                                    <th class="text-right">In</th>
                                    <th class="text-right">Out</th>
                                    <th class="text-right">Balance</th>
                                    <th>User</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($movements as $movement)
                                    <tr>
                                        <td class="whitespace-nowrap text-app-muted">{{ $movement->created_at?->format('d M Y H:i') ?? '-' }}</td>
                                        <td><x-ui.status-badge :status="$movement->transaction_type" /></td>
                                        <td class="text-app-muted">{{ $movement->warehouse?->name ?? '-' }}{{ $movement->location ? ' / ' . $movement->location->code : '' }}</td>
                                        <td class="text-right text-emerald-600 dark:text-emerald-400">{{ $movement->quantity_in > 0 ? $movement->quantity_in : '-' }}</td>
                                        <td class="text-right text-rose-600 dark:text-rose-400">{{ $movement->quantity_out > 0 ? $movement->quantity_out : '-' }}</td>
                                        <td class="text-right font-medium">{{ $movement->balance_after }}</td>
                                        <td class="text-app-muted">{{ $movement->creator?->name ?? '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @elseif ($tab === 'summary')
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div class="app-card p-4">
                        <p class="text-xs font-semibold uppercase text-app-muted">Total On Hand</p>
                        <p class="mt-2 text-2xl font-semibold text-app-text">{{ $totalOnHand }}</p>
                    </div>
                    <div class="app-card p-4">
                        <p class="text-xs font-semibold uppercase text-app-muted">Total Available</p>
                        <p class="mt-2 text-2xl font-semibold text-app-text">{{ $totalAvailable }}</p>
                        <p class="mt-1 text-xs text-app-muted">Reserved {{ $totalReserved }}</p>
                    </div>
                    <div class="app-card p-4">
                        <p class="text-xs font-semibold uppercase text-app-muted">Stock Status</p>
                        <p class="mt-2"><x-ui.status-badge :status="$stockStatus->value" :label="$stockStatus->label()" /></p>
                        <p class="mt-2 text-xs text-app-muted">Min {{ $itemModel->minimum_stock }} / Max {{ $itemModel->maximum_stock }}</p>
                    </div>
                </div>
                <dl class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div><dt class="text-xs font-semibold uppercase text-app-muted">Lokasi aktif</dt><dd class="mt-1 text-sm text-app-text">{{ $balances->count() }} location(s) membawa stok barang ini</dd></div>
                    <div><dt class="text-xs font-semibold uppercase text-app-muted">Movement terbaru</dt><dd class="mt-1 text-sm text-app-text">{{ $movements->count() }} baris (20 terbaru)</dd></div>
                </dl>
            @endif
        </div>
    </x-ui.card>

    <div x-data="{ open: false }">
        <x-ui.card>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 class="app-card-title">Riwayat Audit</h2>
                    <p class="mt-1 text-sm text-app-muted">Jejak perubahan data barang ini.</p>
                </div>
                <button type="button" @click="open = true" class="app-btn app-btn-secondary app-btn-sm">Lihat Riwayat</button>
            </div>
        </x-ui.card>

        <div x-show="open" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center p-4" role="dialog" aria-modal="true">
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="open = false"></div>
            <div class="relative flex max-h-[90vh] w-full max-w-3xl flex-col rounded-xl border border-app-border bg-app-surface shadow-popover">
                <div class="flex items-center justify-between border-b border-app-border px-5 py-3">
                    <h3 class="text-base font-semibold text-app-text">Riwayat Audit Barang</h3>
                    <button type="button" @click="open = false" class="app-btn app-btn-ghost !p-1.5 text-lg leading-none">&times;</button>
                </div>
                <div class="overflow-y-auto p-5">
                    <x-ui.audit-history :auditableType="\App\Models\Item::class" :auditableId="$itemModel->id" />
                </div>
            </div>
        </div>
    </div>
</div>
