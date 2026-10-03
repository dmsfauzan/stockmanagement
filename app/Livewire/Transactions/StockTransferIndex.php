<?php

namespace App\Livewire\Transactions;

use App\Models\StockTransfer;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Transfer Barang')]
class StockTransferIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public string $warehouseFromFilter = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public string $sortDirection = 'desc';

    public int $perPage = 10;

    public function mount(): void
    {
        $this->authorize('viewAny', StockTransfer::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedWarehouseFromFilter(): void
    {
        $this->resetPage();
    }

    public function updatedDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function sortByDate(): void
    {
        $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        $this->resetPage();
    }

    protected function baseQuery(): Builder
    {
        return StockTransfer::query()
            ->with(['fromWarehouse', 'fromLocation.rack.zone.warehouse', 'toWarehouse', 'toLocation.rack.zone.warehouse'])
            ->withCount('items')
            ->when($this->search !== '', function (Builder $query): void {
                $term = '%'.$this->search.'%';
                $query->where(function (Builder $inner) use ($term): void {
                    $inner->where('number', 'like', $term)
                        ->orWhere('notes', 'like', $term);
                });
            })
            ->when($this->statusFilter !== '', fn (Builder $query) => $query->where('status', $this->statusFilter))
            ->when($this->warehouseFromFilter !== '', fn (Builder $query) => $query->where('from_warehouse_id', $this->warehouseFromFilter))
            ->when($this->dateFrom !== '', fn (Builder $query) => $query->whereDate('transfer_date', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn (Builder $query) => $query->whereDate('transfer_date', '<=', $this->dateTo));
    }

    public function render()
    {
        return view('livewire.transactions.stock-transfer-index', [
            'transfers' => $this->baseQuery()
                ->orderBy('transfer_date', $this->sortDirection)
                ->orderByDesc('id')
                ->paginate($this->perPage),
            'warehouses' => Warehouse::orderBy('name')->get(['id', 'name']),
            'statuses' => ['draft', 'requested', 'approved', 'in_transit', 'received', 'rejected', 'completed'],
        ]);
    }
}
