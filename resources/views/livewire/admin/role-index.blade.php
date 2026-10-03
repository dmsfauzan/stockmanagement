<div>
    <x-ui.page-header title="Roles & Permissions" subtitle="Kelola peran dan hak akses">
        <x-slot:actions>
            @can('create', App\Models\Role::class)
                <button type="button" wire:click="openCreate" class="app-btn app-btn-primary">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                    Tambah Role
                </button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card padding="p-0">
        <div class="border-b border-app-border p-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <label class="relative block w-full sm:max-w-xs">
                    <svg class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-app-muted" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.3-4.3M10 18a8 8 0 110-16 8 8 0 010 16z"/></svg>
                    <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari role..." class="app-input pl-9">
                </label>
                <select wire:model.live="perPage" class="app-select w-24">
                    @foreach ([10, 25, 50] as $size)<option value="{{ $size }}">{{ $size }}</option>@endforeach
                </select>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="app-table">
                <thead>
                    <tr>
                        <th>Role</th>
                        <th>Slug</th>
                        <th class="text-center">Permissions</th>
                        <th class="text-center">Users</th>
                        <th>Tipe</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($roles as $role)
                        <tr>
                            <td class="whitespace-nowrap font-medium">{{ $role->name }}</td>
                            <td class="whitespace-nowrap text-app-muted">{{ $role->slug }}</td>
                            <td class="whitespace-nowrap text-center text-app-muted">{{ $role->permissions_count }}</td>
                            <td class="whitespace-nowrap text-center text-app-muted">{{ $role->users_count }}</td>
                            <td class="whitespace-nowrap">
                                @if ($role->is_system)
                                    <span class="app-badge bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-900/40 dark:text-amber-300 dark:ring-amber-800">System</span>
                                @else
                                    <span class="app-badge bg-slate-100 text-slate-600 ring-slate-500/20 dark:bg-slate-800 dark:text-slate-400 dark:ring-slate-600">Custom</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap text-right">
                                <div class="flex items-center justify-end gap-1">
                                    @can('update', $role)
                                        <button type="button" wire:click="openEdit({{ $role->id }})" class="app-btn app-btn-ghost !p-1.5" title="Edit">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487z"/></svg>
                                        </button>
                                    @endcan
                                    @can('delete', $role)
                                        @if (! $role->is_system)
                                            <x-ui.confirm action="delete" :params="[$role->id]" title="Hapus Role" :message="'Hapus role ' . $role->name . '?'" confirm-label="Hapus" variant="danger" aria-label="Delete" class="app-btn app-btn-ghost !p-1.5 hover:!text-rose-600 dark:hover:!text-rose-400"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg></x-ui.confirm>
                                        @endif
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-ui.empty-state title="Tidak ada role" message="Belum ada role." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($roles->hasPages())<div class="border-t border-app-border px-4 py-3">{{ $roles->links() }}</div>@endif
    </x-ui.card>

    @if ($showModal)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/60 p-4">
            <div class="app-card max-h-[90vh] w-full max-w-2xl overflow-y-auto p-5">
                <h2 class="text-lg font-semibold text-app-text">{{ $editingId ? 'Edit Role' : 'Tambah Role' }}</h2>

                @if ($isAdmin)
                    <div class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:border-amber-700 dark:bg-amber-900/30 dark:text-amber-300">Role admin selalu memiliki semua permission — tidak dapat dihapus atau dikurangi.</div>
                @endif

                <form wire:submit="save" class="mt-4 space-y-4">
                    <div>
                        <label class="app-label mb-1">Nama Role<span class="text-rose-500">*</span></label>
                        <input type="text" wire:model="name" class="app-input">
                        @error('name') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <p class="text-sm font-medium text-app-text">Permissions</p>
                        <div class="mt-2 space-y-4">
                            @foreach ($permissionGroups as $group => $perms)
                                <div class="rounded-lg border border-app-border bg-app-surface-2 p-3">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-app-muted">{{ $group }}</p>
                                    <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
                                        @foreach ($perms as $perm)
                                            <label class="flex items-center gap-2 rounded-lg border border-app-border bg-app-surface px-3 py-2 text-sm @if($isAdmin) opacity-60 @endif">
                                                <input type="checkbox" value="{{ $perm->id }}" wire:model.live="selectedPermissions" @disabled($isAdmin) class="h-4 w-4 rounded border-app-border bg-app-surface text-primary-600 focus:ring-primary-500 disabled:opacity-50">
                                                <span class="text-app-text">{{ $perm->name }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        @error('selectedPermissions') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
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
