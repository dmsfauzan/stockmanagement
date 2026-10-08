<?php

namespace App\Livewire\MasterData;

use App\Imports\WarehouseImport;
use App\Livewire\Concerns\ImportsMasterData;
use App\Models\Warehouse;
use App\Services\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts.app')]
#[Title('Warehouse')]
class WarehouseIndex extends Component
{
    use ImportsMasterData, WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public string $trashedFilter = '';

    public int $perPage = 10;

    public array $selectedIds = [];

    public bool $selectAll = false;

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $code = '';

    public string $name = '';

    public string $address = '';

    public string $status = 'active';

    public function mount(): void
    {
        $this->authorize('viewAny', Warehouse::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedTrashedFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSelectedIds(): void
    {
        if ($this->selectedIds === []) {
            $this->selectAll = false;
        }
    }

    public function updatedSelectAll(): void
    {
        $this->toggleSelectAll();
    }

    public function toggleSelectAll(): void
    {
        if (! $this->selectAll) {
            $this->selectedIds = [];

            return;
        }

        $this->selectedIds = Warehouse::query()
            ->when($this->trashedFilter === 'trashed', fn ($q) => $q->onlyTrashed())
            ->when($this->trashedFilter === 'all', fn ($q) => $q->withTrashed())
            ->when($this->search !== '', function ($query): void {
                $term = '%'.$this->search.'%';
                $query->where(fn ($inner) => $inner->where('code', 'like', $term)->orWhere('name', 'like', $term));
            })
            ->when($this->statusFilter !== '', fn ($query) => $query->where('status', $this->statusFilter))
            ->orderBy('name')
            ->paginate($this->perPage)
            ->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->authorize('create', Warehouse::class);

        $this->resetValidation();
        $this->reset(['editingId', 'code', 'name', 'address']);
        $this->status = 'active';
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $warehouse = Warehouse::findOrFail($id);
        $this->authorize('update', $warehouse);
        $this->resetValidation();

        $this->editingId = $warehouse->id;
        $this->code = (string) $warehouse->code;
        $this->name = (string) $warehouse->name;
        $this->address = (string) ($warehouse->address ?? '');
        $this->status = (string) $warehouse->status;
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetValidation();
    }

    public function save(): void
    {
        $permission = $this->editingId ? 'update' : 'create';
        $target = $this->editingId ? Warehouse::findOrFail($this->editingId) : Warehouse::class;
        $this->authorize($permission, $target);

        if (! $this->editingId && $this->trashedConflict('warehouses', $this->code)) {
            $this->dispatch('toast', type: 'error', message: __('Kode sudah dipakai data terhapus. Pulihkan dari filter Terhapus.'));

            return;
        }

        $data = $this->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('warehouses', 'code')->ignore($this->editingId)],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        $data['address'] = $data['address'] !== '' ? $data['address'] : null;

        if ($this->editingId) {
            $warehouse = Warehouse::findOrFail($this->editingId);
            $old = $warehouse->toArray();
            $warehouse->update($data);
            AuditLogger::logModel('update', $warehouse, $old, $warehouse->fresh()->toArray());
        } else {
            $warehouse = Warehouse::create($data);
            AuditLogger::logModel('create', $warehouse, null, $warehouse->toArray());
        }

        $this->showModal = false;
        $this->dispatch('toast', type: 'success', message: __('Tersimpan'));
    }

    public function delete(int $id): void
    {
        $warehouse = Warehouse::findOrFail($id);
        $this->authorize('delete', $warehouse);

        $old = $warehouse->toArray();
        $warehouse->delete();

        AuditLogger::logModel('delete', $warehouse, $old);

        $this->dispatch('toast', type: 'success', message: __('Warehouse dihapus.'));
    }

    public function bulkDelete(): void
    {
        abort_unless(auth()->user()->hasPermission('warehouse.delete'), 403);

        if ($this->selectedIds === []) {
            $this->dispatch('toast', type: 'error', message: __('Tidak ada data terpilih.'));

            return;
        }

        $deleted = 0;
        $skipped = 0;

        foreach (Warehouse::whereIn('id', $this->selectedIds)->get() as $warehouse) {
            if (auth()->user()->cannot('delete', $warehouse)) {
                $skipped++;

                continue;
            }

            $old = $warehouse->toArray();
            $warehouse->delete();
            AuditLogger::logModel('delete', $warehouse, $old);
            $deleted++;
        }

        $this->reset('selectedIds', 'selectAll');

        $this->dispatch('toast', type: $deleted > 0 ? 'success' : 'error', message: __('Hapus :deleted warehouse, :skipped dilewati.', ['deleted' => $deleted, 'skipped' => $skipped]));
    }

    public function restore(int $id): void
    {
        $warehouse = Warehouse::withTrashed()->findOrFail($id);
        $this->authorize('update', $warehouse);
        $warehouse->restore();

        AuditLogger::logModel('restore', $warehouse, null, $warehouse->fresh()->toArray());

        $this->dispatch('toast', type: 'success', message: __('Warehouse dipulihkan.'));
    }

    public function bulkRestore(): void
    {
        abort_unless(auth()->user()->hasPermission('warehouse.update'), 403);

        if ($this->selectedIds === []) {
            $this->dispatch('toast', type: 'error', message: __('Tidak ada data terpilih.'));

            return;
        }

        $count = 0;

        foreach (Warehouse::withTrashed()->whereIn('id', $this->selectedIds)->get() as $warehouse) {
            $warehouse->restore();
            AuditLogger::logModel('restore', $warehouse, null, $warehouse->fresh()->toArray());
            $count++;
        }

        $this->reset('selectedIds', 'selectAll');

        $this->dispatch('toast', type: 'success', message: __('Pulihkan :count data.', ['count' => $count]));
    }

    private function trashedConflict(string $table, string $code): bool
    {
        return DB::table($table)->where('code', trim($code))->whereNotNull('deleted_at')->exists();
    }

    public function bulkActivate(): void
    {
        $this->bulkSetStatus('active');
    }

    public function bulkDeactivate(): void
    {
        $this->bulkSetStatus('inactive');
    }

    protected function bulkSetStatus(string $status): void
    {
        abort_unless(auth()->user()->hasPermission('warehouse.update'), 403);

        if ($this->selectedIds === []) {
            $this->dispatch('toast', type: 'error', message: __('Tidak ada data terpilih.'));

            return;
        }

        $count = 0;

        foreach (Warehouse::whereIn('id', $this->selectedIds)->get() as $warehouse) {
            $old = $warehouse->toArray();
            $warehouse->update(['status' => $status]);
            AuditLogger::logModel('update', $warehouse, $old, $warehouse->fresh()->toArray());
            $count++;
        }

        $this->reset('selectedIds', 'selectAll');

        $this->dispatch('toast', type: 'success', message: __(':count warehouse diperbarui menjadi :status.', ['count' => $count, 'status' => $status]));
    }

    public function export(): StreamedResponse
    {
        $this->authorize('viewAny', Warehouse::class);

        $rows = Warehouse::query()
            ->withCount('zones')
            ->withCount(['zones as racks_count' => fn ($q) => $q->join('racks', 'racks.zone_id', '=', 'zones.id')])
            ->withCount(['zones as locations_count' => fn ($q) => $q->join('racks', 'racks.zone_id', '=', 'zones.id')->join('locations', 'locations.rack_id', '=', 'racks.id')])
            ->when($this->search !== '', function ($query): void {
                $term = '%'.$this->search.'%';
                $query->where(fn ($inner) => $inner->where('code', 'like', $term)->orWhere('name', 'like', $term));
            })
            ->when($this->statusFilter !== '', fn ($query) => $query->where('status', $this->statusFilter))
            ->orderBy('name')
            ->get();

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Code', 'Name', 'Address', 'Status', 'Zones', 'Racks', 'Locations']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->code,
                    $row->name,
                    $row->address,
                    $row->status,
                    $row->zones_count,
                    $row->racks_count,
                    $row->locations_count,
                ]);
            }

            fclose($handle);
        }, 'warehouses-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    protected function importClass(): string
    {
        return WarehouseImport::class;
    }

    protected function importLabelText(): string
    {
        return 'Warehouse';
    }

    protected function importModule(): string
    {
        return 'warehouses';
    }

    protected function importPermission(): string
    {
        return 'warehouse.create';
    }

    protected function importSampleRow(): array
    {
        return ['WH01', 'Gudang Utama', 'Jl. Industri 1', 'active'];
    }

    public function render()
    {
        return view('livewire.master-data.warehouse-index', [
            'warehouses' => Warehouse::query()
                ->withCount('zones')
                ->withCount(['zones as racks_count' => fn ($q) => $q->join('racks', 'racks.zone_id', '=', 'zones.id')])
                ->withCount(['zones as locations_count' => fn ($q) => $q->join('racks', 'racks.zone_id', '=', 'zones.id')->join('locations', 'locations.rack_id', '=', 'racks.id')])
                ->when($this->trashedFilter === 'trashed', fn ($q) => $q->onlyTrashed())
                ->when($this->trashedFilter === 'all', fn ($q) => $q->withTrashed())
                ->when($this->search !== '', function ($query): void {
                    $term = '%'.$this->search.'%';
                    $query->where(fn ($inner) => $inner->where('code', 'like', $term)->orWhere('name', 'like', $term));
                })
                ->when($this->statusFilter !== '', fn ($query) => $query->where('status', $this->statusFilter))
                ->orderBy('name')
                ->paginate($this->perPage),
        ]);
    }
}
