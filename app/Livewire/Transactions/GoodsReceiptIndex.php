<?php

namespace App\Livewire\Transactions;

use App\Models\GoodsReceipt;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Barang Masuk')]
class GoodsReceiptIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public string $warehouseFilter = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public string $sortDirection = 'desc';

    public int $perPage = 10;

    public function mount(): void
    {
        $this->authorize('viewAny', GoodsReceipt::class);
    }

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
        return GoodsReceipt::query()
            ->with(['supplier', 'warehouse', 'creator'])
            ->withCount('receiptItems')
            ->when($this->search !== '', function (Builder $query): void {
                $term = '%'.$this->search.'%';
                $query->where(function (Builder $inner) use ($term): void {
                    $inner->where('number', 'like', $term)
                        ->orWhere('po_number', 'like', $term)
                        ->orWhere('delivery_note', 'like', $term);
                });
            })
            ->when($this->statusFilter !== '', fn (Builder $query) => $query->where('status', $this->statusFilter))
            ->when($this->warehouseFilter !== '', fn (Builder $query) => $query->where('warehouse_id', $this->warehouseFilter))
            ->when($this->dateFrom !== '', fn (Builder $query) => $query->whereDate('transaction_date', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn (Builder $query) => $query->whereDate('transaction_date', '<=', $this->dateTo));
    }

    public function render()
    {
        return view('livewire.transactions.goods-receipt-index', [
            'receipts' => $this->baseQuery()
                ->orderBy('transaction_date', $this->sortDirection)
                ->orderByDesc('id')
                ->paginate($this->perPage),
            'warehouses' => Warehouse::orderBy('name')->get(['id', 'name']),
            'statuses' => ['draft', 'submitted', 'approved', 'rejected', 'posted'],
        ]);
    }
}
