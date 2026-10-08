<div>
    <x-ui.page-header title="Supplier" subtitle="Kelola data supplier">
        <x-slot:actions>
            <button type="button" wire:click="export" class="app-btn app-btn-secondary gap-2">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                Export
            </button>
            <button type="button" wire:click="openImportModal" class="app-btn app-btn-secondary gap-2">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
                {{ __('Import') }}
            </button>
            <button type="button" wire:click="openCreate" class="app-btn app-btn-primary gap-2">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                Tambah Supplier
            </button>
        </x-slot:actions>
    </x-ui.page-header>

    @include('livewire.partials.master-import-modal')

    <x-ui.card padding="p-0">
        <div class="border-b border-app-border p-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex flex-1 flex-col gap-3 sm:flex-row sm:flex-wrap">
                    <label class="relative block w-full sm:max-w-xs">
                        <svg class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-app-muted" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.3-4.3M10 18a8 8 0 110-16 8 8 0 010 16z"/></svg>
                        <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari kode / nama / kontak..." class="app-input pl-9">
                    </label>
                    <select wire:model.live="statusFilter" class="app-select w-full sm:w-auto">
                        <option value="">{{ __('Semua Status') }}</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                    <select wire:model.live="trashedFilter" class="app-select w-full sm:w-auto">
                        <option value="">Aktif</option>
                        <option value="trashed">{{ __('Terhapus') }}</option>
                        <option value="all">{{ __('Semua') }}</option>
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
                <x-ui.confirm action="bulkActivate" title="Aktifkan massal" :message="'Aktifkan '.count($selectedIds).' supplier terpilih?'" confirm-label="Aktifkan" class="app-btn app-btn-secondary app-btn-sm">{{ __('Aktifkan') }}</x-ui.confirm>
                <x-ui.confirm action="bulkDeactivate" title="Nonaktifkan massal" :message="'Nonaktifkan '.count($selectedIds).' supplier terpilih?'" confirm-label="Nonaktifkan" class="app-btn app-btn-secondary app-btn-sm">{{ __('Nonaktifkan') }}</x-ui.confirm>
                <x-ui.confirm action="bulkDelete" title="Hapus massal" :message="'Hapus '.count($selectedIds).' supplier terpilih?'" confirm-label="Hapus" variant="danger" class="app-btn app-btn-danger app-btn-sm">Hapus</x-ui.confirm>
                <x-ui.confirm action="bulkRestore" title="Pulihkan massal" :message="'Pulihkan '.count($selectedIds).' supplier terpilih?'" confirm-label="Pulihkan" class="app-btn app-btn-secondary app-btn-sm">{{ __('Pulihkan') }}</x-ui.confirm>
            </div>
        @endif

        <div class="overflow-x-auto">
            <table class="app-table">
                <thead>
                    <tr>
                        <th class="w-8"><input type="checkbox" wire:model.live="selectAll" class="rounded border-slate-300 text-primary-600 focus:ring-primary-500"></th>
                        <th>{{ __('Kode') }}</th>
                        <th>{{ __('Nama') }}</th>
                        <th>Lead Time</th>
                        <th>Payment Terms</th>
                        <th>Region</th>
                        <th class="text-right">Primary Items</th>
                        <th>Status</th>
                        <th class="text-right">{{ __('Aksi') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($suppliers as $supplier)
                        <tr @class(['cursor-pointer hover:bg-app-surface-2/60' => ! $supplier->trashed()]) @if (! $supplier->trashed()) onclick="window.location='{{ route('suppliers.show', $supplier) }}'" @endif>
                            <td wire:click.stop><input type="checkbox" value="{{ $supplier->id }}" wire:model.live="selectedIds" class="rounded border-slate-300 text-primary-600 focus:ring-primary-500"></td>
                            <td class="whitespace-nowrap font-medium">@if ($supplier->trashed()){{ $supplier->code }}@else<a href="{{ route('suppliers.show', $supplier) }}" class="text-primary-600 hover:underline dark:text-primary-400" wire:click.stop>{{ $supplier->code }}</a>@endif</td>
                            <td>@if ($supplier->trashed()){{ $supplier->name }}@else<a href="{{ route('suppliers.show', $supplier) }}" class="hover:underline" wire:click.stop>{{ $supplier->name }}</a>@endif<div class="text-xs text-app-muted">{{ $supplier->contact_person ?? '-' }} {{ $supplier->phone ? '· '.$supplier->phone : '' }}</div></td>
                            <td class="whitespace-nowrap text-app-muted">{{ (int) ($supplier->lead_time_days ?? 7) }}d</td>
                            <td class="whitespace-nowrap text-app-muted">{{ $supplier->payment_terms ?? 'NET 30' }}</td>
                            <td class="whitespace-nowrap">
                                @if($supplier->region)
                                    <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-700 ring-1 ring-inset ring-slate-500/10 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-600">{{ $supplier->region }}</span>
                                @else
                                    <span class="text-app-muted">-</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap text-right font-medium text-app-text">{{ $supplier->primary_items_count ?? 0 }}</td>
                            <td class="whitespace-nowrap"><x-ui.status-badge :status="$supplier->status" /></td>
                            <td class="whitespace-nowrap text-right">
                                <div class="flex items-center justify-end gap-1" wire:click.stop>
                                    @if ($supplier->trashed())
                                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500 ring-1 ring-inset ring-slate-500/10 dark:bg-slate-800 dark:text-slate-400 dark:ring-slate-600">{{ __('Terhapus') }}</span>
                                        <x-ui.confirm action="restore" :params="[$supplier->id]" title="Pulihkan Data" message="Pulihkan data ini?" confirm-label="Pulihkan" class="app-btn app-btn-ghost app-btn-sm">{{ __('Pulihkan') }}</x-ui.confirm>
                                    @else
                                        <a href="{{ route('suppliers.show', $supplier) }}" class="app-btn app-btn-ghost !p-1.5" title="View">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                        </a>
                                        <button type="button" wire:click="openEdit({{ $supplier->id }})" class="app-btn app-btn-ghost !p-1.5" title="Edit">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487z"/></svg>
                                        </button>
                                        <x-ui.confirm action="delete" :params="[$supplier->id]" title="Hapus Supplier" :message="'Hapus ' . $supplier->name . '?'" confirm-label="Hapus" variant="danger" aria-label="Delete" class="app-btn app-btn-ghost !p-1.5 hover:!text-rose-600 dark:hover:!text-rose-400"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg></x-ui.confirm>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9"><x-ui.empty-state title="Tidak ada supplier" message="Belum ada supplier yang cocok dengan pencarian." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($suppliers->hasPages())
            <div class="app-card-body border-t border-app-border py-3">{{ $suppliers->links() }}</div>
        @endif
    </x-ui.card>

    @if ($showModal)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/60 p-4">
            <div class="app-card w-full max-w-lg max-h-[90vh] overflow-y-auto p-5">
                <h2 class="text-lg font-semibold text-app-text">{{ $editingId ? 'Edit Supplier' : 'Tambah Supplier' }}</h2>
                <form wire:submit="save" class="mt-4 space-y-4">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="app-label mb-1">{{ __('Kode') }}<span class="text-rose-500">*</span></label>
                            <input type="text" wire:model="code" class="app-input">
                            @error('code') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="app-label mb-1">{{ __('Nama') }}<span class="text-rose-500">*</span></label>
                            <input type="text" wire:model="name" class="app-input">
                            @error('name') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="app-label mb-1">Contact Person</label>
                            <input type="text" wire:model="contact_person" class="app-input">
                            @error('contact_person') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="app-label mb-1">{{ __('Telepon') }}</label>
                            <input type="text" wire:model="phone" class="app-input">
                            @error('phone') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="app-label mb-1">Lead Time (days)<span class="text-rose-500">*</span></label>
                            <input type="number" min="1" max="365" wire:model="lead_time_days" class="app-input">
                            @error('lead_time_days') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="app-label mb-1">Payment Terms<span class="text-rose-500">*</span></label>
                            <input type="text" wire:model="payment_terms" placeholder="NET 30" class="app-input">
                            @error('payment_terms') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div>
                        <label class="app-label mb-1">Email</label>
                        <input type="email" wire:model="email" class="app-input">
                        @error('email') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="app-label mb-1">Region</label>
                        <input type="text" wire:model="region" placeholder="Jakarta" class="app-input">
                        @error('region') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="app-label mb-1">{{ __('Alamat') }}</label>
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
                        <button type="button" wire:click="closeModal" class="app-btn app-btn-secondary">{{ __('Batal') }}</button>
                        <button type="submit" class="app-btn app-btn-primary">{{ __('Simpan') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
