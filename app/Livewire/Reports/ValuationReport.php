<?php

namespace App\Livewire\Reports;

use App\Exports\ValuationExport;
use App\Livewire\Reports\Concerns\HandlesReportExport;
use App\Models\Category;
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
#[Title('Laporan Valuasi')]
class ValuationReport extends Component
{
    use HandlesReportExport, WithPagination;

    public string $search = '';

    public string $warehouseFilter = '';

    public string $categoryFilter = '';

    public int $perPage = 15;

    public function mount(): void
    {
        abort_unless(auth()->user()->hasPermission('reports.view'), 403);
    }

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

    public function resetFilters(): void
    {
        $this->reset(['search', 'warehouseFilter', 'categoryFilter']);
        $this->resetPage();
    }

    public function exportCsv(): StreamedResponse
    {
        abort_unless(auth()->user()->hasPermission('reports.export'), 403);

        return $this->csvResponse(
            'valuation-report-'.now()->format('Ymd-His').'.csv',
            ['SKU', 'Item', 'Category', 'Warehouse', 'Qty', 'Average Cost', 'Total Value'],
            $this->exportRows(),
        );
    }

    public function exportExcel()
    {
        abort_unless(auth()->user()->hasPermission('reports.export'), 403);

        return Excel::download(new ValuationExport($this->filterPayload()), 'valuation-report-'.now()->format('Ymd-His').'.xlsx');
    }

    public function exportPdf(): StreamedResponse
    {
        abort_unless(auth()->user()->hasPermission('reports.export'), 403);

        return $this->pdfResponse('reports.pdf.valuation', [
            'rows' => $this->exportRows(),
            'filters' => $this->filterLines(),
            'totals' => $this->totals(),
            'generatedAt' => now()->format('d M Y H:i'),
        ], 'valuation-report-'.now()->format('Ymd-His').'.pdf');
    }

    protected function exportRows()
    {
        return $this->baseQuery()->orderBy('items.sku')->get()->map(fn ($r) => [
            'sku' => $r->sku,
            'item_name' => $r->item_name,
            'category_name' => $r->category_name,
            'warehouse_name' => $r->warehouse_name,
            'quantity' => (int) $r->quantity,
            'average_cost' => (float) $r->average_cost,
            'total_value' => (float) $r->total_value,
        ]);
    }

    protected function totals(): array
    {
        $row = $this->baseQuery()
            ->reorder()
            ->select(DB::raw('COALESCE(SUM(inventory_valuations.quantity),0) as total_qty, COALESCE(SUM(inventory_valuations.total_value),0) as total_value, COUNT(*) as total_rows'))
            ->first();

        return [
            'qty' => (int) ($row->total_qty ?? 0),
            'value' => (float) ($row->total_value ?? 0),
            'rows' => (int) ($row->total_rows ?? 0),
        ];
    }

    protected function filterLines(): array
    {
        return array_filter([
            'Warehouse' => $this->warehouseFilter !== '' ? Warehouse::whereKey($this->warehouseFilter)->value('name') : null,
            'Category' => $this->categoryFilter !== '' ? Category::whereKey($this->categoryFilter)->value('name') : null,
            'Search' => $this->search !== '' ? $this->search : null,
        ]);
    }

    protected function filterPayload(): array
    {
        return [
            'search' => $this->search,
            'warehouse' => $this->warehouseFilter,
            'category' => $this->categoryFilter,
        ];
    }

    protected function baseQuery(): QueryBuilder
    {
        return DB::table('inventory_valuations')
            ->join('items', 'inventory_valuations.item_id', '=', 'items.id')
            ->join('warehouses', 'inventory_valuations.warehouse_id', '=', 'warehouses.id')
            ->join('categories', 'items.category_id', '=', 'categories.id')
            ->whereNull('items.deleted_at')
            ->select([
                'inventory_valuations.id',
                'items.sku',
                'items.name as item_name',
                'categories.id as category_id',
                'categories.name as category_name',
                'warehouses.id as warehouse_id',
                'warehouses.name as warehouse_name',
                'inventory_valuations.quantity',
                'inventory_valuations.average_cost',
                'inventory_valuations.total_value',
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
            ->when($this->categoryFilter !== '', fn (QueryBuilder $query) => $query->where('categories.id', $this->categoryFilter));
    }

    public function render()
    {
        return view('livewire.reports.valuation-report', [
            'rows' => $this->baseQuery()->orderBy('items.sku')->paginate($this->perPage),
            'totals' => $this->totals(),
            'warehouses' => Warehouse::orderBy('name')->get(['id', 'name']),
            'categories' => Category::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
