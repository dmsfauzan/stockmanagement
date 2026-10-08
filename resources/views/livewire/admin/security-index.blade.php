<div>
    <x-ui.page-header title="Keamanan — Threat Monitor" subtitle="Pantau percobaan serangan (login gagal, 403/429, scanner) & kelola blokir IP">
        <x-slot:actions>
            <button type="button" wire:click="export" class="app-btn app-btn-secondary gap-2">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                Export CSV
            </button>
            <x-ui.confirm action="purge" title="Bersihkan event lama" message="Hapus seluruh event & ban-kedaluwarsa sesuai retensi?" confirm-label="Bersihkan" variant="danger" class="app-btn app-btn-secondary app-btn-sm">Purge</x-ui.confirm>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        @php $statItems = [['Login gagal', $stats['login_failed'], 'rose'], ['Lockout', $stats['login_lockout'], 'orange'], ['403', $stats['unauthorized'], 'amber'], ['429', $stats['rate_limited'], 'red'], ['Scanner', $stats['suspicious_path'], 'violet'], ['IP diblokir', $stats['banned'], 'slate']]; @endphp
        @foreach ($statItems as [$label, $value, $tone])
            <div class="rounded-xl border border-app-border bg-app-surface px-4 py-3">
                <p class="text-xs font-medium uppercase tracking-wide text-app-muted">{{ $label }}</p>
                <p class="mt-1 text-2xl font-semibold text-app-text">{{ $value }}</p>
                <p class="text-xs text-app-muted">24 jam terakhir</p>
            </div>
        @endforeach
    </div>

    <div class="mb-6 grid grid-cols-1 gap-4 lg:grid-cols-3">
        <x-ui.card class="lg:col-span-2">
            <h2 class="app-card-title mb-3">Ancaman per hari (14 hari)</h2>
            <div x-data="{
                renderChart() {
                    if (typeof ApexCharts === 'undefined') return;
                    const isDark = document.documentElement.classList.contains('dark');
                    const fg = isDark ? '#94a3b8' : '#64748b';
                    const grid = isDark ? '#334155' : '#e2e8f0';
                    const el = this.$refs.canvas;
                    while (el.firstChild) el.removeChild(el.firstChild);
                    const chart = new ApexCharts(el, {
                        chart: { type: 'area', height: 200, toolbar: { show: false }, foreColor: fg },
                        series: [{ name: 'Event', data: @js($chart['values']) }],
                        xaxis: { categories: @js($chart['labels']), labels: { style: { colors: fg } } },
                        yaxis: { labels: { style: { colors: fg } } },
                        stroke: { curve: 'smooth', width: 2, colors: ['#ef4444'] },
                        fill: { type: 'gradient', gradient: { opacityFrom: 0.35, opacityTo: 0.02 } },
                        dataLabels: { enabled: false },
                        grid: { borderColor: grid },
                        colors: ['#ef4444']
                    });
                    chart.render();
                }
            }" x-init="renderChart()" x-ref="canvas"></div>
        </x-ui.card>

        <x-ui.card>
            <h2 class="app-card-title mb-3">Pengguna/ IP teratas (7 hari)</h2>
            @if ($offenders->isEmpty())
                <x-ui.empty-state title="Belum ada offender" message="Tidak ada event mencurigakan minggu ini." />
            @else
                <div class="space-y-2">
                    @foreach ($offenders as $row)
                        <div class="flex items-center gap-2 rounded-lg border border-app-border bg-app-surface-2/40 px-3 py-2">
                            <div class="min-w-0 flex-1">
                                <p class="font-mono text-sm font-medium text-app-text truncate">{{ $row->ip_address }}</p>
                                <p class="text-xs text-app-muted truncate">{{ $row->country ?? '-' }} · {{ $row->hits }}× · {{ \Illuminate\Support\Carbon::parse($row->last_seen)->diffForHumans() }}</p>
                            </div>
                            @if ($row->banned)
                                <span class="rounded-full bg-rose-500 px-2 py-0.5 text-xs font-bold text-white">BANNED</span>
                                <button type="button" wire:click="unban('{{ $row->ip_address }}')" class="app-btn app-btn-ghost app-btn-sm">Unban</button>
                            @else
                                <button type="button" wire:click="quickBan('{{ $row->ip_address }}')" class="app-btn app-btn-ghost app-btn-sm">Ban 24j</button>
                            @endif
                            <button type="button" wire:click="filterByIp('{{ $row->ip_address }}')" class="app-btn app-btn-secondary app-btn-sm">Filter</button>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-ui.card>
    </div>

    <div class="mb-6">
        <x-ui.card>
            <h2 class="app-card-title mb-3">Tambah blokir manual</h2>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-4">
                <label class="sm:col-span-1"><span class="app-label">IP</span><input type="text" wire:model="newBanIp" placeholder="192.0.2.1" class="app-input"></label>
                <label class="sm:col-span-2"><span class="app-label">Alasan</span><input type="text" wire:model="newBanReason" placeholder="Manual ban" class="app-input"></label>
                <label class="sm:col-span-1"><span class="app-label">Durasi (menit, 0=permanen)</span><input type="number" wire:model="newBanDuration" class="app-input"></label>
            </div>
            <div class="mt-3">
                <button type="button" wire:click="manualBan" class="app-btn app-btn-primary">Blokir IP</button>
                @error('newBanIp') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                @error('newBanDuration') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
            </div>
        </x-ui.card>
    </div>

    @if ($bannedIps->isNotEmpty())
        <x-ui.card padding="p-0" class="mb-6">
            <div class="border-b border-app-border px-4 py-3">
                <h2 class="app-card-title">IP yang diblokir</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="app-table">
                    <thead><tr><th>IP</th><th>Negara</th><th>Alasan</th><th>Hits</th><th>Source</th><th>Kedaluwarsa</th><th class="text-right">Aksi</th></tr></thead>
                    <tbody>
                        @foreach ($bannedIps as $row)
                            <tr>
                                <td class="font-mono text-sm">{{ $row->ip_address }}</td>
                                <td class="text-app-muted">{{ $row->country_code ?? '-' }}</td>
                                <td class="text-app-muted">{{ $row->reason ?? '-' }}</td>
                                <td>{{ $row->hits }}</td>
                                <td><x-ui.status-badge :status="$row->source" /></td>
                                <td class="text-app-muted">@if($row->expires_at){{ \Illuminate\Support\Carbon::parse($row->expires_at)->diffForHumans() }}@else permanen @endif</td>
                                <td class="text-right"><x-ui.confirm action="unban" :params="[$row->ip_address]" title="Buka blokir" :message="'Buka blokir '.$row->ip_address.'?'" confirm-label="Buka" class="app-btn app-btn-ghost app-btn-sm">Unban</x-ui.confirm></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-ui.card>
    @endif

    <x-ui.card padding="p-0">
        <div class="border-b border-app-border p-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div class="flex flex-1 flex-wrap gap-2">
                    <label class="w-full sm:max-w-[220px]"><span class="app-label">Cari (tipe/IP/email/path)</span><input type="search" wire:model.live.debounce.300ms="search" class="app-input" placeholder="Failed / 192.0.2. / wp-admin ..."></label>
                    <label class="w-full sm:w-auto"><span class="app-label">Tipe</span><select wire:model.live="typeFilter" class="app-select"><option value="">Semua</option>@foreach ($types as $t)<option value="{{ $t }}">{{ $t }}</option>@endforeach</select></label>
                    <label class="w-full sm:w-auto"><span class="app-label">Severity</span><select wire:model.live="severityFilter" class="app-select"><option value="">Semua</option>@foreach ($severities as $s)<option value="{{ $s }}">{{ $s }}</option>@endforeach</select></label>
                    <label class="w-full sm:w-auto"><span class="app-label">IP</span><input type="text" wire:model.live="ipFilter" placeholder="192.0.2.x" class="app-input"></label>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" wire:click="resetFilters" class="app-btn app-btn-ghost app-btn-sm">Reset filter</button>
                    <select wire:model.live="perPage" class="app-select w-24">@foreach ([15,25,50,100] as $n)<option value="{{ $n }}">{{ $n }}</option>@endforeach</select>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="app-table">
                <thead><tr><th>Waktu</th><th>Tipe</th><th>IP</th><th>Negara</th><th>User / Email</th><th>Path</th><th class="text-right">Aksi</th></tr></thead>
                <tbody>
                    @forelse ($events as $row)
                        <tr>
                            <td class="whitespace-nowrap text-app-muted text-xs">{{ \Illuminate\Support\Carbon::parse($row->created_at)->format('d M Y H:i:s') }}</td>
                            <td class="whitespace-nowrap"><x-ui.status-badge :status="$row->severity" /></td>
                            <td class="font-mono text-xs">{{ $row->ip_address }}</td>
                            <td class="whitespace-nowrap text-app-muted text-xs">{{ $row->country_name ?? $row->country_code ?? '-' }}</td>
                            <td class="whitespace-nowrap text-app-muted text-xs">@if ($row->user){{ $row->user->name }}@elseif ($row->email){{ $row->email }}@else - @endif</td>
                            <td class="max-w-[360px] truncate text-xs"><span class="font-medium">{{ $row->event_type }}</span> <span class="text-app-muted">·</span> <span title="{{ $row->path }} {{ $row->user_agent }}">{{ $row->path ?? '-' }}</span></td>
                            <td class="text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <button type="button" wire:click="openDetail({{ $row->id }})" class="app-btn app-btn-ghost !p-1.5" title="Detail">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    </button>
                                    <button type="button" wire:click="filterByIp('{{ $row->ip_address }}')" class="app-btn app-btn-ghost !p-1.5" title="Filter IP ini">⚙</button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-ui.empty-state title="Belum ada event" message="Belum ada percobaan serangan yang terekam." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($events->hasPages())
            <div class="border-t border-app-border px-4 py-3">{{ $events->links() }}</div>
        @endif
    </x-ui.card>

    @if ($showDetail && $selected)
        <div class="fixed inset-0 z-[55] flex items-center justify-center p-4" role="dialog" aria-modal="true">
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" wire:click="closeDetail"></div>
            <div class="relative w-full max-w-xl rounded-xl border border-app-border bg-app-surface p-6 shadow-popover">
                <h3 class="text-base font-semibold text-app-text">Detail — {{ $selected->event_type }}</h3>
                <dl class="mt-4 grid grid-cols-3 gap-3 text-sm">
                    <dt class="text-app-muted col-span-1">Waktu</dt><dd class="col-span-2 font-mono text-xs">{{ $selected->created_at }}</dd>
                    <dt class="text-app-muted col-span-1">IP / Negara</dt><dd class="col-span-2 font-mono text-xs">{{ $selected->ip_address }} — {{ $selected->country_name ?? $selected->country_code ?? '-' }}</dd>
                    <dt class="text-app-muted col-span-1">User</dt><dd class="col-span-2 text-xs">{{ $selected->user?->name ?? $selected->email ?? '-' }}</dd>
                    <dt class="text-app-muted col-span-1">Method / Path</dt><dd class="col-span-2 text-xs">{{ $selected->method }} {{ $selected->path }}</dd>
                    <dt class="text-app-muted col-span-1">Referer</dt><dd class="col-span-2 truncate text-xs">{{ $selected->referer ?? '-' }}</dd>
                    <dt class="text-app-muted col-span-1">User Agent</dt><dd class="col-span-2 break-all text-xs">{{ $selected->user_agent ?? '-' }}</dd>
                    <dt class="text-app-muted col-span-1">Severity</dt><dd class="col-span-2 text-xs"><x-ui.status-badge :status="$selected->severity" /></dd>
                    <dt class="text-app-muted col-span-1">Meta</dt><dd class="col-span-2 font-mono text-xs break-all">{{ $selected->meta ? json_encode($selected->meta) : '-' }}</dd>
                </dl>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" wire:click="closeDetail" class="app-btn app-btn-secondary">Tutup</button>
                </div>
            </div>
        </div>
    @endif
</div>
