<?php

namespace App\Livewire\Reports;

use App\Enums\StockStatus;
use App\Exports\StockExport;
use App\Livewire\Reports\Concerns\HandlesReportColumns;
use App\Livewire\Reports\Concerns\HandlesReportExport;
use App\Livewire\Reports\Concerns\HandlesSavedFilters;
use App\Models\Category;
use App\Models\Location;
use App\Models\Warehouse;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts.app')]
#[Title('Laporan Stok')]
class StockReport extends Component
{
    use HandlesReportColumns, HandlesReportExport, HandlesSavedFilters, WithPagination;

    public string $search = '';

    public string $warehouseFilter = '';

    public string $locationFilter = '';

    public string $categoryFilter = '';

    public string $statusFilter = '';

    public string $sortField = 'sku';

    public string $sortDirection = 'asc';

    public int $perPage = 15;

    protected function filterKeys(): array
    {
        return ['search', 'warehouseFilter', 'locationFilter', 'categoryFilter', 'statusFilter'];
    }

    public function reportColumns(): array
    {
        return [
            'sku' => ['label' => 'SKU'],
            'item' => ['label' => 'Barang'],
            'category' => ['label' => 'Kategori'],
            'warehouse' => ['label' => 'Warehouse'],
            'location' => ['label' => 'Lokasi'],
            'on_hand' => ['label' => 'On Hand', 'align' => 'right'],
            'min' => ['label' => 'Min', 'align' => 'right'],
            'max' => ['label' => 'Max', 'align' => 'right'],
            'status' => ['label' => 'Status'],
        ];
    }

    public function mount(): void
    {
        abort_unless(auth()->user()->hasPermission('reports.view'), 403);

        $this->mountReportColumns();
    }

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

    public function updatedCategoryFilter(): void
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

    public function exportCsv(): StreamedResponse
    {
        abort_unless(auth()->user()->hasPermission('reports.export'), 403);

        $rows = $this->exportRows();

        return $this->csvResponse(
            'stock-report-'.now()->format('Ymd-His').'.csv',
            ['SKU', 'Item', 'Category', 'Warehouse', 'Location', 'On Hand', 'Min', 'Max', 'Status'],
            $rows,
        );
    }

    public function exportExcel()
    {
        abort_unless(auth()->user()->hasPermission('reports.export'), 403);

        return Excel::download(new StockExport($this->filterPayload()), 'stock-report-'.now()->format('Ymd-His').'.xlsx');
    }

    public function exportPdf(): StreamedResponse
    {
        abort_unless(auth()->user()->hasPermission('reports.export'), 403);

        return $this->pdfResponse('reports.pdf.stock', [
            'rows' => $this->exportRows(true),
            'filters' => $this->filterLines(),
            'totals' => $this->totals(),
            'generatedAt' => now()->format('d M Y H:i'),
        ], 'stock-report-'.now()->format('Ymd-His').'.pdf');
    }

    protected function exportRows(bool $withStatusLabel = false)
    {
        return $this->baseQuery()
            ->orderBy($this->sortColumn(), $this->sortDirection)
            ->get()
            ->map(function ($row) use ($withStatusLabel) {
                $status = StockStatus::evaluate((int) $row->quantity_on_hand, (int) $row->min_stock, (int) $row->max_stock);

                return [
                    'sku' => $row->sku,
                    'item_name' => $row->item_name,
                    'category_name' => $row->category_name,
                    'warehouse_name' => $row->warehouse_name,
                    'location_path' => $row->location_path,
                    'quantity_on_hand' => (int) $row->quantity_on_hand,
                    'min_stock' => (int) $row->min_stock,
                    'max_stock' => (int) $row->max_stock,
                    'status' => $withStatusLabel ? $status->label() : $status->value,
                    'status_color' => $status->badgeColor(),
                ];
            });
    }

    protected function totals(): array
    {
        $row = $this->baseQuery()
            ->reorder()
            ->select(DB::raw('COALESCE(SUM(stock_balances.quantity_on_hand),0) as total_on_hand, COALESCE(SUM(stock_balances.quantity_available),0) as total_available, COUNT(*) as total_rows'))
            ->first();

        return [
            'on_hand' => (int) ($row->total_on_hand ?? 0),
            'available' => (int) ($row->total_available ?? 0),
            'rows' => (int) ($row->total_rows ?? 0),
        ];
    }

    protected function filterLines(): array
    {
        return array_filter([
            'Warehouse' => $this->warehouseFilter !== '' ? Warehouse::whereKey($this->warehouseFilter)->value('name') : null,
            'Location' => $this->locationFilter !== '' ? Location::whereKey($this->locationFilter)->value('code') : null,
            'Category' => $this->categoryFilter !== '' ? Category::whereKey($this->categoryFilter)->value('name') : null,
            'Status' => $this->statusFilter !== '' ? StockStatus::from($this->statusFilter)->label() : null,
            'Search' => $this->search !== '' ? $this->search : null,
        ]);
    }

    protected function filterPayload(): array
    {
        return [
            'search' => $this->search,
            'warehouse' => $this->warehouseFilter,
            'location' => $this->locationFilter,
            'category' => $this->categoryFilter,
            'status' => $this->statusFilter,
            'sortField' => $this->sortField,
            'sortDirection' => $this->sortDirection,
        ];
    }

    protected function baseQuery(): QueryBuilder
    {
        $q = DB::table('stock_balances')
            ->join('items', 'stock_balances.item_id', '=', 'items.id')
            ->join('warehouses', 'stock_balances.warehouse_id', '=', 'warehouses.id')
            ->join('locations', 'stock_balances.location_id', '=', 'locations.id')
            ->join('racks', 'locations.rack_id', '=', 'racks.id')
            ->join('zones', 'racks.zone_id', '=', 'zones.id')
            ->join('categories', 'items.category_id', '=', 'categories.id')
            ->whereNull('items.deleted_at')
            ->select([
                'stock_balances.id',
                'stock_balances.quantity_on_hand',
                'stock_balances.quantity_reserved',
                'stock_balances.quantity_available',
                'items.sku',
                'items.name as item_name',
                'items.minimum_stock as min_stock',
                'items.maximum_stock as max_stock',
                'categories.id as category_id',
                'categories.name as category_name',
                'warehouses.id as warehouse_id',
                'warehouses.name as warehouse_name',
                'locations.id as location_id',
                DB::raw("CONCAT_WS(' / ', warehouses.name, zones.name, racks.name, locations.code) as location_path"),
            ])
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

    protected function sortColumn(): string
    {
        return $this->sortField === 'on_hand' ? 'stock_balances.quantity_on_hand' : 'items.sku';
    }

    public function render()
    {
        $paginator = $this->baseQuery()->orderBy($this->sortColumn(), $this->sortDirection)->paginate($this->perPage);
        $paginator->getCollection()->transform(function ($row) {
            $row->stock_status = StockStatus::evaluate((int) $row->quantity_on_hand, (int) $row->min_stock, (int) $row->max_stock);

            return $row;
        });

        return view('livewire.reports.stock-report', [
            'rows' => $paginator,
            'totals' => $this->totals(),
            'warehouses' => Warehouse::orderBy('name')->get(['id', 'name']),
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'locations' => Location::orderBy('code')->get(['id', 'code']),
            'statuses' => StockStatus::cases(),
        ]);
    }
}
