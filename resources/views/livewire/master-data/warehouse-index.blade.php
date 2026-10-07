<div>
    <x-ui.page-header title="Warehouse" subtitle="Kelola gudang dan struktur penyimpanan">
        <x-slot:actions>
            <button type="button" wire:click="export" class="app-btn app-btn-secondary gap-2">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                Export
            </button>
            <button type="button" wire:click="openCreate" class="app-btn app-btn-primary gap-2">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                Tambah Warehouse
            </button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card padding="p-0">
        <div class="border-b border-app-border p-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex flex-1 flex-col gap-3 sm:flex-row sm:flex-wrap">
                    <label class="relative block w-full sm:max-w-xs">
                        <svg class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-app-muted" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.3-4.3M10 18a8 8 0 110-16 8 8 0 010 16z"/></svg>
                        <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari kode / nama..." class="app-input pl-9">
                    </label>
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
                <select wire:model.live="perPage" class="app-select w-24">
                    @foreach ([10, 25, 50] as $size)
                        <option value="{{ $size }}">{{ $size }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        @if (count($selectedIds) > 0)
            <div class="flex flex-wrap items-center gap-2 border-b border-app-border px-4 py-3">
                <span class="text-xs font-medium text-app-text">{{ count($selectedIds) }} dipilih</span>
                <x-ui.confirm action="bulkActivate" title="Aktifkan massal" :message="'Aktifkan '.count($selectedIds).' warehouse terpilih?'" confirm-label="Aktifkan" class="app-btn app-btn-secondary app-btn-sm">Aktifkan</x-ui.confirm>
                <x-ui.confirm action="bulkDeactivate" title="Nonaktifkan massal" :message="'Nonaktifkan '.count($selectedIds).' warehouse terpilih?'" confirm-label="Nonaktifkan" class="app-btn app-btn-secondary app-btn-sm">Nonaktifkan</x-ui.confirm>
                <x-ui.confirm action="bulkDelete" title="Hapus massal" :message="'Hapus '.count($selectedIds).' warehouse terpilih?'" confirm-label="Hapus" variant="danger" class="app-btn app-btn-danger app-btn-sm">Hapus</x-ui.confirm>
                <x-ui.confirm action="bulkRestore" title="Pulihkan massal" :message="'Pulihkan '.count($selectedIds).' warehouse terpilih?'" confirm-label="Pulihkan" class="app-btn app-btn-secondary app-btn-sm">Pulihkan</x-ui.confirm>
            </div>
        @endif

        <div class="overflow-x-auto">
            <table class="app-table">
                <thead>
                    <tr>
                        <th class="w-8"><input type="checkbox" wire:model.live="selectAll" class="rounded border-slate-300 text-primary-600 focus:ring-primary-500"></th>
                        <th>Kode</th>
                        <th>Nama</th>
                        <th>Alamat</th>
                        <th class="text-right">Zones</th>
                        <th>Status</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($warehouses as $warehouse)
                        <tr>
                            <td><input type="checkbox" value="{{ $warehouse->id }}" wire:model.live="selectedIds" class="rounded border-slate-300 text-primary-600 focus:ring-primary-500"></td>
                            <td class="whitespace-nowrap font-medium">{{ $warehouse->code }}</td>
                            <td>{{ $warehouse->name }}</td>
                            <td class="text-app-muted">{{ \Illuminate\Support\Str::limit($warehouse->address ?? '-', 50) }}</td>
                            <td class="text-right text-app-muted">{{ $warehouse->zones_count }}</td>
                            <td class="whitespace-nowrap"><x-ui.status-badge :status="$warehouse->status" /></td>
                            <td class="whitespace-nowrap text-right">
                                <div class="flex items-center justify-end gap-1">
                                    @if ($warehouse->trashed())
                                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500 ring-1 ring-inset ring-slate-500/10 dark:bg-slate-800 dark:text-slate-400 dark:ring-slate-600">Terhapus</span>
                                        <x-ui.confirm action="restore" :params="[$warehouse->id]" title="Pulihkan Data" message="Pulihkan data ini?" confirm-label="Pulihkan" class="app-btn app-btn-ghost app-btn-sm">Pulihkan</x-ui.confirm>
                                    @else
                                        <button type="button" wire:click="openEdit({{ $warehouse->id }})" class="app-btn app-btn-ghost !p-1.5" title="Edit">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487z"/></svg>
                                        </button>
                                        <x-ui.confirm action="delete" :params="[$warehouse->id]" title="Hapus Warehouse" :message="'Hapus ' . $warehouse->name . '?'" confirm-label="Hapus" variant="danger" aria-label="Delete" class="app-btn app-btn-ghost !p-1.5 hover:!text-rose-600 dark:hover:!text-rose-400"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg></x-ui.confirm>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-ui.empty-state title="Tidak ada warehouse" message="Belum ada warehouse yang cocok dengan pencarian." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($warehouses->hasPages())
            <div class="app-card-body border-t border-app-border py-3">{{ $warehouses->links() }}</div>
        @endif
    </x-ui.card>

    @if ($showModal)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/60 p-4">
            <div class="app-card w-full max-w-md p-5">
                <h2 class="text-lg font-semibold text-app-text">{{ $editingId ? 'Edit Warehouse' : 'Tambah Warehouse' }}</h2>
                <form wire:submit="save" class="mt-4 space-y-4">
                    <div>
                        <label class="app-label mb-1">Kode<span class="text-rose-500">*</span></label>
                        <input type="text" wire:model="code" class="app-input" placeholder="WH-JKT">
                        @error('code') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="app-label mb-1">Nama<span class="text-rose-500">*</span></label>
                        <input type="text" wire:model="name" class="app-input" placeholder="Warehouse Jakarta">
                        @error('name') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="app-label mb-1">Alamat</label>
                        <textarea wire:model="address" rows="2" class="app-input min-h-[60px] resize-y"></textarea>
                        @error('address') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="app-label mb-1">Status<span class="text-rose-500">*</span></label>
                        <select wire:model="status" class="app-select">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                        @error('status') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="closeModal" class="app-btn app-btn-secondary">Batal</button>
                        <button type="submit" class="app-btn app-btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
