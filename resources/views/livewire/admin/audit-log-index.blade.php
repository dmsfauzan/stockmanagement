<div>
    <x-ui.page-header title="Audit Logs" subtitle="Jejak perubahan data (read-only)">
        <x-slot:actions>
            <button type="button" wire:click="resetFilters" class="app-btn app-btn-secondary">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M21.015 4.356v4.992m0 0h-4.992m4.992 0l-3.181-3.183a8.25 8.25 0 00-13.803 3.7"/></svg>
                Reset Filter
            </button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card padding="p-0">
        <div class="border-b border-app-border p-4">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-6">
                <div class="relative">
                    <svg class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-app-muted" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.3-4.3M10 18a8 8 0 110-16 8 8 0 010 16z"/></svg>
                    <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari aksi / modul..." class="app-input pl-9">
                </div>
                <select wire:model.live="userFilter" class="app-select">
                    <option value="">Semua User</option>
                    @foreach ($users as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach
                </select>
                <select wire:model.live="moduleFilter" class="app-select">
                    <option value="">Semua Modul</option>
                    @foreach ($modules as $module)<option value="{{ $module }}">{{ $module }}</option>@endforeach
                </select>
                <select wire:model.live="actionFilter" class="app-select">
                    <option value="">Semua Aksi</option>
                    @foreach ($actions as $action)<option value="{{ $action }}">{{ $action }}</option>@endforeach
                </select>
                <input type="date" wire:model.live="fromDate" class="app-input">
                <input type="date" wire:model.live="toDate" class="app-input">
            </div>
            <div class="mt-3 flex items-center justify-end">
                <select wire:model.live="perPage" class="app-select w-24">
                    @foreach ([15, 25, 50, 100] as $size)<option value="{{ $size }}">{{ $size }}</option>@endforeach
                </select>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="app-table">
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>User</th>
                        <th>Aksi</th>
                        <th>Modul</th>
                        <th>Entitas</th>
                        <th>IP</th>
                        <th class="text-right">Detail</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td class="whitespace-nowrap text-app-muted">{{ to_display_tz($log->created_at)?->format('d M Y H:i:s') ?? '-' }}</td>
                            <td class="whitespace-nowrap text-app-text">{{ $log->user?->name ?? 'System' }}</td>
                            <td class="whitespace-nowrap"><span class="app-badge bg-slate-100 text-slate-600 ring-slate-500/20 dark:bg-slate-800 dark:text-slate-400 dark:ring-slate-600">{{ $log->action }}</span></td>
                            <td class="whitespace-nowrap text-app-muted">{{ $log->module }}</td>
                            <td class="whitespace-nowrap text-app-muted">{{ $log->auditable_type ? class_basename($log->auditable_type).'#'.$log->auditable_id : '-' }}</td>
                            <td class="whitespace-nowrap text-app-muted">{{ $log->ip_address ?? '-' }}</td>
                            <td class="whitespace-nowrap text-right">
                                <button type="button" wire:click="openDetail({{ $log->id }})" class="app-btn app-btn-secondary px-2.5 py-1 text-xs">Lihat</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-ui.empty-state title="Tidak ada audit log" message="Belum ada aktivitas yang tercatat." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($logs->hasPages())<div class="border-t border-app-border px-4 py-3">{{ $logs->links() }}</div>@endif
    </x-ui.card>

    @if ($showDetail && $selected)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/60 p-4">
            <div class="app-card max-h-[90vh] w-full max-w-3xl overflow-y-auto p-5">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-app-text">Audit Log #{{ $selected->id }}</h2>
                    <button type="button" wire:click="closeDetail" class="app-btn app-btn-ghost !p-1.5 text-lg leading-none">&times;</button>
                </div>

                <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                    <div><dt class="font-medium text-app-muted">User</dt><dd class="text-app-text">{{ $selected->user?->name ?? 'System' }}</dd></div>
                    <div><dt class="font-medium text-app-muted">Waktu</dt><dd class="text-app-text">{{ to_display_tz($selected->created_at)?->format('d M Y H:i:s') }}</dd></div>
                    <div><dt class="font-medium text-app-muted">Aksi</dt><dd class="text-app-text">{{ $selected->action }}</dd></div>
                    <div><dt class="font-medium text-app-muted">Modul</dt><dd class="text-app-text">{{ $selected->module }}</dd></div>
                    <div><dt class="font-medium text-app-muted">Entitas</dt><dd class="text-app-text">{{ $selected->auditable_type ? class_basename($selected->auditable_type).'#'.$selected->auditable_id : '-' }}</dd></div>
                    <div><dt class="font-medium text-app-muted">IP</dt><dd class="text-app-text">{{ $selected->ip_address ?? '-' }}</dd></div>
                    <div class="col-span-2"><dt class="font-medium text-app-muted">User Agent</dt><dd class="break-all text-xs text-app-muted">{{ $selected->user_agent ?? '-' }}</dd></div>
                </dl>

                <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="rounded-lg border border-app-border p-3">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-app-muted">Old Values</h3>
                        <pre class="mt-2 max-h-72 overflow-auto rounded-lg bg-app-surface-2 p-3 font-mono text-xs text-app-text">{{ json_encode($selected->old_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?? 'null' }}</pre>
                    </div>
                    <div class="rounded-lg border border-app-border p-3">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-app-muted">New Values</h3>
                        <pre class="mt-2 max-h-72 overflow-auto rounded-lg bg-app-surface-2 p-3 font-mono text-xs text-app-text">{{ json_encode($selected->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?? 'null' }}</pre>
                    </div>
                </div>

                @php
                    $old = $selected->old_values;
                    $new = $selected->new_values;
                    $diff = [];
                    if (is_array($old) && is_array($new)) {
                        foreach (array_unique(array_merge(array_keys($old), array_keys($new))) as $k) {
                            $ov = json_encode($old[$k] ?? null);
                            $nv = json_encode($new[$k] ?? null);
                            if ($ov !== $nv) {
                                $diff[$k] = ['old' => $old[$k] ?? null, 'new' => $new[$k] ?? null];
                            }
                        }
                    }
                @endphp
                @if ($diff !== [])
                    <div class="mt-4">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-app-muted">Perubahan (diff)</h3>
                        <div class="mt-2 overflow-x-auto rounded-lg border border-app-border">
                            <table class="app-table text-xs">
                                <thead>
                                    <tr><th>Field</th><th>Sebelum</th><th>Sesudah</th></tr>
                                </thead>
                                <tbody>
                                    @foreach ($diff as $field => $v)
                                        <tr>
                                            <td class="whitespace-nowrap font-medium">{{ $field }}</td>
                                            <td class="text-app-muted">{{ json_encode($v['old'], JSON_UNESCAPED_UNICODE) }}</td>
                                            <td class="text-app-muted">{{ json_encode($v['new'], JSON_UNESCAPED_UNICODE) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                <div class="mt-6 flex justify-end">
                    <button type="button" wire:click="closeDetail" class="app-btn app-btn-secondary">Tutup</button>
                </div>
            </div>
        </div>
    @endif
</div>
