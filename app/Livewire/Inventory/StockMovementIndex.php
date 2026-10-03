<?php

namespace App\Livewire\Inventory;

use App\Models\Location;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts.app')]
#[Title('Stock Movement')]
class StockMovementIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $warehouseFilter = '';

    public string $locationFilter = '';

    public string $transactionTypeFilter = '';

    public string $userFilter = '';

    public string $fromDate = '';

    public string $toDate = '';

    public string $sortField = 'created_at';

    public string $sortDirection = 'desc';

    public int $perPage = 10;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedWarehouseFilter(): void
    {
        $this->resetPage();
    }

    public function updatedLocationFilter(): void
    {
        $this->resetPage();
    }

    public function updatedTransactionTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatedUserFilter(): void
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

    public function clearDates(): void
    {
        $this->fromDate = '';
        $this->toDate = '';
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if (! in_array($field, ['created_at', 'sku'], true)) {
            return;
        }
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = $field === 'created_at' ? 'desc' : 'asc';
        }
        $this->resetPage();
    }

    public function export(): StreamedResponse
    {
        $this->dispatch('toast', type: 'success', message: 'Export started.');
        $rows = $this->baseQuery()->orderBy($this->sortColumn(), $this->sortDirection)->orderBy('stock_movements.id', 'desc')->get();

        return response()->streamDownload(function () use ($rows): void {
            $h = fopen('php://output', 'w');
            fputcsv($h, ['Date', 'Reference', 'Type', 'SKU', 'Item', 'Warehouse', 'Location', 'Qty In', 'Qty Out', 'Balance After', 'User']);
            foreach ($rows as $row) {
                fputcsv($h, [
                    $row->created_at,
                    $row->reference_type.'#'.$row->reference_id,
                    $row->transaction_type,
                    $row->sku,
                    $row->item_name,
                    $row->warehouse_name,
                    $row->location_code,
                    $row->quantity_in,
                    $row->quantity_out,
                    $row->balance_after,
                    $row->user_name,
                ]);
            }
            fclose($h);
        }, 'stock-movements-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    protected function baseQuery(): QueryBuilder
    {
        return DB::table('stock_movements')
            ->leftJoin('items', 'stock_movements.item_id', '=', 'items.id')
            ->leftJoin('warehouses', 'stock_movements.warehouse_id', '=', 'warehouses.id')
            ->leftJoin('locations', 'stock_movements.location_id', '=', 'locations.id')
            ->leftJoin('users', 'stock_movements.created_by', '=', 'users.id')
            ->select([
                'stock_movements.id',
                'stock_movements.created_at',
                'stock_movements.reference_type',
                'stock_movements.reference_id',
                'stock_movements.transaction_type',
                'stock_movements.quantity_in',
                'stock_movements.quantity_out',
                'stock_movements.balance_after',
                'items.sku',
                'items.name as item_name',
                'warehouses.name as warehouse_name',
                'locations.code as location_code',
                'users.name as user_name',
            ])
            ->when($this->search !== '', function (QueryBuilder $q): void {
                $term = '%'.$this->search.'%';
                $q->where(function (QueryBuilder $inner) use ($term): void {
                    $inner->where('items.sku', 'like', $term)->orWhere('items.name', 'like', $term);
                });
            })
            ->when($this->warehouseFilter !== '', fn (QueryBuilder $q) => $q->where('stock_movements.warehouse_id', $this->warehouseFilter))
            ->when($this->locationFilter !== '', fn (QueryBuilder $q) => $q->where('stock_movements.location_id', $this->locationFilter))
            ->when($this->transactionTypeFilter !== '', fn (QueryBuilder $q) => $q->where('stock_movements.transaction_type', $this->transactionTypeFilter))
            ->when($this->userFilter !== '', fn (QueryBuilder $q) => $q->where('stock_movements.created_by', $this->userFilter))
            ->when($this->fromDate !== '', fn (QueryBuilder $q) => $q->whereDate('stock_movements.created_at', '>=', $this->fromDate))
            ->when($this->toDate !== '', fn (QueryBuilder $q) => $q->whereDate('stock_movements.created_at', '<=', $this->toDate));
    }

    protected function sortColumn(): string
    {
        return match ($this->sortField) {
            'sku' => 'items.sku',
            default => 'stock_movements.created_at',
        };
    }

    public function render()
    {
        return view('livewire.inventory.stock-movement-index', [
            'rows' => $this->baseQuery()->orderBy($this->sortColumn(), $this->sortDirection)->orderBy('stock_movements.id', 'desc')->paginate($this->perPage),
            'warehouses' => Warehouse::orderBy('name')->get(['id', 'name']),
            'locations' => Location::orderBy('code')->get(['id', 'code', 'name']),
            'users' => User::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
