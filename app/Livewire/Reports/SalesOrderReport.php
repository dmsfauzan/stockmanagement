<?php

namespace App\Livewire\Reports;

use App\Enums\SalesOrderStatus;
use App\Exports\SalesOrderExport;
use App\Livewire\Reports\Concerns\HandlesReportColumns;
use App\Livewire\Reports\Concerns\HandlesReportExport;
use App\Livewire\Reports\Concerns\HandlesSavedFilters;
use App\Models\Customer;
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
#[Title('Laporan Sales Order')]
class SalesOrderReport extends Component
{
    use HandlesReportColumns, HandlesReportExport, HandlesSavedFilters, WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public string $customerFilter = '';

    public string $warehouseFilter = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public int $perPage = 15;

    protected function filterKeys(): array
    {
        return ['search', 'statusFilter', 'customerFilter', 'warehouseFilter', 'dateFrom', 'dateTo'];
    }

    public function reportColumns(): array
    {
        return [
            'number' => ['label' => 'No. SO'],
            'date' => ['label' => 'Tanggal'],
            'customer' => ['label' => 'Customer'],
            'warehouse' => ['label' => 'Warehouse'],
            'sku' => ['label' => 'SKU'],
            'item' => ['label' => 'Barang'],
            'quantity' => ['label' => 'Qty', 'align' => 'right'],
            'fulfilled' => ['label' => 'Terpenuhi', 'align' => 'right'],
            'remaining' => ['label' => 'Sisa', 'align' => 'right'],
            'unit_price' => ['label' => 'Harga', 'align' => 'right'],
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

    public function updatedStatusFilter(): void
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
        $this->reset(['search', 'statusFilter', 'customerFilter', 'warehouseFilter', 'dateFrom', 'dateTo']);
        $this->statusFilter = '';
        $this->resetPage();
    }

    public function exportCsv(): StreamedResponse
    {
        abort_unless(auth()->user()->hasPermission('reports.export'), 403);

        return $this->csvResponse(
            'sales-order-report-'.now()->format('Ymd-His').'.csv',
            ['SO Number', 'Date', 'Customer', 'Warehouse', 'SKU', 'Item', 'Quantity', 'Fulfilled', 'Remaining', 'Unit Price', 'Status'],
            $this->exportRows(),
        );
    }

    public function exportExcel()
    {
        abort_unless(auth()->user()->hasPermission('reports.export'), 403);

        return Excel::download(new SalesOrderExport($this->filterPayload()), 'sales-order-report-'.now()->format('Ymd-His').'.xlsx');
    }

    public function exportPdf(): StreamedResponse
    {
        abort_unless(auth()->user()->hasPermission('reports.export'), 403);

        return $this->pdfResponse('reports.pdf.sales-order', [
            'rows' => $this->exportRows(),
            'filters' => $this->filterLines(),
            'totals' => $this->totals(),
            'generatedAt' => now()->format('d M Y H:i'),
        ], 'sales-order-report-'.now()->format('Ymd-His').'.pdf');
    }

    protected function exportRows()
    {
        return $this->baseQuery()->get()->map(fn ($r) => [
            'so_number' => $r->so_number,
            'order_date' => $r->order_date,
            'customer_name' => $r->customer_name,
            'warehouse_name' => $r->warehouse_name,
            'item_sku' => $r->sku,
            'item_name' => $r->item_name,
            'quantity' => (int) $r->quantity,
            'fulfilled_quantity' => (int) $r->fulfilled_quantity,
            'remaining' => max(0, (int) $r->quantity - (int) $r->fulfilled_quantity),
            'unit_price' => $r->unit_price,
            'status' => $r->status,
        ]);
    }

    protected function totals(): array
    {
        $row = $this->baseQuery()
            ->reorder()
            ->select(DB::raw('COUNT(*) as total_rows, COALESCE(SUM(sales_order_items.quantity),0) as total_quantity, COALESCE(SUM(sales_order_items.fulfilled_quantity),0) as total_fulfilled'))
            ->first();

        return [
            'rows' => (int) ($row->total_rows ?? 0),
            'quantity' => (int) ($row->total_quantity ?? 0),
            'fulfilled' => (int) ($row->total_fulfilled ?? 0),
        ];
    }

    protected function filterLines(): array
    {
        return array_filter([
            'Status' => $this->statusFilter !== '' ? $this->statusLabel($this->statusFilter) : null,
            'Customer' => $this->customerFilter !== '' ? Customer::whereKey($this->customerFilter)->value('name') : null,
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
            'customer' => $this->customerFilter,
            'warehouse' => $this->warehouseFilter,
            'from' => $this->dateFrom,
            'to' => $this->dateTo,
        ];
    }

    protected function baseQuery(): QueryBuilder
    {
        return DB::table('sales_orders')
            ->join('sales_order_items', 'sales_order_items.sales_order_id', '=', 'sales_orders.id')
            ->join('items', 'items.id', '=', 'sales_order_items.item_id')
            ->join('customers', 'customers.id', '=', 'sales_orders.customer_id')
            ->join('warehouses', 'warehouses.id', '=', 'sales_orders.warehouse_id')
            ->select([
                'sales_orders.number as so_number',
                'sales_orders.order_date',
                'sales_orders.status',
                'sales_orders.id as sales_order_id',
                'customers.name as customer_name',
                'warehouses.name as warehouse_name',
                'items.sku',
                'items.name as item_name',
                'sales_order_items.quantity',
                'sales_order_items.fulfilled_quantity',
                'sales_order_items.unit_price',
            ])
            ->when($this->search !== '', function (QueryBuilder $query): void {
                $term = '%'.$this->search.'%';
                $query->where(function (QueryBuilder $inner) use ($term): void {
                    $inner->where('sales_orders.number', 'like', $term)
                        ->orWhere('customers.name', 'like', $term);
                });
            })
            ->when($this->statusFilter !== '', fn (QueryBuilder $query) => $query->where('sales_orders.status', $this->statusFilter))
            ->when($this->customerFilter !== '', fn (QueryBuilder $query) => $query->where('sales_orders.customer_id', $this->customerFilter))
            ->when($this->warehouseFilter !== '', fn (QueryBuilder $query) => $query->where('sales_orders.warehouse_id', $this->warehouseFilter))
            ->when($this->dateFrom !== '', fn (QueryBuilder $query) => $query->whereDate('sales_orders.order_date', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn (QueryBuilder $query) => $query->whereDate('sales_orders.order_date', '<=', $this->dateTo))
            ->orderBy('sales_orders.order_date', 'desc')
            ->orderBy('sales_orders.number', 'desc');
    }

    protected function statusLabel(string $status): string
    {
        return SalesOrderStatus::tryFrom($status)?->label() ?? ucfirst($status);
    }

    public function render()
    {
        return view('livewire.reports.sales-order-report', [
            'rows' => $this->baseQuery()->paginate($this->perPage),
            'totals' => $this->totals(),
            'customers' => Customer::orderBy('name')->get(['id', 'name']),
            'warehouses' => Warehouse::orderBy('name')->get(['id', 'name']),
            'statuses' => SalesOrderStatus::cases(),
        ]);
    }
}
