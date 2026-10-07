<?php

namespace App\Livewire\Reports;

use App\Exports\IncomingExport;
use App\Livewire\Reports\Concerns\HandlesReportExport;
use App\Livewire\Reports\Concerns\HandlesSavedFilters;
use App\Models\Supplier;
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
#[Title('Laporan Barang Masuk')]
class IncomingReport extends Component
{
    use HandlesReportExport, HandlesSavedFilters, WithPagination;

    public string $search = '';

    public string $supplierFilter = '';

    public string $warehouseFilter = '';

    public string $fromDate = '';

    public string $toDate = '';

    public int $perPage = 15;

    protected function filterKeys(): array
    {
        return ['search', 'supplierFilter', 'warehouseFilter', 'fromDate', 'toDate'];
    }

    public function mount(): void
    {
        abort_unless(auth()->user()->hasPermission('reports.view'), 403);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedSupplierFilter(): void
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
        $this->reset(['search', 'supplierFilter', 'warehouseFilter', 'fromDate', 'toDate']);
        $this->resetPage();
    }

    public function exportCsv(): StreamedResponse
    {
        abort_unless(auth()->user()->hasPermission('reports.export'), 403);

        return $this->csvResponse(
            'incoming-report-'.now()->format('Ymd-His').'.csv',
            ['Receipt No', 'Date', 'Supplier', 'Warehouse', 'SKU', 'Item', 'Qty', 'Batch', 'Expiry', 'Posted At'],
            $this->exportRows(),
        );
    }

    public function exportExcel()
    {
        abort_unless(auth()->user()->hasPermission('reports.export'), 403);

        return Excel::download(new IncomingExport($this->filterPayload()), 'incoming-report-'.now()->format('Ymd-His').'.xlsx');
    }

    public function exportPdf(): StreamedResponse
    {
        abort_unless(auth()->user()->hasPermission('reports.export'), 403);

        return $this->pdfResponse('reports.pdf.incoming', [
            'rows' => $this->exportRows(),
            'filters' => $this->filterLines(),
            'totals' => $this->totals(),
            'generatedAt' => now()->format('d M Y H:i'),
        ], 'incoming-report-'.now()->format('Ymd-His').'.pdf');
    }

    protected function exportRows()
    {
        return $this->baseQuery()->get()->map(fn ($r) => [
            'receipt_number' => $r->receipt_number,
            'transaction_date' => $r->transaction_date,
            'supplier_name' => $r->supplier_name,
            'warehouse_name' => $r->warehouse_name,
            'sku' => $r->sku,
            'item_name' => $r->item_name,
            'qty' => (int) $r->qty,
            'batch_number' => $r->batch_number,
            'expiry_date' => $r->expiry_date,
            'posted_at' => $r->posted_at,
        ]);
    }

    protected function totals(): array
    {
        $row = $this->baseQuery()
            ->reorder()
            ->select(DB::raw('COALESCE(SUM(goods_receipt_items.quantity),0) as total_qty, COUNT(*) as total_rows'))
            ->first();

        return [
            'qty' => (int) ($row->total_qty ?? 0),
            'rows' => (int) ($row->total_rows ?? 0),
        ];
    }

    protected function filterLines(): array
    {
        return array_filter([
            'Supplier' => $this->supplierFilter !== '' ? Supplier::whereKey($this->supplierFilter)->value('name') : null,
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
            'supplier' => $this->supplierFilter,
            'warehouse' => $this->warehouseFilter,
            'fromDate' => $this->fromDate,
            'toDate' => $this->toDate,
        ];
    }

    protected function baseQuery(): QueryBuilder
    {
        return DB::table('goods_receipt_items')
            ->join('goods_receipts', 'goods_receipt_items.goods_receipt_id', '=', 'goods_receipts.id')
            ->join('items', 'goods_receipt_items.item_id', '=', 'items.id')
            ->leftJoin('suppliers', 'goods_receipts.supplier_id', '=', 'suppliers.id')
            ->join('warehouses', 'goods_receipts.warehouse_id', '=', 'warehouses.id')
            ->where('goods_receipts.status', 'posted')
            ->select([
                'goods_receipt_items.id',
                'goods_receipts.number as receipt_number',
                'goods_receipts.transaction_date',
                'suppliers.name as supplier_name',
                'warehouses.name as warehouse_name',
                'items.sku',
                'items.name as item_name',
                'goods_receipt_items.quantity as qty',
                'goods_receipt_items.batch_number',
                'goods_receipt_items.expiry_date',
                'goods_receipts.posted_at',
            ])
            ->when($this->search !== '', function (QueryBuilder $query): void {
                $term = '%'.$this->search.'%';
                $query->where(function (QueryBuilder $inner) use ($term): void {
                    $inner->where('goods_receipts.number', 'like', $term)
                        ->orWhere('items.sku', 'like', $term)
                        ->orWhere('items.name', 'like', $term);
                });
            })
            ->when($this->supplierFilter !== '', fn (QueryBuilder $query) => $query->where('goods_receipts.supplier_id', $this->supplierFilter))
            ->when($this->warehouseFilter !== '', fn (QueryBuilder $query) => $query->where('goods_receipts.warehouse_id', $this->warehouseFilter))
            ->when($this->fromDate !== '', fn (QueryBuilder $query) => $query->whereDate('goods_receipts.transaction_date', '>=', $this->fromDate))
            ->when($this->toDate !== '', fn (QueryBuilder $query) => $query->whereDate('goods_receipts.transaction_date', '<=', $this->toDate))
            ->orderBy('goods_receipts.transaction_date', 'desc')
            ->orderByDesc('goods_receipt_items.id');
    }

    public function render()
    {
        return view('livewire.reports.incoming-report', [
            'rows' => $this->baseQuery()->paginate($this->perPage),
            'totals' => $this->totals(),
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
            'warehouses' => Warehouse::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
