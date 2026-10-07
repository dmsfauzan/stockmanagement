@props([
    'auditableType' => null,
    'auditableId' => null,
])

@php
    $logs = ($auditableType && $auditableId)
        ? \App\Models\AuditLog::with('user')
            ->where('auditable_type', $auditableType)
            ->where('auditable_id', $auditableId)
            ->latest('created_at')
            ->limit(50)
            ->get()
        : collect();
@endphp

<div {{ $attributes }}>
    @if ($logs->isEmpty())
        <x-ui.empty-state title="Belum ada riwayat" message="Belum ada aktivitas audit untuk data ini." />
    @else
        <ul class="space-y-3">
            @foreach ($logs as $log)
                @php
                    $old = is_array($log->old_values) ? $log->old_values : [];
                    $new = is_array($log->new_values) ? $log->new_values : [];
                    $diff = [];
                    if ($log->action === 'update' && ($old || $new)) {
                        foreach (array_unique(array_merge(array_keys($old), array_keys($new))) as $key) {
                            if (json_encode($old[$key] ?? null) !== json_encode($new[$key] ?? null)) {
                                $diff[$key] = ['old' => $old[$key] ?? null, 'new' => $new[$key] ?? null];
                            }
                        }
                    }
                    $variant = match (strtolower((string) $log->action)) {
                        'create', 'import' => 'bg-emerald-100 text-emerald-700 ring-emerald-500/20 dark:bg-emerald-900/30 dark:text-emerald-300 dark:ring-emerald-700/40',
                        'delete' => 'bg-rose-100 text-rose-700 ring-rose-500/20 dark:bg-rose-900/30 dark:text-rose-300 dark:ring-rose-700/40',
                        default => 'bg-slate-100 text-slate-600 ring-slate-500/20 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-600',
                    };
                @endphp
                <li class="rounded-lg border border-app-border p-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <span class="app-badge {{ $variant }}">{{ strtoupper((string) $log->action) }}</span>
                            <span class="app-badge bg-app-surface-2 text-app-muted ring-app-border">{{ $log->module }}</span>
                        </div>
                        <span class="text-xs text-app-muted">{{ $log->created_at?->format('d M Y H:i:s') ?? '-' }}</span>
                    </div>

                    <p class="mt-2 text-xs text-app-muted">oleh <span class="font-medium text-app-text">{{ $log->user?->name ?? 'System' }}</span>@if($log->ip_address) · {{ $log->ip_address }}@endif</p>

                    @if ($diff !== [])
                        <div class="mt-2 overflow-x-auto rounded-lg border border-app-border">
                            <table class="app-table text-xs">
                                <thead>
                                    <tr><th>Field</th><th>Sebelum</th><th>Sesudah</th></tr>
                                </thead>
                                <tbody>
                                    @foreach ($diff as $field => $value)
                                        <tr>
                                            <td class="whitespace-nowrap font-medium">{{ $field }}</td>
                                            <td class="text-app-muted">{{ json_encode($value['old'], JSON_UNESCAPED_UNICODE) }}</td>
                                            <td class="text-app-muted">{{ json_encode($value['new'], JSON_UNESCAPED_UNICODE) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @elseif ($log->action === 'create' && $new !== [])
                        <pre class="mt-2 max-h-48 overflow-auto rounded-lg bg-app-surface-2 p-3 font-mono text-xs text-app-text">{{ json_encode($new, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                    @elseif ($log->action === 'delete' && $old !== [])
                        <pre class="mt-2 max-h-48 overflow-auto rounded-lg bg-app-surface-2 p-3 font-mono text-xs text-app-text">{{ json_encode($old, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</div>
