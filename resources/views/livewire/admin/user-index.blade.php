<div>
    <x-ui.page-header title="Users" subtitle="Kelola akun pengguna dan akses">
        <x-slot:actions>
            @can('create', App\Models\User::class)
                <button type="button" wire:click="openCreate" class="app-btn app-btn-primary">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                    Tambah User
                </button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card padding="p-0">
        <div class="border-b border-app-border p-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex flex-1 flex-col gap-3 sm:flex-row">
                    <label class="relative block w-full sm:max-w-xs">
                        <svg class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-app-muted" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.3-4.3M10 18a8 8 0 110-16 8 8 0 010 16z"/></svg>
                        <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari nama / email..." class="app-input pl-9">
                    </label>
                    <select wire:model.live="statusFilter" class="app-select">
                        <option value="">{{ __('Semua Status') }}</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                    <select wire:model.live="roleFilter" class="app-select">
                        <option value="">Semua Role</option>
                        @foreach ($roles as $role)
                            <option value="{{ $role->id }}">{{ $role->name }}</option>
                        @endforeach
                    </select>
                </div>
                <select wire:model.live="perPage" class="app-select w-24">
                    @foreach ([10, 25, 50] as $size)<option value="{{ $size }}">{{ $size }}</option>@endforeach
                </select>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="app-table">
                <thead>
                    <tr>
                        <th class="w-12"></th>
                        <th>Pengguna</th>
                        <th>Roles</th>
                        <th>Status</th>
                        <th>Last Login</th>
                        <th class="text-right">{{ __('Aksi') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr>
                            <td>
                                @if($user->avatarUrl())
                                    <img src="{{ $user->avatarUrl() }}" alt="{{ $user->name }}" class="h-8 w-8 rounded-full object-cover" loading="lazy">
                                @else
                                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-app-surface-2 text-xs font-semibold text-app-muted">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                                @endif
                            </td>
                            <td>
                                <div class="font-medium text-app-text">{{ $user->name }}</div>
                                <div class="text-xs text-app-muted">{{ $user->email }}</div>
                            </td>
                            <td>
                                <div class="flex flex-wrap gap-1">
                                    @forelse ($user->roles as $role)
                                        <span class="inline-flex items-center rounded-full bg-app-surface-2 px-2 py-0.5 text-xs font-medium text-app-muted ring-1 ring-inset ring-app-border">{{ $role->name }}</span>
                                    @empty
                                        <span class="text-xs text-app-muted">-</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="whitespace-nowrap"><x-ui.status-badge :status="$user->status" /></td>
                            <td class="whitespace-nowrap text-app-muted">{{ to_display_tz($user->last_login_at)?->format('d M Y H:i') ?? '-' }}</td>
                            <td class="whitespace-nowrap text-right">
                                <div class="flex items-center justify-end gap-1">
                                    @can('update', $user)
                                        <button type="button" wire:click="openEdit({{ $user->id }})" class="app-btn app-btn-ghost !p-1.5" title="Edit">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487z"/></svg>
                                        </button>
                                        <x-ui.confirm action="toggleStatus" :params="[$user->id]" title="Ubah Status" :message="'Ubah status ' . $user->name . '?'" confirm-label="Ubah" variant="primary" aria-label="Toggle status" class="app-btn app-btn-ghost !p-1.5 hover:!text-amber-600 dark:hover:!text-amber-400"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9V5.25A2.25 2.25 0 0110.5 3h6a2.25 2.25 0 012.25 2.25v13.5A2.25 2.25 0 0116.5 21h-6a2.25 2.25 0 01-2.25-2.25V15"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 9l3 3m0 0l-3 3m3-3H2.25"/></svg></x-ui.confirm>
                                    @endcan
                                    @can('delete', $user)
                                        <x-ui.confirm action="delete" :params="[$user->id]" title="Hapus User" :message="'Hapus ' . $user->name . '?'" confirm-label="Hapus" variant="danger" aria-label="Delete" class="app-btn app-btn-ghost !p-1.5 hover:!text-rose-600 dark:hover:!text-rose-400"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg></x-ui.confirm>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-ui.empty-state title="Tidak ada user" message="Belum ada user yang cocok dengan pencarian." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($users->hasPages())<div class="border-t border-app-border px-4 py-3">{{ $users->links() }}</div>@endif
    </x-ui.card>

    @if ($showModal)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/60 p-4">
            <div class="app-card max-h-[90vh] w-full max-w-lg overflow-y-auto p-5">
                <h2 class="text-lg font-semibold text-app-text">{{ $editingId ? 'Edit User' : 'Tambah User' }}</h2>
                <form wire:submit="save" class="mt-4 space-y-4">
                    <div>
                        <label class="app-label mb-1">{{ __('Nama') }}<span class="text-rose-500">*</span></label>
                        <input type="text" wire:model="name" class="app-input">
                        @error('name') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="app-label mb-1">Email<span class="text-rose-500">*</span></label>
                        <input type="email" wire:model="email" class="app-input">
                        @error('email') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="app-label mb-1">Password {{ $editingId ? '(kosongkan jika tidak diubah)' : '*' }}</label>
                        <input type="password" wire:model="password" class="app-input" placeholder="Min 8 karakter">
                        @error('password') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="app-label mb-1">Status<span class="text-rose-500">*</span></label>
                        <select wire:model="status" class="app-select">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                        @error('status') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="app-label mb-1">Roles</label>
                        <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
                            @foreach ($roles as $role)
                                <label class="flex items-center gap-2 rounded-lg border border-app-border bg-app-surface px-3 py-2 text-sm hover:bg-app-surface-2">
                                    <input type="checkbox" value="{{ $role->id }}" wire:model.live="selectedRoles" class="h-4 w-4 rounded border-app-border bg-app-surface text-primary-600 focus:ring-primary-500">
                                    <span class="text-app-text">{{ $role->name }}</span>
                                </label>
                            @endforeach
                        </div>
                        @error('selectedRoles') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="closeModal" class="app-btn app-btn-secondary">{{ __('Batal') }}</button>
                        <button type="submit" class="app-btn app-btn-primary">{{ __('Simpan') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
