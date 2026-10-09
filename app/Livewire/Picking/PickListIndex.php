<?php

namespace App\Livewire\Picking;

use App\Models\PickList;
use App\Models\Warehouse;
use App\Services\Support\WarehouseAccess;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Daftar Picking')]
class PickListIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public string $warehouseFilter = '';

    public int $perPage = 10;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedWarehouseFilter(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    protected function baseQuery()
    {
        return PickList::query()
            ->with(['warehouse', 'goodsIssue', 'salesOrder', 'assignee'])
            ->whereIn('warehouse_id', WarehouseAccess::ids())
            ->when($this->search !== '', function ($q): void {
                $term = '%'.$this->search.'%';
                $q->where(function ($inner) use ($term): void {
                    $inner->where('number', 'like', $term);
                });
            })
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->warehouseFilter !== '', fn ($q) => $q->where('warehouse_id', $this->warehouseFilter))
            ->orderByDesc('id');
    }

    public function render()
    {
        return view('livewire.picking.pick-list-index', [
            'rows' => $this->baseQuery()->paginate($this->perPage),
            'warehouses' => Warehouse::whereIn('id', WarehouseAccess::ids())->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
