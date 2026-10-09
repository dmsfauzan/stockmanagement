<?php

namespace App\Livewire\Reports;

use App\Exports\OutgoingExport;
use App\Livewire\Reports\Concerns\HandlesReportColumns;
use App\Livewire\Reports\Concerns\HandlesReportExport;
use App\Livewire\Reports\Concerns\HandlesSavedFilters;
use App\Models\Customer;
use App\Models\Warehouse;
use App\Services\Support\WarehouseAccess;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts.app')]
#[Title('Laporan Barang Keluar')]
class OutgoingReport extends Component
{
    use HandlesReportColumns, HandlesReportExport, HandlesSavedFilters, WithPagination;

    public string $search = '';

    public string $customerFilter = '';

    public string $warehouseFilter = '';

    public string $fromDate = '';

    public string $toDate = '';

    public int $perPage = 15;

    protected function filterKeys(): array
    {
        return ['search', 'customerFilter', 'warehouseFilter', 'fromDate', 'toDate'];
    }

    public function reportColumns(): array
    {
        return [
            'number' => ['label' => 'Issue No'],
            'date' => ['label' => 'Date'],
            'customer' => ['label' => 'Customer / Destination'],
            'warehouse' => ['label' => 'Warehouse'],
            'sku' => ['label' => 'SKU'],
            'item' => ['label' => 'Item'],
            'quantity' => ['label' => 'Qty', 'align' => 'right'],
            'posted_at' => ['label' => 'Posted At'],
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

    public function updatedCustomerFilter(): void
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
        $this->reset(['search', 'customerFilter', 'warehouseFilter', 'fromDate', 'toDate']);
        $this->resetPage();
    }

    public function exportCsv(): StreamedResponse
    {
        abort_unless(auth()->user()->hasPermission('reports.export'), 403);

        return $this->csvResponse(
            'outgoing-report-'.now()->format('Ymd-His').'.csv',
            ['Issue No', 'Date', 'Customer / Destination', 'Warehouse', 'SKU', 'Item', 'Qty', 'Posted At'],
            $this->exportRows(),
        );
    }

    public function exportExcel()
    {
        abort_unless(auth()->user()->hasPermission('reports.export'), 403);

        return Excel::download(new OutgoingExport($this->filterPayload()), 'outgoing-report-'.now()->format('Ymd-His').'.xlsx');
    }

    public function exportPdf(): StreamedResponse
    {
        abort_unless(auth()->user()->hasPermission('reports.export'), 403);

        return $this->pdfResponse('reports.pdf.outgoing', [
            'rows' => $this->exportRows(),
            'filters' => $this->filterLines(),
            'totals' => $this->totals(),
            'generatedAt' => now()->format('d M Y H:i'),
        ], 'outgoing-report-'.now()->format('Ymd-His').'.pdf');
    }

    protected function exportRows()
    {
        return $this->baseQuery()->get()->map(fn ($r) => [
            'issue_number' => $r->issue_number,
            'transaction_date' => $r->transaction_date,
            'customer_name' => $r->customer_name,
            'destination' => $r->destination,
            'warehouse_name' => $r->warehouse_name,
            'sku' => $r->sku,
            'item_name' => $r->item_name,
            'qty' => (int) $r->qty,
            'posted_at' => $r->posted_at,
        ]);
    }

    protected function totals(): array
    {
        $row = $this->baseQuery()
            ->reorder()
            ->select(DB::raw('COALESCE(SUM(goods_issue_items.quantity),0) as total_qty, COUNT(*) as total_rows'))
            ->first();

        return [
            'qty' => (int) ($row->total_qty ?? 0),
            'rows' => (int) ($row->total_rows ?? 0),
        ];
    }

    protected function filterLines(): array
    {
        return array_filter([
            'Customer' => $this->customerFilter !== '' ? Customer::whereKey($this->customerFilter)->value('name') : null,
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
            'customer' => $this->customerFilter,
            'warehouse' => $this->warehouseFilter,
            'fromDate' => $this->fromDate,
            'toDate' => $this->toDate,
        ];
    }

    protected function baseQuery(): QueryBuilder
    {
        return DB::table('goods_issue_items')
            ->forAccessibleWarehouses('goods_issues.warehouse_id')
            ->join('goods_issues', 'goods_issue_items.goods_issue_id', '=', 'goods_issues.id')
            ->join('items', 'goods_issue_items.item_id', '=', 'items.id')
            ->leftJoin('customers', 'goods_issues.customer_id', '=', 'customers.id')
            ->join('warehouses', 'goods_issues.warehouse_id', '=', 'warehouses.id')
            ->where('goods_issues.status', 'posted')
            ->select([
                'goods_issue_items.id',
                'goods_issues.number as issue_number',
                'goods_issues.transaction_date',
                'customers.name as customer_name',
                'goods_issues.destination',
                'warehouses.name as warehouse_name',
                'items.sku',
                'items.name as item_name',
                'goods_issue_items.quantity as qty',
                'goods_issues.posted_at',
            ])
            ->when($this->search !== '', function (QueryBuilder $query): void {
                $term = '%'.$this->search.'%';
                $query->where(function (QueryBuilder $inner) use ($term): void {
                    $inner->where('goods_issues.number', 'like', $term)
                        ->orWhere('items.sku', 'like', $term)
                        ->orWhere('items.name', 'like', $term);
                });
            })
            ->when($this->customerFilter !== '', fn (QueryBuilder $query) => $query->where('goods_issues.customer_id', $this->customerFilter))
            ->when($this->warehouseFilter !== '', fn (QueryBuilder $query) => $query->where('goods_issues.warehouse_id', $this->warehouseFilter))
            ->when($this->fromDate !== '', fn (QueryBuilder $query) => $query->whereDate('goods_issues.transaction_date', '>=', $this->fromDate))
            ->when($this->toDate !== '', fn (QueryBuilder $query) => $query->whereDate('goods_issues.transaction_date', '<=', $this->toDate))
            ->orderBy('goods_issues.transaction_date', 'desc')
            ->orderByDesc('goods_issue_items.id');
    }

    public function render()
    {
        return view('livewire.reports.outgoing-report', [
            'rows' => $this->baseQuery()->paginate($this->perPage),
            'totals' => $this->totals(),
            'customers' => Customer::orderBy('name')->get(['id', 'name']),
            'warehouses' => Warehouse::whereIn('id', WarehouseAccess::ids())->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
