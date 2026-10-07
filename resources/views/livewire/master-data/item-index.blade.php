<div>
    <x-ui.page-header title="Barang" subtitle="Kelola master data barang">
        <x-slot:actions>
            @can('items.create')
                <a href="{{ route('items.create') }}" class="app-btn app-btn-primary gap-2">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                    Tambah Barang
                </a>
            @endcan
            <button type="button" wire:click="export" class="app-btn app-btn-secondary gap-2">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                Export
            </button>
            @can('items.create')
                <button type="button" wire:click="openImportModal" class="app-btn app-btn-secondary gap-2">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
                    Import
                </button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    @if (count($selectedIds) > 0)
        <div class="mt-3 flex flex-wrap items-center gap-2">
            <span class="text-xs font-medium text-app-text">{{ count($selectedIds) }} dipilih</span>
            <x-ui.confirm action="bulkActivate" title="Aktifkan massal" :message="'Aktifkan '.count($selectedIds).' barang terpilih?'" confirm-label="Aktifkan" class="app-btn app-btn-secondary app-btn-sm">Aktifkan</x-ui.confirm>
            <x-ui.confirm action="bulkDeactivate" title="Nonaktifkan massal" :message="'Nonaktifkan '.count($selectedIds).' barang terpilih?'" confirm-label="Nonaktifkan" class="app-btn app-btn-secondary app-btn-sm">Nonaktifkan</x-ui.confirm>
            <x-ui.confirm action="bulkDelete" title="Hapus massal" :message="'Hapus '.count($selectedIds).' barang terpilih?'" confirm-label="Hapus" variant="danger" class="app-btn app-btn-danger app-btn-sm">Hapus</x-ui.confirm>
            <x-ui.confirm action="bulkRestore" title="Pulihkan massal" :message="'Pulihkan '.count($selectedIds).' barang terpilih?'" confirm-label="Pulihkan" class="app-btn app-btn-secondary app-btn-sm">Pulihkan</x-ui.confirm>
        </div>
    @endif

    <div
        x-data="{
            selectedCount: 0,
            refreshCount() { this.selectedCount = document.querySelectorAll('input[name=\'labelIds\']:checked').length },
            openBulk(format = 'qr') {
                const ids = [...document.querySelectorAll('input[name=\'labelIds\']:checked')].map(el => el.value);
                if (!ids.length) { this.$dispatch('toast', { type: 'warning', message: 'Pilih setidaknya satu barang.' }); return; }
                const qs = ids.map(v => 'ids[]=' + encodeURIComponent(v)).join('&') + '&format=' + encodeURIComponent(format);
                window.open('{{ route('labels.bulk') }}?' + qs, '_blank');
            },
            toggleAll(e) {
                const checked = e.target.checked;
                document.querySelectorAll('input[name=\'labelIds\']').forEach(el => el.checked = checked);
                this.refreshCount();
            }
        }"
        x-init="refreshCount()"
    >
    <x-ui.card padding="p-0">
        <div class="border-b border-app-border p-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex flex-1 flex-col gap-3 sm:flex-row sm:flex-wrap">
                    <label class="relative block w-full sm:max-w-xs">
                        <svg class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-app-muted" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.3-4.3M10 18a8 8 0 110-16 8 8 0 010 16z"/></svg>
                        <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari SKU / barcode / nama..." class="app-input pl-9">
                    </label>
                    <select wire:model.live="categoryFilter" class="app-select w-full sm:w-auto">
                        <option value="">Semua Kategori</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                    <select wire:model.live="statusFilter" class="app-select w-full sm:w-auto">
                        <option value="">Semua Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                    <select wire:model.live="trashedFilter" class="app-select w-full sm:w-auto">
                        <option value="">Aktif</option>
                        <option value="trashed">Terhapus</option>
                        <option value="all">Semua</option>
                    </select>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    @can('items.view')
                        <div class="flex items-center gap-1">
                            <button type="button" @click="openBulk('qr')" :disabled="selectedCount === 0" :class="selectedCount === 0 ? 'opacity-50 cursor-not-allowed' : ''" class="app-btn app-btn-secondary gap-1.5 text-xs" title="Cetak label barang terpilih">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3.75H3.75v2.25h3V3.75zM6.75 17.25H3.75v2.25h3v-2.25zM17.25 3.75h-2.25v2.25h2.25V3.75zM17.25 14.25h-5.25v5.25h5.25v-5.25zM10.5 3.75H7.5v2.25h3V3.75zM10.5 6H7.5v2.25h3V6zM13.5 10.5h2.25V12H13.5z"/></svg>
                                Cetak Label
                            </button>
                            <a href="{{ route('labels.print') }}" target="_blank" class="app-btn app-btn-secondary gap-1.5 text-xs" title="Cetak massal dengan filter/pencarian">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.9 12h10.2M6.9 12L9.75 9M6.9 12l2.85 3M9 21H6a2 2 0 01-2-2V6a2 2 0 012-2h9l5 5v10a2 2 0 01-2 2h-3"/></svg>
                                Cetak per Filter
                            </a>
                        </div>
                    @endcan
                    <span class="text-xs text-app-muted">Per halaman</span>
                    <select wire:model.live="perPage" class="app-select w-auto">
                        @foreach ([10, 25, 50, 100] as $size)
                            <option value="{{ $size }}">{{ $size }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="app-table">
                <thead>
                    <tr>
                        <th class="w-10"><input type="checkbox" wire:model.live="selectAll" @change="toggleAll($event)" class="rounded border-slate-300 text-primary-600 focus:ring-primary-500" aria-label="Pilih semua"></th>
                        <th class="w-12"></th>
                        <th>
                            <button type="button" wire:click="sortBy('sku')" class="inline-flex items-center gap-1 hover:text-app-text">
                                SKU
                                @if ($sortField === 'sku')
                                    <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </button>
                        </th>
                        <th>Barcode</th>
                        <th>
                            <button type="button" wire:click="sortBy('name')" class="inline-flex items-center gap-1 hover:text-app-text">
                                Nama
                                @if ($sortField === 'name')
                                    <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </button>
                        </th>
                        <th>Kategori</th>
                        <th>Unit</th>
                        <th class="text-right">Min</th>
                        <th class="text-right">Max</th>
                        <th>Status</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        <tr>
                            <td><input type="checkbox" name="labelIds" value="{{ $item->id }}" wire:model.live="selectedIds" @change="refreshCount()" class="rounded border-slate-300 text-primary-600 focus:ring-primary-500"></td>
                            <td>
                                @if($item->thumbUrl())
                                    <img src="{{ $item->thumbUrl() }}" alt="{{ $item->name }}" class="h-8 w-8 rounded object-cover" loading="lazy">
                                @else
                                    <span class="flex h-8 w-8 items-center justify-center rounded bg-app-surface-2 text-app-muted">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3.75 21V3a.75.75 0 01.75-.75h15a.75.75 0 01.75.75v18a.75.75 0 01-.75.75H4.5a.75.75 0 01-.75-.75z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"/></svg>
                                    </span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap font-medium">{{ $item->sku }}</td>
                            <td class="whitespace-nowrap text-app-muted">{{ $item->barcode ?? '-' }}</td>
                            <td>{{ $item->name }}</td>
                            <td class="whitespace-nowrap text-app-muted">{{ $item->category?->name ?? '-' }}</td>
                            <td class="whitespace-nowrap text-app-muted">{{ $item->unit?->name ?? '-' }}</td>
                            <td class="whitespace-nowrap text-right text-app-muted">{{ $item->minimum_stock }}</td>
                            <td class="whitespace-nowrap text-right text-app-muted">{{ $item->maximum_stock }}</td>
                            <td class="whitespace-nowrap">
                                @if ($item->trashed())
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500 ring-1 ring-inset ring-slate-500/10 dark:bg-slate-800 dark:text-slate-400 dark:ring-slate-600">Terhapus</span>
                                @else
                                    <x-ui.status-badge :status="$item->status" />
                                @endif
                            </td>
                            <td class="whitespace-nowrap text-right">
                                <div class="flex items-center justify-end gap-1">
                                    @if ($item->trashed())
                                        <x-ui.confirm action="restore" :params="[$item->id]" title="Pulihkan Barang" :message="'Pulihkan ' . $item->sku . ' - ' . $item->name . '?'" confirm-label="Pulihkan" class="app-btn app-btn-ghost app-btn-sm">Pulihkan</x-ui.confirm>
                                    @else
                                        <a href="{{ route('items.show', $item) }}" class="app-btn app-btn-ghost !p-1.5" title="View">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                        </a>
                                        @can('update', $item)
                                            <a href="{{ route('items.edit', $item) }}" class="app-btn app-btn-ghost !p-1.5" title="Edit">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487z"/></svg>
                                            </a>
                                        @endcan
                                        @can('delete', $item)
                                            <x-ui.confirm action="deleteItem" :params="[$item->id]" title="Hapus Barang" :message="'Hapus ' . $item->sku . ' - ' . $item->name . '?'" confirm-label="Hapus" variant="danger" aria-label="Hapus" class="app-btn app-btn-ghost !p-1.5 hover:!text-rose-600 dark:hover:!text-rose-400">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
                                            </x-ui.confirm>
                                        @endcan
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11">
                                <x-ui.empty-state title="Tidak ada barang" message="Belum ada data barang yang cocok dengan filter." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($items->hasPages())
            <div class="border-t border-app-border px-4 py-3">
                {{ $items->links() }}
            </div>
        @endif
    </x-ui.card>
    </div>

    @if ($showImportModal)
        <div class="fixed inset-0 z-[55] flex items-center justify-center p-4" role="dialog" aria-modal="true">
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" wire:click="$set('showImportModal', false)"></div>
            <div class="relative w-full max-w-xl rounded-xl border border-app-border bg-app-surface p-6 shadow-popover">
                <h3 class="text-base font-semibold text-app-text">Import Barang</h3>
                <p class="mt-1 text-sm text-app-muted">Unggah file Excel/CSV dengan kolom: <code class="rounded bg-app-surface-2 px-1 text-xs">sku, barcode, name, category_code, unit_code, brand, minimum_stock, maximum_stock, supplier_code, status</code>.</p>

                <div class="mt-4">
                    <button type="button" wire:click="downloadImportTemplate" class="app-btn app-btn-ghost gap-1.5 text-xs">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                        Unduh Template CSV
                    </button>
                </div>

                <div class="mt-4 space-y-3">
                    <label class="app-label">File</label>
                    <input type="file" wire:model="importFile" accept=".xlsx,.xls,.csv" class="app-input text-sm">
                    @error('importFile') <p class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror

                    <div wire:loading wire:target="importFile,import" class="text-xs text-app-muted">Memproses…</div>

                    <p class="text-xs text-app-muted">File akan diproses di latar belakang. Anda akan menerima notifikasi saat selesai (periksa bell notifikasi).</p>
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" wire:click="$set('showImportModal', false)" class="app-btn app-btn-secondary">Tutup</button>
                    <button type="button" wire:click="import" wire:loading.attr="disabled" class="app-btn app-btn-primary">Import</button>
                </div>
            </div>
        </div>
    @endif
</div>
