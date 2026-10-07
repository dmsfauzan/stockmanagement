<?php

namespace App\Livewire\Transactions;

use App\Models\Customer;
use App\Models\SalesOrder;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts.app')]
#[Title('Sales Order')]
class SalesOrderIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public string $customerFilter = '';

    public string $warehouseFilter = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public string $sortDirection = 'desc';

    public int $perPage = 10;

    public function mount(): void
    {
        $this->authorize('viewAny', SalesOrder::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedCustomerFilter(): void
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
        return SalesOrder::query()
            ->with(['customer', 'warehouse'])
            ->withCount('items')
            ->withSum('items as total_quantity', 'quantity')
            ->withSum('items as total_fulfilled', 'fulfilled_quantity')
            ->when($this->search !== '', function (Builder $query): void {
                $term = '%'.$this->search.'%';
                $query->where(function (Builder $inner) use ($term): void {
                    $inner->where('number', 'like', $term)
                        ->orWhereHas('customer', fn (Builder $q) => $q->where('name', 'like', $term));
                });
            })
            ->when($this->statusFilter !== '', fn (Builder $query) => $query->where('status', $this->statusFilter))
            ->when($this->customerFilter !== '', fn (Builder $query) => $query->where('customer_id', $this->customerFilter))
            ->when($this->warehouseFilter !== '', fn (Builder $query) => $query->where('warehouse_id', $this->warehouseFilter))
            ->when($this->dateFrom !== '', fn (Builder $query) => $query->whereDate('order_date', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn (Builder $query) => $query->whereDate('order_date', '<=', $this->dateTo));
    }

    public function export(): StreamedResponse
    {
        $this->authorize('viewAny', SalesOrder::class);

        $rows = $this->baseQuery()->orderBy('order_date', $this->sortDirection)->orderByDesc('id')->get();

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Number', 'Date', 'Customer', 'Warehouse', 'Items', 'Fulfilled', 'Total', 'Status']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->number,
                    $row->order_date?->format('Y-m-d'),
                    $row->customer?->name,
                    $row->warehouse?->name,
                    $row->items_count,
                    (int) ($row->total_fulfilled ?? 0),
                    (int) ($row->total_quantity ?? 0),
                    $row->status,
                ]);
            }

            fclose($handle);
        }, 'sales-orders-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function render()
    {
        return view('livewire.transactions.sales-order-index', [
            'orders' => $this->baseQuery()
                ->orderBy('order_date', $this->sortDirection)
                ->orderByDesc('id')
                ->paginate($this->perPage),
            'warehouses' => Warehouse::orderBy('name')->get(['id', 'name']),
            'customers' => Customer::orderBy('name')->get(['id', 'name']),
            'statuses' => ['draft', 'submitted', 'approved', 'partial', 'fulfilled', 'closed', 'rejected'],
        ]);
    }
}
