<div>
    <x-ui.page-header title="API Tokens" subtitle="Kelola token akses untuk integrasi">
        <x-slot:actions>
            <button type="button" wire:click="openCreate" class="app-btn app-btn-primary gap-2">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                Buat Token
            </button>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($plainToken)
        <x-ui.card class="mb-4 border-primary-200 bg-primary-50 dark:border-primary-900/40 dark:bg-primary-900/20">
            <p class="text-sm font-semibold text-app-text">Token untuk user #{{ $plainTokenUserId }}</p>
            <p class="mt-1 break-all font-mono text-xs text-app-text">{{ $plainToken }}</p>
            <p class="mt-1 text-xs text-app-muted">Simpan sekarang — plain token tidak bisa ditampilkan lagi.</p>
        </x-ui.card>
    @endif

    <x-ui.card padding="p-0">
        <div class="border-b border-app-border p-4">
            <label class="relative block w-full sm:max-w-xs">
                <svg class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-app-muted" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.3-4.3M10 18a8 8 0 110-16 8 8 0 010 16z"/></svg>
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari nama token..." class="app-input pl-9">
            </label>
        </div>

        <div class="overflow-x-auto">
            <table class="app-table">
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>User</th>
                        <th>Abilities</th>
                        <th>Dibuat</th>
                        <th>Terakhir dipakai</th>
                        <th>Kedaluwarsa</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tokens as $token)
                        <tr>
                            <td class="whitespace-nowrap font-medium">{{ $token->name }}</td>
                            <td class="whitespace-nowrap text-app-muted">{{ $token->tokenable?->name ?? '-' }}</td>
                            <td class="max-w-xs truncate text-app-muted">{{ $token->abilities === '*' ? '*' : implode(', ', (array) $token->abilities) }}</td>
                            <td class="whitespace-nowrap text-app-muted">{{ to_display_tz($token->created_at)?->format('d M Y H:i') }}</td>
                            <td class="whitespace-nowrap text-app-muted">{{ to_display_tz($token->last_used_at)?->format('d M Y H:i') ?? '-' }}</td>
                            <td class="whitespace-nowrap text-app-muted">{{ to_display_tz($token->expires_at)?->format('d M Y') ?? '—' }}</td>
                            <td class="whitespace-nowrap text-right">
                                <x-ui.confirm action="rotateToken" :params="[$token->id]" title="Rotate Token" :message="'Rotate token ' . $token->name . '? Token lama langsung tidak berlaku.'" confirm-label="Rotate" class="app-btn app-btn-ghost !p-1.5" aria-label="Rotate">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
                                </x-ui.confirm>
                                <x-ui.confirm action="deleteToken" :params="[$token->id]" title="Hapus Token" :message="'Hapus token ' . $token->name . '?'" confirm-label="Hapus" variant="danger" aria-label="Hapus" class="app-btn app-btn-ghost !p-1.5 hover:!text-rose-600 dark:hover:!text-rose-400">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
                                </x-ui.confirm>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-ui.empty-state title="Belum ada token" message="Buat token pertama untuk integrasi." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($tokens->hasPages())
            <div class="border-t border-app-border px-4 py-3">
                {{ $tokens->links() }}
            </div>
        @endif
    </x-ui.card>

    @if ($showCreateModal)
        <div class="fixed inset-0 z-[55] flex items-center justify-center p-4" role="dialog" aria-modal="true">
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" wire:click="closeModal"></div>
            <div class="relative w-full max-w-xl rounded-xl border border-app-border bg-app-surface p-6 shadow-popover">
                <h3 class="text-base font-semibold text-app-text">Buat Token</h3>
                <p class="mt-1 text-sm text-app-muted">Kosongkan abilities untuk memakai semua permission user.</p>

                <div class="mt-4 space-y-3">
                    <div>
                        <label class="app-label">User</label>
                        <select wire:model="selectedUserId" class="app-select">
                            <option value="0">— Pilih user —</option>
                            @foreach ($users as $user)
                                <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                            @endforeach
                        </select>
                        @error('selectedUserId') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="app-label">Nama Token</label>
                        <input type="text" wire:model="tokenName" class="app-input" placeholder="api-token">
                        @error('tokenName') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="app-label">Kedaluwarsa</label>
                        <input type="date" wire:model="expiresAt" class="app-input">
                        @error('expiresAt') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="app-label">Abilities (permission slugs)</label>
                        <div class="grid max-h-56 grid-cols-1 gap-1 overflow-y-auto rounded-lg border border-app-border p-2 sm:grid-cols-2">
                            @foreach ($permissions as $permission)
                                <label class="flex items-center gap-2 rounded px-2 py-1 text-xs hover:bg-app-surface-2">
                                    <input type="checkbox" wire:model="abilities" value="{{ $permission->slug }}" class="rounded">
                                    <span class="font-mono">{{ $permission->slug }}</span>
                                </label>
                            @endforeach
                        </div>
                        @error('abilities') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" wire:click="closeModal" class="app-btn app-btn-secondary">Batal</button>
                    <button type="button" wire:click="createToken" wire:loading.attr="disabled" class="app-btn app-btn-primary">Buat</button>
                </div>
            </div>
        </div>
    @endif
</div>
