<?php

namespace App\Livewire\Reports;

use App\Exports\ExpiryExport;
use App\Livewire\Reports\Concerns\HandlesReportExport;
use App\Models\Location;
use App\Models\Warehouse;
use App\Services\Inventory\ExpiryService;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts.app')]
#[Title('Laporan Kedaluwarsa')]
class ExpiryReport extends Component
{
    use HandlesReportExport, WithPagination;

    public string $search = '';

    public string $statusFilter = 'all';

    public string $warehouseFilter = '';

    public string $locationFilter = '';

    public string $batchSearch = '';

    public string $fromDate = '';

    public string $toDate = '';

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

    public function updatedLocationFilter(): void
    {
        $this->resetPage();
    }

    public function updatedBatchSearch(): void
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
        $this->reset(['search', 'statusFilter', 'warehouseFilter', 'locationFilter', 'batchSearch', 'fromDate', 'toDate']);
        $this->statusFilter = 'all';
        $this->resetPage();
    }

    public function exportCsv(): StreamedResponse
    {
        abort_unless(auth()->user()->hasPermission('reports.export'), 403);

        return $this->csvResponse(
            'expiry-report-'.now()->format('Ymd-His').'.csv',
            ['SKU', 'Item', 'Warehouse', 'Location', 'Batch', 'Expiry Date', 'Days Left', 'Qty Received', 'Reference'],
            $this->exportRows(),
        );
    }

    public function exportExcel()
    {
        abort_unless(auth()->user()->hasPermission('reports.export'), 403);

        return Excel::download(new ExpiryExport($this->filterPayload()), 'expiry-report-'.now()->format('Ymd-His').'.xlsx');
    }

    public function exportPdf(): StreamedResponse
    {
        abort_unless(auth()->user()->hasPermission('reports.export'), 403);

        return $this->pdfResponse('reports.pdf.expiry', [
            'rows' => $this->exportRows(),
            'filters' => $this->filterLines(),
            'totals' => $this->totals(),
            'generatedAt' => now()->format('d M Y H:i'),
        ], 'expiry-report-'.now()->format('Ymd-His').'.pdf');
    }

    protected function exportRows()
    {
        return $this->baseQuery()->get()->map(fn ($r) => [
            'sku' => $r->sku,
            'item_name' => $r->item_name,
            'warehouse_name' => $r->warehouse_name,
            'location_code' => $r->location_code,
            'batch_number' => $r->batch_number,
            'expiry_date' => $r->expiry_date,
            'days_left' => (int) ($r->days_left ?? 0),
            'quantity_in' => (int) $r->quantity_in,
            'reference' => trim(($r->reference_type ?? '').' #'.($r->reference_id ?? '-')),
        ]);
    }

    protected function totals(): array
    {
        $row = $this->baseQuery()
            ->reorder()
            ->select(DB::raw('COUNT(*) as total_rows, COALESCE(SUM(stock_movements.quantity_in),0) as total_qty'))
            ->first();

        return [
            'qty' => (int) ($row->total_qty ?? 0),
            'rows' => (int) ($row->total_rows ?? 0),
        ];
    }

    protected function filterLines(): array
    {
        return array_filter([
            'Status' => $this->statusFilter !== '' && $this->statusFilter !== 'all' ? $this->statusLabel($this->statusFilter) : null,
            'Warehouse' => $this->warehouseFilter !== '' ? Warehouse::whereKey($this->warehouseFilter)->value('name') : null,
            'Location' => $this->locationFilter !== '' ? Location::whereKey($this->locationFilter)->value('code') : null,
            'Batch' => $this->batchSearch !== '' ? $this->batchSearch : null,
            'From' => $this->fromDate ?: null,
            'To' => $this->toDate ?: null,
            'Search' => $this->search !== '' ? $this->search : null,
        ]);
    }

    protected function filterPayload(): array
    {
        return [
            'search' => $this->search,
            'status' => $this->statusFilter,
            'warehouse' => $this->warehouseFilter,
            'location' => $this->locationFilter,
            'batch' => $this->batchSearch,
            'fromDate' => $this->fromDate,
            'toDate' => $this->toDate,
        ];
    }

    protected function baseQuery(): QueryBuilder
    {
        $q = ExpiryService::rows(
            $this->statusFilter !== '' ? $this->statusFilter : 'all',
            $this->search !== '' ? $this->search : null,
            $this->warehouseFilter !== '' ? (int) $this->warehouseFilter : null,
        );

        return $q
            ->when($this->locationFilter !== '', fn (QueryBuilder $query) => $query->where('stock_movements.location_id', $this->locationFilter))
            ->when($this->batchSearch !== '', function (QueryBuilder $query): void {
                $query->where('stock_movements.batch_number', 'like', '%'.$this->batchSearch.'%');
            })
            ->when($this->fromDate !== '', fn (QueryBuilder $query) => $query->whereDate('stock_movements.expiry_date', '>=', $this->fromDate))
            ->when($this->toDate !== '', fn (QueryBuilder $query) => $query->whereDate('stock_movements.expiry_date', '<=', $this->toDate));
    }

    protected function statusLabel(string $status): string
    {
        return match ($status) {
            'expired' => 'Kedaluwarsa',
            'expiring_30' => 'Segera (<= '.ExpiryService::warnDays().' hari)',
            'expiring_90' => '31-90 hari',
            'valid' => 'Valid (> 90 hari)',
            default => 'Semua',
        };
    }

    public function render()
    {
        return view('livewire.reports.expiry-report', [
            'rows' => $this->baseQuery()->paginate($this->perPage),
            'totals' => $this->totals(),
            'counts' => ExpiryService::counts(),
            'warehouses' => Warehouse::orderBy('name')->get(['id', 'name']),
            'locations' => Location::orderBy('code')->get(['id', 'code']),
        ]);
    }
}
