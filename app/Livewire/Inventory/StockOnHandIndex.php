<?php

namespace App\Livewire\Inventory;

use App\Enums\StockStatus;
use App\Models\Category;
use App\Models\Location;
use App\Models\Warehouse;
use App\Services\Support\WarehouseAccess;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts.app')]
#[Title('Stock On Hand')]
class StockOnHandIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $warehouseFilter = '';

    public string $categoryFilter = '';

    public string $locationFilter = '';

    public string $statusFilter = '';

    public string $sortField = 'sku';

    public string $sortDirection = 'asc';

    public int $perPage = 10;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedWarehouseFilter(): void
    {
        $this->resetPage();
    }

    public function updatedCategoryFilter(): void
    {
        $this->resetPage();
    }

    public function updatedLocationFilter(): void
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

    public function sortBy(string $field): void
    {
        if (! in_array($field, ['sku', 'on_hand'], true)) {
            return;
        }
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
        $this->resetPage();
    }

    public function export(): StreamedResponse
    {
        $this->dispatch('toast', type: 'success', message: __('Export started.'));
        $rows = $this->baseQuery()->orderBy($this->sortColumn(), $this->sortDirection)->get();
        foreach ($rows as $r) {
            $r->stock_status = StockStatus::evaluate((int) $r->quantity_on_hand, (int) $r->min_stock, (int) $r->max_stock)->value;
        }

        return response()->streamDownload(function () use ($rows): void {
            $h = fopen('php://output', 'w');
            fputcsv($h, ['SKU', 'Item', 'Category', 'Warehouse', 'Location', 'On Hand', 'Reserved', 'Quarantine', 'Available', 'Min', 'Max', 'Status']);
            foreach ($rows as $row) {
                fputcsv($h, [
                    $row->sku,
                    $row->item_name,
                    $row->category_name,
                    $row->warehouse_name,
                    $row->location_path,
                    $row->quantity_on_hand,
                    $row->quantity_reserved,
                    $row->quantity_quarantine,
                    $row->quantity_available,
                    $row->min_stock,
                    $row->max_stock,
                    $row->stock_status,
                ]);
            }
            fclose($h);
        }, 'stock-on-hand-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    protected function filteredQuery(): QueryBuilder
    {
        $q = DB::table('stock_balances')
            ->join('items', 'stock_balances.item_id', '=', 'items.id')
            ->join('warehouses', 'stock_balances.warehouse_id', '=', 'warehouses.id')
            ->join('locations', 'stock_balances.location_id', '=', 'locations.id')
            ->join('racks', 'locations.rack_id', '=', 'racks.id')
            ->join('zones', 'racks.zone_id', '=', 'zones.id')
            ->join('categories', 'items.category_id', '=', 'categories.id')
            ->whereIn('warehouses.id', WarehouseAccess::ids())
            ->when($this->search !== '', function (QueryBuilder $query): void {
                $term = '%'.$this->search.'%';
                $query->where(function (QueryBuilder $inner) use ($term): void {
                    $inner->where('items.sku', 'like', $term)
                        ->orWhere('items.barcode', 'like', $term)
                        ->orWhere('items.name', 'like', $term);
                });
            })
            ->when($this->warehouseFilter !== '', fn (QueryBuilder $query) => $query->where('warehouses.id', $this->warehouseFilter))
            ->when($this->categoryFilter !== '', fn (QueryBuilder $query) => $query->where('categories.id', $this->categoryFilter))
            ->when($this->locationFilter !== '', fn (QueryBuilder $query) => $query->where('locations.id', $this->locationFilter));

        if ($this->statusFilter !== '') {
            match ($this->statusFilter) {
                'out' => $q->where('stock_balances.quantity_on_hand', '<=', 0),
                'low' => $q->where('stock_balances.quantity_on_hand', '>', 0)->whereColumn('stock_balances.quantity_on_hand', '<=', 'items.minimum_stock'),
                'over' => $q->where('items.maximum_stock', '>', 0)->whereColumn('stock_balances.quantity_on_hand', '>', 'items.maximum_stock'),
                'normal' => $q->where('stock_balances.quantity_on_hand', '>', 0)
                    ->whereColumn('stock_balances.quantity_on_hand', '>', 'items.minimum_stock')
                    ->where(function (QueryBuilder $inner): void {
                        $inner->where('items.maximum_stock', '=', 0)->orWhereColumn('stock_balances.quantity_on_hand', '<=', 'items.maximum_stock');
                    }),
                default => null,
            };
        }

        return $q;
    }

    protected function baseQuery(): QueryBuilder
    {
        return $this->filteredQuery()->select([
            'stock_balances.id',
            'stock_balances.quantity_on_hand',
            'stock_balances.quantity_reserved',
            'stock_balances.quantity_quarantine',
            'stock_balances.quantity_available',
            'items.sku',
            'items.name as item_name',
            'items.barcode',
            'items.minimum_stock as min_stock',
            'items.maximum_stock as max_stock',
            'categories.id as category_id',
            'categories.name as category_name',
            'warehouses.id as warehouse_id',
            'warehouses.name as warehouse_name',
            'locations.id as location_id',
            'locations.code as location_code',
            DB::raw("CONCAT_WS(' / ', warehouses.name, zones.name, racks.name, locations.code) as location_path"),
        ]);
    }

    protected function sortColumn(): string
    {
        return match ($this->sortField) {
            'on_hand' => 'stock_balances.quantity_on_hand',
            default => 'items.sku',
        };
    }

    public function render()
    {
        $paginator = $this->baseQuery()->orderBy($this->sortColumn(), $this->sortDirection)->paginate($this->perPage);
        $paginator->getCollection()->transform(function ($row) {
            $row->stock_status = StockStatus::evaluate((int) $row->quantity_on_hand, (int) $row->min_stock, (int) $row->max_stock)->value;

            return $row;
        });

        $totals = $this->filteredQuery()->selectRaw('COALESCE(SUM(stock_balances.quantity_on_hand),0) as total_on_hand, COALESCE(SUM(stock_balances.quantity_available),0) as total_available')->first();
        $totalsArray = $totals ? ['on_hand' => (int) $totals->total_on_hand, 'available' => (int) $totals->total_available] : ['on_hand' => 0, 'available' => 0];

        return view('livewire.inventory.stock-on-hand-index', [
            'rows' => $paginator,
            'totals' => $totalsArray,
            'warehouses' => Warehouse::whereIn('id', WarehouseAccess::ids())->orderBy('name')->get(['id', 'name']),
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'locations' => Location::orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }
}
