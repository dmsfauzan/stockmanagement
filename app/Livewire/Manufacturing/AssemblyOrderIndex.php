<?php

namespace App\Livewire\Manufacturing;

use App\Enums\AssemblyType;
use App\Models\AssemblyOrder;
use App\Models\Warehouse;
use App\Services\Inventory\AssemblyService;
use App\Services\Support\WarehouseAccess;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Perakitan / Kit')]
class AssemblyOrderIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public string $typeFilter = '';

    public int $perPage = 10;

    public string $reversalReason = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function post(int $id): void
    {
        abort_unless(auth()->user()->hasPermission('assembly.post'), 403);

        $order = AssemblyOrder::whereIn('warehouse_id', WarehouseAccess::ids())->findOrFail($id);

        abort_unless(auth()->user()?->canAccessWarehouse((int) $order->warehouse_id), 403);

        try {
            AssemblyService::post($order);
            $this->dispatch('toast', type: 'success', message: __('Perakitan diposting.'));
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function reverse(int $id): void
    {
        abort_unless(auth()->user()->hasPermission('assembly.post'), 403);

        $this->validate(['reversalReason' => ['required', 'string', 'min:3', 'max:500']]);

        $order = AssemblyOrder::whereIn('warehouse_id', WarehouseAccess::ids())->findOrFail($id);

        abort_unless(auth()->user()?->canAccessWarehouse((int) $order->warehouse_id), 403);

        try {
            AssemblyService::reverse($order, $this->reversalReason);
            $this->reversalReason = '';
            $this->dispatch('toast', type: 'success', message: __('Reversal berhasil.'));
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    protected function baseQuery()
    {
        return AssemblyOrder::query()
            ->with(['item:id,sku,name', 'warehouse:id,name'])
            ->whereIn('warehouse_id', WarehouseAccess::ids())
            ->when($this->search !== '', function ($q): void {
                $term = '%'.$this->search.'%';
                $q->where('number', 'like', $term);
            })
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->typeFilter !== '', fn ($q) => $q->where('type', $this->typeFilter))
            ->orderByDesc('id');
    }

    public function render()
    {
        return view('livewire.manufacturing.assembly-order-index', [
            'rows' => $this->baseQuery()->paginate($this->perPage),
            'types' => AssemblyType::cases(),
            'warehouses' => Warehouse::whereIn('id', WarehouseAccess::ids())->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
