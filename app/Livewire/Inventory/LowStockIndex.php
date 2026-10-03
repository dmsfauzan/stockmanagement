<?php

namespace App\Livewire\Inventory;

use App\Models\Category;
use App\Models\Warehouse;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts.app')]
#[Title('Low Stock')]
class LowStockIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $warehouseFilter = '';

    public string $categoryFilter = '';

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

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function export(): StreamedResponse
    {
        $this->dispatch('toast', type: 'success', message: 'Export started.');
        $rows = $this->baseQuery()->orderBy('items.sku')->get();

        return response()->streamDownload(function () use ($rows): void {
            $h = fopen('php://output', 'w');
            fputcsv($h, ['SKU', 'Item', 'Warehouse', 'Location', 'Current Stock', 'Minimum Stock', 'Difference', 'Status']);
            foreach ($rows as $row) {
                $diff = (int) $row->quantity_on_hand - (int) $row->min_stock;
                fputcsv($h, [
                    $row->sku,
                    $row->item_name,
                    $row->warehouse_name,
                    $row->location_code,
                    $row->quantity_on_hand,
                    $row->min_stock,
                    $diff,
                    (int) $row->quantity_on_hand <= 0 ? 'OUT OF STOCK' : 'LOW STOCK',
                ]);
            }
            fclose($h);
        }, 'low-stock-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    protected function baseQuery(): QueryBuilder
    {
        return DB::table('stock_balances')
            ->join('items', 'stock_balances.item_id', '=', 'items.id')
            ->join('warehouses', 'stock_balances.warehouse_id', '=', 'warehouses.id')
            ->join('locations', 'stock_balances.location_id', '=', 'locations.id')
            ->join('categories', 'items.category_id', '=', 'categories.id')
            ->whereNull('items.deleted_at')
            ->whereColumn('stock_balances.quantity_on_hand', '<=', 'items.minimum_stock')
            ->select([
                'stock_balances.id',
                'stock_balances.quantity_on_hand',
                'items.sku',
                'items.name as item_name',
                'items.minimum_stock as min_stock',
                'warehouses.name as warehouse_name',
                'warehouses.id as warehouse_id',
                'locations.code as location_code',
                'categories.id as category_id',
                'categories.name as category_name',
            ])
            ->when($this->search !== '', function (QueryBuilder $q): void {
                $term = '%'.$this->search.'%';
                $q->where(function (QueryBuilder $inner) use ($term): void {
                    $inner->where('items.sku', 'like', $term)->orWhere('items.name', 'like', $term)->orWhere('items.barcode', 'like', $term);
                });
            })
            ->when($this->warehouseFilter !== '', fn (QueryBuilder $q) => $q->where('warehouses.id', $this->warehouseFilter))
            ->when($this->categoryFilter !== '', fn (QueryBuilder $q) => $q->where('categories.id', $this->categoryFilter));
    }

    public function render()
    {
        $query = $this->baseQuery()->orderBy('items.sku');
        $paginator = $query->paginate($this->perPage);

        $counts = DB::table('stock_balances')
            ->join('items', 'stock_balances.item_id', '=', 'items.id')
            ->whereNull('items.deleted_at')
            ->whereColumn('stock_balances.quantity_on_hand', '<=', 'items.minimum_stock')
            ->selectRaw('SUM(CASE WHEN stock_balances.quantity_on_hand <= 0 THEN 1 ELSE 0 END) as out_count, COUNT(*) as low_count')
            ->first();

        return view('livewire.inventory.low-stock-index', [
            'rows' => $paginator,
            'warehouses' => Warehouse::orderBy('name')->get(['id', 'name']),
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'badgeOut' => (int) ($counts->out_count ?? 0),
            'badgeLow' => (int) ($counts->low_count ?? 0),
        ]);
    }
}
