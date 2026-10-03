<?php

namespace App\Livewire\Admin;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Audit Logs')]
class AuditLogIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $userFilter = '';

    public string $moduleFilter = '';

    public string $actionFilter = '';

    public string $fromDate = '';

    public string $toDate = '';

    public int $perPage = 15;

    public ?int $selectedId = null;

    public bool $showDetail = false;

    public function mount(): void
    {
        abort_unless(auth()->user()->hasPermission('audit_logs.view'), 403);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedUserFilter(): void
    {
        $this->resetPage();
    }

    public function updatedModuleFilter(): void
    {
        $this->resetPage();
    }

    public function updatedActionFilter(): void
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

    public function resetFilters(): void
    {
        $this->reset(['search', 'userFilter', 'moduleFilter', 'actionFilter', 'fromDate', 'toDate']);
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

    protected function baseQuery(): Builder
    {
        return AuditLog::query()
            ->with('user')
            ->when($this->search !== '', function (Builder $query): void {
                $term = '%'.$this->search.'%';
                $query->where(function (Builder $inner) use ($term): void {
                    $inner->where('action', 'like', $term)
                        ->orWhere('module', 'like', $term)
                        ->orWhere('auditable_type', 'like', $term);
                });
            })
            ->when($this->userFilter !== '', fn (Builder $query) => $query->where('user_id', $this->userFilter))
            ->when($this->moduleFilter !== '', fn (Builder $query) => $query->where('module', $this->moduleFilter))
            ->when($this->actionFilter !== '', fn (Builder $query) => $query->where('action', $this->actionFilter))
            ->when($this->fromDate !== '', fn (Builder $query) => $query->whereDate('created_at', '>=', $this->fromDate))
            ->when($this->toDate !== '', fn (Builder $query) => $query->whereDate('created_at', '<=', $this->toDate))
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    public function render()
    {
        $selected = $this->selectedId !== null ? AuditLog::with('user')->find($this->selectedId) : null;

        return view('livewire.admin.audit-log-index', [
            'logs' => $this->baseQuery()->paginate($this->perPage),
            'users' => User::orderBy('name')->get(['id', 'name']),
            'modules' => AuditLog::query()->distinct()->pluck('module'),
            'actions' => AuditLog::query()->distinct()->pluck('action'),
            'selected' => $selected,
        ]);
    }
}
