<div>
    <x-ui.page-header title="Kategori" subtitle="Kelola kategori barang">
        <x-slot:actions>
            <button type="button" wire:click="openCreate" class="app-btn app-btn-primary gap-2">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                Tambah Kategori
            </button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card padding="p-0">
        <div class="border-b border-app-border p-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <label class="relative block w-full sm:max-w-xs">
                    <svg class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-app-muted" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.3-4.3M10 18a8 8 0 110-16 8 8 0 010 16z"/></svg>
                    <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari kode / nama..." class="app-input pl-9">
                </label>
                <select wire:model.live="perPage" class="app-select w-24">
                    @foreach ([10, 25, 50] as $size)
                        <option value="{{ $size }}">{{ $size }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="app-table">
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Nama</th>
                        <th>Deskripsi</th>
                        <th class="text-right">Barang</th>
                        <th>Status</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($categories as $category)
                        <tr>
                            <td class="whitespace-nowrap font-medium">{{ $category->code }}</td>
                            <td>{{ $category->name }}</td>
                            <td class="text-app-muted">{{ \Illuminate\Support\Str::limit($category->description ?? '-', 60) }}</td>
                            <td class="text-right text-app-muted">{{ $category->items_count }}</td>
                            <td class="whitespace-nowrap"><x-ui.status-badge :status="$category->status" /></td>
                            <td class="whitespace-nowrap text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <button type="button" wire:click="openEdit({{ $category->id }})" class="app-btn app-btn-ghost !p-1.5" title="Edit">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487z"/></svg>
                                    </button>
                                    <x-ui.confirm action="delete" :params="[$category->id]" title="Hapus Kategori" :message="'Hapus ' . $category->name . '?'" confirm-label="Hapus" variant="danger" aria-label="Delete" class="app-btn app-btn-ghost !p-1.5 hover:!text-rose-600 dark:hover:!text-rose-400"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg></x-ui.confirm>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-ui.empty-state title="Tidak ada kategori" message="Belum ada kategori yang cocok dengan pencarian." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($categories->hasPages())
            <div class="app-card-body border-t border-app-border py-3">{{ $categories->links() }}</div>
        @endif
    </x-ui.card>

    @if ($showModal)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/60 p-4">
            <div class="app-card w-full max-w-md p-5">
                <h2 class="text-lg font-semibold text-app-text">{{ $editingId ? 'Edit Kategori' : 'Tambah Kategori' }}</h2>
                <form wire:submit="save" class="mt-4 space-y-4">
                    <div>
                        <label class="app-label mb-1">Kode<span class="text-rose-500">*</span></label>
                        <input type="text" wire:model="code" class="app-input">
                        @error('code') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="app-label mb-1">Nama<span class="text-rose-500">*</span></label>
                        <input type="text" wire:model="name" class="app-input">
                        @error('name') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="app-label mb-1">Deskripsi</label>
                        <textarea wire:model="description" rows="2" class="app-input min-h-[60px] resize-y"></textarea>
                        @error('description') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
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
