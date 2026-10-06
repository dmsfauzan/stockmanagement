<?php

namespace App\Livewire\Reports;

use App\Exports\OpnameExport;
use App\Livewire\Reports\Concerns\HandlesReportExport;
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
#[Title('Laporan Opname')]
class OpnameReport extends Component
{
    use HandlesReportExport, WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public string $warehouseFilter = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public int $perPage = 15;

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
            'opname-report-'.now()->format('Ymd-His').'.csv',
            ['Opname Number', 'Date', 'Warehouse', 'Location', 'SKU', 'Item', 'System Qty', 'Physical Qty', 'Difference', 'Reason', 'Status'],
            $this->exportRows(),
        );
    }

    public function exportExcel()
    {
        abort_unless(auth()->user()->hasPermission('reports.export'), 403);

        return Excel::download(new OpnameExport($this->filterPayload()), 'opname-report-'.now()->format('Ymd-His').'.xlsx');
    }

    public function exportPdf(): StreamedResponse
    {
        abort_unless(auth()->user()->hasPermission('reports.export'), 403);

        return $this->pdfResponse('reports.pdf.opname', [
            'rows' => $this->exportRows(),
            'filters' => $this->filterLines(),
            'totals' => $this->totals(),
            'generatedAt' => now()->format('d M Y H:i'),
        ], 'opname-report-'.now()->format('Ymd-His').'.pdf');
    }

    protected function exportRows()
    {
        return $this->baseQuery()->get()->map(fn ($r) => [
            'opname_number' => $r->opname_number,
            'opname_date' => $r->opname_date,
            'warehouse_name' => $r->warehouse_name,
            'location_code' => $r->location_code,
            'item_sku' => $r->sku,
            'item_name' => $r->item_name,
            'system_quantity' => (int) $r->system_quantity,
            'physical_quantity' => $r->physical_quantity !== null ? (int) $r->physical_quantity : null,
            'difference' => (int) $r->difference,
            'reason' => $r->reason,
            'status' => $r->status,
        ]);
    }

    protected function totals(): array
    {
        $row = $this->baseQuery()
            ->reorder()
            ->select(DB::raw('COUNT(*) as total_rows, COALESCE(SUM(stock_opname_items.difference),0) as net_diff, COALESCE(SUM(ABS(stock_opname_items.difference)),0) as abs_diff'))
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
        return DB::table('stock_opnames')
            ->join('stock_opname_items', 'stock_opname_items.stock_opname_id', '=', 'stock_opnames.id')
            ->join('items', 'items.id', '=', 'stock_opname_items.item_id')
            ->join('warehouses', 'warehouses.id', '=', 'stock_opnames.warehouse_id')
            ->leftJoin('locations', 'locations.id', '=', 'stock_opnames.location_id')
            ->select([
                'stock_opnames.number as opname_number',
                'stock_opnames.opname_date',
                'stock_opnames.status',
                'stock_opnames.id as opname_id',
                'warehouses.name as warehouse_name',
                'locations.code as location_code',
                'items.sku',
                'items.name as item_name',
                'stock_opname_items.system_quantity',
                'stock_opname_items.physical_quantity',
                'stock_opname_items.difference',
                'stock_opname_items.reason',
            ])
            ->when($this->search !== '', function (QueryBuilder $query): void {
                $term = '%'.$this->search.'%';
                $query->where(function (QueryBuilder $inner) use ($term): void {
                    $inner->where('stock_opnames.number', 'like', $term)
                        ->orWhere('items.sku', 'like', $term)
                        ->orWhere('items.name', 'like', $term);
                });
            })
            ->when($this->statusFilter !== '', fn (QueryBuilder $query) => $query->where('stock_opnames.status', $this->statusFilter))
            ->when($this->warehouseFilter !== '', fn (QueryBuilder $query) => $query->where('stock_opnames.warehouse_id', $this->warehouseFilter))
            ->when($this->dateFrom !== '', fn (QueryBuilder $query) => $query->whereDate('stock_opnames.opname_date', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn (QueryBuilder $query) => $query->whereDate('stock_opnames.opname_date', '<=', $this->dateTo))
            ->orderBy('stock_opnames.opname_date', 'desc')
            ->orderBy('stock_opnames.number', 'desc');
    }

    protected function statusLabel(string $status): string
    {
        return match ($status) {
            'draft' => 'Draft',
            'counting' => 'Counting',
            'submitted' => 'Submitted',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'completed' => 'Completed',
            default => ucfirst($status),
        };
    }

    public function render()
    {
        return view('livewire.reports.opname-report', [
            'rows' => $this->baseQuery()->paginate($this->perPage),
            'totals' => $this->totals(),
            'warehouses' => Warehouse::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
