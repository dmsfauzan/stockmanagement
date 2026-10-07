<?php

namespace App\Livewire\MasterData;

use App\Imports\LocationImport;
use App\Livewire\Concerns\ImportsMasterData;
use App\Models\Location;
use App\Models\Rack;
use App\Models\Warehouse;
use App\Models\Zone;
use App\Services\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts.app')]
#[Title('Location / Rack')]
class LocationIndex extends Component
{
    use ImportsMasterData, WithPagination;

    public string $search = '';

    public string $warehouseFilter = '';

    public string $zoneFilter = '';

    public string $trashedFilter = '';

    public int $perPage = 10;

    public array $selectedIds = [];

    public bool $selectAll = false;

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $warehouse_id = '';

    public string $zone_id = '';

    public string $rack_id = '';

    public string $code = '';

    public string $name = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Location::class);
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

        $this->selectedIds = Location::query()
            ->when($this->trashedFilter === 'trashed', fn ($q) => $q->onlyTrashed())
            ->when($this->trashedFilter === 'all', fn ($q) => $q->withTrashed())
            ->when($this->search !== '', function ($query): void {
                $term = '%'.$this->search.'%';
                $query->where(fn ($inner) => $inner->where('code', 'like', $term)->orWhere('name', 'like', $term));
            })
            ->when($this->warehouseFilter !== '', function ($query): void {
                $query->whereHas('rack.zone', fn ($inner) => $inner->where('warehouse_id', $this->warehouseFilter));
            })
            ->when($this->zoneFilter !== '', function ($query): void {
                $query->whereHas('rack', fn ($inner) => $inner->where('zone_id', $this->zoneFilter));
            })
            ->orderBy('code')
            ->paginate($this->perPage)
            ->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    public function updatedWarehouseFilter(): void
    {
        $this->zoneFilter = '';
        $this->resetPage();
    }

    public function updatedZoneFilter(): void
    {
        $this->resetPage();
    }

    public function updatedWarehouseId(string $value): void
    {
        if ($this->zone_id && ! Zone::where('id', $this->zone_id)->where('warehouse_id', $value)->exists()) {
            $this->zone_id = '';
            $this->rack_id = '';
        }
    }

    public function updatedZoneId(string $value): void
    {
        if ($this->rack_id && ! Rack::where('id', $this->rack_id)->where('zone_id', $value)->exists()) {
            $this->rack_id = '';
        }
    }

    public function openCreate(): void
    {
        $this->authorize('create', Location::class);

        $this->resetValidation();
        $this->reset(['editingId', 'warehouse_id', 'zone_id', 'rack_id', 'code', 'name']);
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $location = Location::with('rack.zone')->findOrFail($id);
        $this->authorize('update', $location);
        $this->resetValidation();

        $this->editingId = $location->id;
        $this->rack_id = (string) $location->rack_id;
        $this->zone_id = (string) $location->rack?->zone_id;
        $this->warehouse_id = (string) $location->rack?->zone?->warehouse_id;
        $this->code = (string) $location->code;
        $this->name = (string) $location->name;
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
        $target = $this->editingId ? Location::findOrFail($this->editingId) : Location::class;
        $this->authorize($permission, $target);

        if (! $this->editingId && $this->trashedConflict('locations', $this->code)) {
            $this->dispatch('toast', type: 'error', message: 'Kode sudah dipakai data terhapus. Pulihkan dari filter Terhapus.');

            return;
        }

        $this->validate([
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'zone_id' => ['required', 'exists:zones,id'],
            'rack_id' => ['required', 'exists:racks,id'],
            'code' => ['required', 'string', 'max:50', Rule::unique('locations', 'code')->ignore($this->editingId)],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $rack = Rack::with('zone')->findOrFail($this->rack_id);

        if ((string) $rack->zone_id !== (string) $this->zone_id) {
            $this->addError('rack_id', 'Rack tidak termasuk dalam Zone yang dipilih.');

            return;
        }

        if ((string) $rack->zone->warehouse_id !== (string) $this->warehouse_id) {
            $this->addError('zone_id', 'Zone tidak termasuk dalam Warehouse yang dipilih.');

            return;
        }

        $data = [
            'rack_id' => $this->rack_id,
            'code' => $this->code,
            'name' => $this->name,
        ];

        if ($this->editingId) {
            $location = Location::findOrFail($this->editingId);
            $old = $location->toArray();
            $location->update($data);
            AuditLogger::logModel('update', $location, $old, $location->fresh()->toArray());
        } else {
            $location = Location::create($data);
            AuditLogger::logModel('create', $location, null, $location->toArray());
        }

        $this->showModal = false;
        $this->dispatch('toast', type: 'success', message: 'Tersimpan');
    }

    public function delete(int $id): void
    {
        $location = Location::findOrFail($id);
        $this->authorize('delete', $location);

        $old = $location->toArray();
        $location->delete();

        AuditLogger::logModel('delete', $location, $old);

        $this->dispatch('toast', type: 'success', message: 'Location dihapus.');
    }

    public function bulkDelete(): void
    {
        abort_unless(auth()->user()->hasPermission('location.delete'), 403);

        if ($this->selectedIds === []) {
            $this->dispatch('toast', type: 'error', message: 'Tidak ada data terpilih.');

            return;
        }

        $deleted = 0;
        $skipped = 0;

        foreach (Location::whereIn('id', $this->selectedIds)->get() as $location) {
            if (auth()->user()->cannot('delete', $location)) {
                $skipped++;

                continue;
            }

            $old = $location->toArray();
            $location->delete();
            AuditLogger::logModel('delete', $location, $old);
            $deleted++;
        }

        $this->reset('selectedIds', 'selectAll');

        $this->dispatch('toast', type: $deleted > 0 ? 'success' : 'error', message: "Hapus {$deleted} lokasi, {$skipped} dilewati.");
    }

    public function restore(int $id): void
    {
        $location = Location::withTrashed()->findOrFail($id);
        $this->authorize('update', $location);
        $location->restore();

        AuditLogger::logModel('restore', $location, null, $location->fresh()->toArray());

        $this->dispatch('toast', type: 'success', message: 'Location dipulihkan.');
    }

    public function bulkRestore(): void
    {
        abort_unless(auth()->user()->hasPermission('location.update'), 403);

        if ($this->selectedIds === []) {
            $this->dispatch('toast', type: 'error', message: 'Tidak ada data terpilih.');

            return;
        }

        $count = 0;

        foreach (Location::withTrashed()->whereIn('id', $this->selectedIds)->get() as $location) {
            $location->restore();
            AuditLogger::logModel('restore', $location, null, $location->fresh()->toArray());
            $count++;
        }

        $this->reset('selectedIds', 'selectAll');

        $this->dispatch('toast', type: 'success', message: "Pulihkan {$count} data.");
    }

    private function trashedConflict(string $table, string $code): bool
    {
        return DB::table($table)->where('code', trim($code))->whereNotNull('deleted_at')->exists();
    }

    public function export(): StreamedResponse
    {
        $this->authorize('viewAny', Location::class);

        $rows = Location::query()
            ->with(['rack.zone.warehouse'])
            ->when($this->search !== '', function ($query): void {
                $term = '%'.$this->search.'%';
                $query->where(fn ($inner) => $inner->where('code', 'like', $term)->orWhere('name', 'like', $term));
            })
            ->when($this->warehouseFilter !== '', function ($query): void {
                $query->whereHas('rack.zone', fn ($inner) => $inner->where('warehouse_id', $this->warehouseFilter));
            })
            ->when($this->zoneFilter !== '', function ($query): void {
                $query->whereHas('rack', fn ($inner) => $inner->where('zone_id', $this->zoneFilter));
            })
            ->orderBy('code')
            ->get();

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Code', 'Name', 'Warehouse', 'Zone', 'Rack', 'Full Path']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->code,
                    $row->name,
                    $row->rack?->zone?->warehouse?->name,
                    $row->rack?->zone?->name,
                    $row->rack?->name,
                    $row->fullPath(),
                ]);
            }

            fclose($handle);
        }, 'locations-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    protected function importClass(): string
    {
        return LocationImport::class;
    }

    protected function importLabelText(): string
    {
        return 'Location';
    }

    protected function importModule(): string
    {
        return 'locations';
    }

    protected function importPermission(): string
    {
        return 'location.create';
    }

    protected function importSampleRow(): array
    {
        return ['WH01', 'Z01', 'R01', 'L01', 'Rak 1 Level 1'];
    }

    public function render()
    {
        return view('livewire.master-data.location-index', [
            'locations' => Location::query()
                ->with(['rack.zone.warehouse'])
                ->when($this->trashedFilter === 'trashed', fn ($q) => $q->onlyTrashed())
                ->when($this->trashedFilter === 'all', fn ($q) => $q->withTrashed())
                ->when($this->search !== '', function ($query): void {
                    $term = '%'.$this->search.'%';
                    $query->where(fn ($inner) => $inner->where('code', 'like', $term)->orWhere('name', 'like', $term));
                })
                ->when($this->warehouseFilter !== '', function ($query): void {
                    $query->whereHas('rack.zone', fn ($inner) => $inner->where('warehouse_id', $this->warehouseFilter));
                })
                ->when($this->zoneFilter !== '', function ($query): void {
                    $query->whereHas('rack', fn ($inner) => $inner->where('zone_id', $this->zoneFilter));
                })
                ->orderBy('code')
                ->paginate($this->perPage),
            'warehouses' => Warehouse::orderBy('name')->get(['id', 'name', 'code']),
            'zones' => Zone::when($this->warehouse_id !== '', fn ($query) => $query->where('warehouse_id', $this->warehouse_id))
                ->orderBy('name')->get(['id', 'name', 'code', 'warehouse_id']),
            'racks' => Rack::when($this->zone_id !== '', fn ($query) => $query->where('zone_id', $this->zone_id))
                ->orderBy('name')->get(['id', 'name', 'code', 'zone_id']),
            'filterZones' => Zone::when($this->warehouseFilter !== '', fn ($query) => $query->where('warehouse_id', $this->warehouseFilter))
                ->orderBy('name')->get(['id', 'name', 'code']),
        ]);
    }
}
