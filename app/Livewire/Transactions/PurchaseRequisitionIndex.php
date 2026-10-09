<?php

namespace App\Livewire\Transactions;

use App\Models\PurchaseRequisition;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\Support\WarehouseAccess;
use App\Services\Workflow\RequisitionWorkflow;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Requisition')]
class PurchaseRequisitionIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public string $rejectionReason = '';

    public int $perPage = 10;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    protected function guard(string $permission): void
    {
        abort_unless(auth()->user()->hasPermission($permission), 403);
    }

    protected function find(int $id): PurchaseRequisition
    {
        return PurchaseRequisition::whereIn('warehouse_id', WarehouseAccess::ids())->findOrFail($id);
    }

    public function submit(int $id): void
    {
        $this->guard('requisition.submit');

        try {
            RequisitionWorkflow::submit($id);
            $this->dispatch('toast', type: 'success', message: __('Requisition diajukan.'));
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function approve(int $id): void
    {
        $this->guard('requisition.approve');

        try {
            RequisitionWorkflow::approve($id);
            $this->dispatch('toast', type: 'success', message: __('Requisition disetujui.'));
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function reject(int $id): void
    {
        $this->guard('requisition.approve');

        $this->validate(['rejectionReason' => ['required', 'string', 'min:3', 'max:500']]);

        try {
            RequisitionWorkflow::reject($id, $this->rejectionReason);
            $this->rejectionReason = '';
            $this->dispatch('toast', type: 'success', message: __('Requisition ditolak.'));
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function convert(int $id): void
    {
        $this->guard('requisition.convert');

        $req = $this->find($id);

        try {
            $order = RequisitionWorkflow::convertToPurchaseOrder(
                $id,
                Supplier::where('status', 'active')->value('id'),
            );
            $this->dispatch('toast', type: 'success', message: __('Dikonversi ke PO :number.', ['number' => $order->number]));
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    protected function baseQuery()
    {
        return PurchaseRequisition::query()
            ->with(['warehouse:id,name', 'requester:id,name', 'purchaseOrder:id,number', 'items'])
            ->whereIn('warehouse_id', WarehouseAccess::ids())
            ->when($this->search !== '', fn ($q) => $q->where('number', 'like', '%'.$this->search.'%'))
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->orderByDesc('id');
    }

    public function render()
    {
        return view('livewire.transactions.purchase-requisition-index', [
            'rows' => $this->baseQuery()->paginate($this->perPage),
            'warehouses' => Warehouse::whereIn('id', WarehouseAccess::ids())->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
