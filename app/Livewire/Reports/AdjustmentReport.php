<?php

namespace App\Livewire\Reports;

use App\Exports\AdjustmentExport;
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
#[Title('Laporan Adjustment')]
class AdjustmentReport extends Component
{
    use HandlesReportExport, HandlesSavedFilters, WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public string $warehouseFilter = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public int $perPage = 15;

    protected function filterKeys(): array
    {
        return ['search', 'statusFilter', 'warehouseFilter', 'dateFrom', 'dateTo'];
    }

    public function mount(): void
    {
        abort_unless(auth()->user()->hasPermission('reports.view'), 403);
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

    public function resetFilters(): void
    {
        $this->reset(['search', 'statusFilter', 'warehouseFilter', 'dateFrom', 'dateTo']);
        $this->statusFilter = '';
        $this->resetPage();
    }

    public function exportCsv(): StreamedResponse
    {
        abort_unless(auth()->user()->hasPermission('reports.export'), 403);

        return $this->csvResponse(
            'adjustment-report-'.now()->format('Ymd-His').'.csv',
            ['Adjustment No', 'Date', 'Warehouse', 'Location', 'SKU', 'Item', 'System Qty', 'Actual Qty', 'Difference', 'Status'],
            $this->exportRows(),
        );
    }

    public function exportExcel()
    {
        abort_unless(auth()->user()->hasPermission('reports.export'), 403);

        return Excel::download(new AdjustmentExport($this->filterPayload()), 'adjustment-report-'.now()->format('Ymd-His').'.xlsx');
    }

    public function exportPdf(): StreamedResponse
    {
        abort_unless(auth()->user()->hasPermission('reports.export'), 403);

        return $this->pdfResponse('reports.pdf.adjustment', [
            'rows' => $this->exportRows(),
            'filters' => $this->filterLines(),
            'totals' => $this->totals(),
            'generatedAt' => now()->format('d M Y H:i'),
        ], 'adjustment-report-'.now()->format('Ymd-His').'.pdf');
    }

    protected function exportRows()
    {
        return $this->baseQuery()->get()->map(fn ($r) => [
            'adj_number' => $r->adj_number,
            'transaction_date' => $r->transaction_date,
            'warehouse_name' => $r->warehouse_name,
            'location_code' => $r->location_code,
            'item_sku' => $r->sku,
            'item_name' => $r->item_name,
            'system_quantity' => (int) $r->system_quantity,
            'actual_quantity' => (int) $r->actual_quantity,
            'difference' => (int) $r->difference,
            'status' => $r->status,
        ]);
    }

    protected function totals(): array
    {
        $row = $this->baseQuery()
            ->reorder()
            ->select(DB::raw('COUNT(*) as total_rows, COALESCE(SUM(stock_adjustment_items.difference),0) as net_diff, COALESCE(SUM(ABS(stock_adjustment_items.difference)),0) as abs_diff'))
            ->first();

        return [
            'rows' => (int) ($row->total_rows ?? 0),
            'net' => (int) ($row->net_diff ?? 0),
            'abs' => (int) ($row->abs_diff ?? 0),
        ];
    }

    protected function filterLines(): array
    {
        return array_filter([
            'Status' => $this->statusFilter !== '' ? $this->statusLabel($this->statusFilter) : null,
            'Warehouse' => $this->warehouseFilter !== '' ? Warehouse::whereKey($this->warehouseFilter)->value('name') : null,
            'From' => $this->dateFrom ?: null,
            'To' => $this->dateTo ?: null,
            'Search' => $this->search !== '' ? $this->search : null,
        ]);
    }

    protected function filterPayload(): array
    {
        return [
            'search' => $this->search,
            'status' => $this->statusFilter,
            'warehouse' => $this->warehouseFilter,
            'from' => $this->dateFrom,
            'to' => $this->dateTo,
        ];
    }

    protected function baseQuery(): QueryBuilder
    {
        return DB::table('stock_adjustments')
            ->join('stock_adjustment_items', 'stock_adjustment_items.stock_adjustment_id', '=', 'stock_adjustments.id')
            ->join('items', 'items.id', '=', 'stock_adjustment_items.item_id')
            ->join('warehouses', 'warehouses.id', '=', 'stock_adjustments.warehouse_id')
            ->leftJoin('locations', 'locations.id', '=', 'stock_adjustments.location_id')
            ->select([
                'stock_adjustments.number as adj_number',
                'stock_adjustments.transaction_date',
                'stock_adjustments.status',
                'stock_adjustments.id as adjustment_id',
                'warehouses.name as warehouse_name',
                'locations.code as location_code',
                'items.sku',
                'items.name as item_name',
                'stock_adjustment_items.system_quantity',
                'stock_adjustment_items.actual_quantity',
                'stock_adjustment_items.difference',
                'stock_adjustments.reason',
            ])
            ->when($this->search !== '', function (QueryBuilder $query): void {
                $term = '%'.$this->search.'%';
                $query->where(function (QueryBuilder $inner) use ($term): void {
                    $inner->where('stock_adjustments.number', 'like', $term)
                        ->orWhere('stock_adjustments.reason', 'like', $term)
                        ->orWhere('items.sku', 'like', $term)
                        ->orWhere('items.name', 'like', $term);
                });
            })
            ->when($this->statusFilter !== '', fn (QueryBuilder $query) => $query->where('stock_adjustments.status', $this->statusFilter))
            ->when($this->warehouseFilter !== '', fn (QueryBuilder $query) => $query->where('stock_adjustments.warehouse_id', $this->warehouseFilter))
            ->when($this->dateFrom !== '', fn (QueryBuilder $query) => $query->whereDate('stock_adjustments.transaction_date', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn (QueryBuilder $query) => $query->whereDate('stock_adjustments.transaction_date', '<=', $this->dateTo))
            ->orderBy('stock_adjustments.transaction_date', 'desc')
            ->orderBy('stock_adjustments.number', 'desc');
    }

    protected function statusLabel(string $status): string
    {
        return match ($status) {
            'draft' => 'Draft',
            'submitted' => 'Submitted',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'posted' => 'Posted',
            default => ucfirst($status),
        };
    }

    public function render()
    {
        return view('livewire.reports.adjustment-report', [
            'rows' => $this->baseQuery()->paginate($this->perPage),
            'totals' => $this->totals(),
            'warehouses' => Warehouse::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
