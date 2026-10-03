<?php

namespace App\Livewire\MasterData;

use App\Models\Warehouse;
use App\Services\Support\AuditLogger;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Warehouse')]
class WarehouseIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public int $perPage = 10;

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
        $this->dispatch('toast', type: 'success', message: 'Tersimpan');
    }

    public function delete(int $id): void
    {
        $warehouse = Warehouse::findOrFail($id);
        $this->authorize('delete', $warehouse);

        $old = $warehouse->toArray();
        $warehouse->delete();

        AuditLogger::logModel('delete', $warehouse, $old);

        $this->dispatch('toast', type: 'success', message: 'Warehouse dihapus.');
    }

    public function render()
    {
        return view('livewire.master-data.warehouse-index', [
            'warehouses' => Warehouse::query()
                ->withCount('zones')
                ->withCount(['zones as racks_count' => fn ($q) => $q->join('racks', 'racks.zone_id', '=', 'zones.id')])
                ->withCount(['zones as locations_count' => fn ($q) => $q->join('racks', 'racks.zone_id', '=', 'zones.id')->join('locations', 'locations.rack_id', '=', 'racks.id')])
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
