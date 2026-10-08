<?php

namespace App\Livewire\Admin;

use App\Models\BannedIp;
use App\Models\SecurityEvent;
use App\Models\Setting;
use App\Services\Security\SecurityMonitor;
use App\Services\Support\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts.app')]
#[Title('Security')]
class SecurityIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $typeFilter = '';

    public string $severityFilter = '';

    public string $ipFilter = '';

    public string $fromDate = '';

    public string $toDate = '';

    public int $perPage = 15;

    public ?int $selectedId = null;

    public bool $showDetail = false;

    public string $newBanIp = '';

    public string $newBanReason = '';

    public int $newBanDuration = 1440;

    public function mount(): void
    {
        abort_unless(auth()->user()->hasPermission('security.manage'), 403);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSeverityFilter(): void
    {
        $this->resetPage();
    }

    public function updatedIpFilter(): void
    {
        $this->resetPage();
    }

    public function updatedFromDate(): void
    {
        $this->resetPage();
    }

    public function updatedToDate(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function filterByIp(string $ip): void
    {
        $this->ipFilter = $ip;
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'typeFilter', 'severityFilter', 'ipFilter', 'fromDate', 'toDate']);
        $this->resetPage();
    }

    public function openDetail(int $id): void
    {
        $this->selectedId = $id;
        $this->showDetail = true;
    }

    public function closeDetail(): void
    {
        $this->showDetail = false;
        $this->selectedId = null;
    }

    public function quickBan(string $ip): void
    {
        abort_unless(auth()->user()->hasPermission('security.manage'), 403);

        SecurityMonitor::ban($ip, 'Manual ban dari daftar offender', 'manual', 1440, (int) auth()->id());
        AuditLogger::log('BAN', 'security', null, null, ['ip' => $ip, 'minutes' => 1440]);

        $this->dispatch('toast', type: 'success', message: "IP {$ip} diblokir 24 jam.");
    }

    public function manualBan(): void
    {
        abort_unless(auth()->user()->hasPermission('security.manage'), 403);

        $data = $this->validate([
            'newBanIp' => ['required', 'ip'],
            'newBanReason' => ['nullable', 'string', 'max:255'],
            'newBanDuration' => ['required', 'integer', 'min:0', 'max:525600'],
        ]);

        $duration = (int) $data['newBanDuration'];

        SecurityMonitor::ban(
            $data['newBanIp'],
            $data['newBanReason'] !== '' ? $data['newBanReason'] : 'Manual ban',
            'manual',
            $duration === 0 ? null : $duration,
            (int) auth()->id(),
        );

        AuditLogger::log('BAN', 'security', null, null, ['ip' => $data['newBanIp'], 'minutes' => $duration]);

        $this->reset('newBanIp', 'newBanReason');
        $this->dispatch('toast', type: 'success', message: __('IP diblokir.'));
    }

    public function unban(string $ip): void
    {
        abort_unless(auth()->user()->hasPermission('security.manage'), 403);

        SecurityMonitor::unban($ip);
        AuditLogger::log('UNBAN', 'security', null, null, ['ip' => $ip]);

        $this->dispatch('toast', type: 'success', message: "IP {$ip} dibuka.");
    }

    public function purge(): void
    {
        abort_unless(auth()->user()->hasPermission('security.manage'), 403);

        $days = max(1, (int) (Setting::get('security.retention_days', config('security.retention_days', 90)) ?? 90));

        $removed = SecurityEvent::where('created_at', '<', now()->subDays($days))->delete();
        BannedIp::whereNotNull('expires_at')->where('expires_at', '<', now())->delete();

        AuditLogger::log('PURGE', 'security', null, null, ['events' => $removed, 'days' => $days]);

        $this->dispatch('toast', type: 'success', message: "Bersihkan {$removed} event lama.");
    }

    protected function eventsQuery(): Builder
    {
        return SecurityEvent::query()
            ->with('user')
            ->when($this->search !== '', function (Builder $query): void {
                $term = '%'.$this->search.'%';
                $query->where(function (Builder $inner) use ($term): void {
                    $inner->where('event_type', 'like', $term)
                        ->orWhere('ip_address', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('path', 'like', $term)
                        ->orWhere('user_agent', 'like', $term);
                });
            })
            ->when($this->typeFilter !== '', fn (Builder $query) => $query->where('event_type', $this->typeFilter))
            ->when($this->severityFilter !== '', fn (Builder $query) => $query->where('severity', $this->severityFilter))
            ->when($this->ipFilter !== '', fn (Builder $query) => $query->where('ip_address', $this->ipFilter))
            ->when($this->fromDate !== '', fn (Builder $query) => $query->whereDate('created_at', '>=', $this->fromDate))
            ->when($this->toDate !== '', fn (Builder $query) => $query->whereDate('created_at', '<=', $this->toDate))
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    public function export(): StreamedResponse
    {
        abort_unless(auth()->user()->hasPermission('security.manage'), 403);

        $rows = $this->eventsQuery()->limit(5000)->get();

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Time', 'Type', 'Severity', 'IP', 'Country', 'User', 'Email', 'Method', 'Path', 'User Agent']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    optional($row->created_at)->format('Y-m-d H:i:s'),
                    $row->event_type,
                    $row->severity,
                    $row->ip_address,
                    $row->country_name,
                    $row->user?->name,
                    $row->email,
                    $row->method,
                    $row->path,
                    $row->user_agent,
                ]);
            }

            fclose($handle);
        }, 'security-events-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function render()
    {
        $since = now()->subDay();

        $byType = SecurityEvent::query()
            ->where('created_at', '>=', $since)
            ->selectRaw('event_type, COUNT(*) as total')
            ->groupBy('event_type')
            ->pluck('total', 'event_type');

        $stats = [
            'login_failed' => (int) ($byType['login_failed'] ?? 0),
            'login_lockout' => (int) ($byType['login_lockout'] ?? 0),
            'unauthorized' => (int) ($byType['unauthorized'] ?? 0),
            'rate_limited' => (int) ($byType['rate_limited'] ?? 0),
            'suspicious_path' => (int) ($byType['suspicious_path'] ?? 0),
            'banned' => BannedIp::active()->count(),
        ];

        $offenders = SecurityEvent::query()
            ->where('created_at', '>=', now()->subDays(7))
            ->selectRaw('ip_address, COUNT(*) as hits, MAX(created_at) as last_seen')
            ->groupBy('ip_address')
            ->orderByDesc('hits')
            ->limit(10)
            ->get()
            ->map(function ($row) {
                $row->country = SecurityEvent::query()
                    ->where('ip_address', $row->ip_address)
                    ->whereNotNull('country_name')
                    ->value('country_name');
                $row->banned = SecurityMonitor::isBanned((string) $row->ip_address) !== null;

                return $row;
            });

        $chart = $this->buildChart();

        return view('livewire.admin.security-index', [
            'stats' => $stats,
            'offenders' => $offenders,
            'chart' => $chart,
            'events' => $this->eventsQuery()->paginate($this->perPage),
            'bannedIps' => BannedIp::query()->orderByDesc('created_at')->limit(50)->get(),
            'types' => SecurityEvent::query()->distinct()->orderBy('event_type')->pluck('event_type'),
            'severities' => SecurityEvent::query()->distinct()->orderBy('severity')->pluck('severity'),
            'selected' => $this->selectedId !== null ? SecurityEvent::with('user')->find($this->selectedId) : null,
        ]);
    }

    /** @return array{labels: array<int, string>, values: array<int, int>} */
    protected function buildChart(): array
    {
        $days = 13;
        $from = now()->subDays($days)->startOfDay();

        $rows = SecurityEvent::query()
            ->where('created_at', '>=', $from)
            ->get(['created_at']);

        $buckets = [];
        for ($i = $days; $i >= 0; $i--) {
            $buckets[now()->subDays($i)->format('Y-m-d')] = 0;
        }

        foreach ($rows as $row) {
            $key = Carbon::parse($row->created_at)->format('Y-m-d');

            if (array_key_exists($key, $buckets)) {
                $buckets[$key]++;
            }
        }

        return [
            'labels' => array_map(fn (string $d) => Carbon::parse($d)->format('d M'), array_keys($buckets)),
            'values' => array_values($buckets),
        ];
    }
}
