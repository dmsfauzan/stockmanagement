<?php

namespace App\Livewire\Reports;

use App\Exports\CogsExport;
use App\Livewire\Reports\Concerns\HandlesReportExport;
use App\Livewire\Reports\Concerns\HandlesSavedFilters;
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
#[Title('Laporan COGS')]
class CogsReport extends Component
{
    use HandlesReportExport, HandlesSavedFilters, WithPagination;

    public string $search = '';

    public string $warehouseFilter = '';

    public string $fromDate = '';

    public string $toDate = '';

    public int $perPage = 15;

    protected function filterKeys(): array
    {
        return ['search', 'warehouseFilter', 'fromDate', 'toDate'];
    }

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

    public function resetFilters(): void
    {
        $this->reset(['search', 'warehouseFilter', 'fromDate', 'toDate']);
        $this->resetPage();
    }

    public function exportCsv(): StreamedResponse
    {
        abort_unless(auth()->user()->hasPermission('reports.export'), 403);

        return $this->csvResponse(
            'cogs-report-'.now()->format('Ymd-His').'.csv',
            ['Date', 'Reference', 'SKU', 'Item', 'Warehouse', 'Qty Out', 'Unit Cost', 'Total Cost'],
            $this->exportRows(),
        );
    }

    public function exportExcel()
    {
        abort_unless(auth()->user()->hasPermission('reports.export'), 403);

        return Excel::download(new CogsExport($this->filterPayload()), 'cogs-report-'.now()->format('Ymd-His').'.xlsx');
    }

    public function exportPdf(): StreamedResponse
    {
        abort_unless(auth()->user()->hasPermission('reports.export'), 403);

        return $this->pdfResponse('reports.pdf.cogs', [
            'rows' => $this->exportRows(),
            'filters' => $this->filterLines(),
            'totals' => $this->totals(),
            'generatedAt' => now()->format('d M Y H:i'),
        ], 'cogs-report-'.now()->format('Ymd-His').'.pdf');
    }

    protected function exportRows()
    {
        return $this->baseQuery()->get()->map(fn ($r) => [
            'created_at' => $r->created_at,
            'reference' => $r->reference_type.' #'.$r->reference_id,
            'sku' => $r->sku,
            'item_name' => $r->item_name,
            'warehouse_name' => $r->warehouse_name,
            'quantity_out' => (int) $r->quantity_out,
            'unit_cost' => (float) $r->unit_cost,
            'total_cost' => (float) $r->total_cost,
        ]);
    }

    protected function totals(): array
    {
        $row = $this->baseQuery()
            ->reorder()
            ->select(DB::raw('COALESCE(SUM(stock_movements.total_cost),0) as total_cogs, COALESCE(SUM(stock_movements.quantity_out),0) as total_qty, COUNT(*) as total_rows'))
            ->first();

        return [
            'cogs' => (float) ($row->total_cogs ?? 0),
            'qty' => (int) ($row->total_qty ?? 0),
            'rows' => (int) ($row->total_rows ?? 0),
        ];
    }

    protected function filterLines(): array
    {
        return array_filter([
            'Warehouse' => $this->warehouseFilter !== '' ? Warehouse::whereKey($this->warehouseFilter)->value('name') : null,
            'From' => $this->fromDate ?: null,
            'To' => $this->toDate ?: null,
            'Search' => $this->search !== '' ? $this->search : null,
        ]);
    }

    protected function filterPayload(): array
    {
        return [
            'search' => $this->search,
            'warehouse' => $this->warehouseFilter,
            'fromDate' => $this->fromDate,
            'toDate' => $this->toDate,
        ];
    }

    protected function baseQuery(): QueryBuilder
    {
        return DB::table('stock_movements')
            ->join('items', 'stock_movements.item_id', '=', 'items.id')
            ->join('warehouses', 'stock_movements.warehouse_id', '=', 'warehouses.id')
            ->whereIn('stock_movements.transaction_type', ['outgoing', 'adjustment_out', 'transfer_out'])
            ->where('stock_movements.total_cost', '>', 0)
            ->whereNull('items.deleted_at')
            ->select([
                'stock_movements.id',
                'stock_movements.created_at',
                'stock_movements.reference_type',
                'stock_movements.reference_id',
                'items.sku',
                'items.name as item_name',
                'warehouses.name as warehouse_name',
                'warehouses.id as warehouse_id',
                'stock_movements.quantity_out',
                'stock_movements.unit_cost',
                'stock_movements.total_cost',
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
            ->when($this->fromDate !== '', fn (QueryBuilder $query) => $query->whereDate('stock_movements.created_at', '>=', $this->fromDate))
            ->when($this->toDate !== '', fn (QueryBuilder $query) => $query->whereDate('stock_movements.created_at', '<=', $this->toDate))
            ->orderBy('stock_movements.created_at', 'desc')
            ->orderByDesc('stock_movements.id');
    }

    public function render()
    {
        return view('livewire.reports.cogs-report', [
            'rows' => $this->baseQuery()->paginate($this->perPage),
            'totals' => $this->totals(),
            'warehouses' => Warehouse::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
