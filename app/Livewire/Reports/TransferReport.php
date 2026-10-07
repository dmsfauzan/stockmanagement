<?php

namespace App\Livewire\Reports;

use App\Exports\TransferExport;
use App\Livewire\Reports\Concerns\HandlesReportColumns;
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
#[Title('Laporan Transfer')]
class TransferReport extends Component
{
    use HandlesReportColumns, HandlesReportExport, HandlesSavedFilters, WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public string $fromWarehouseFilter = '';

    public string $toWarehouseFilter = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public int $perPage = 15;

    protected function filterKeys(): array
    {
        return ['search', 'statusFilter', 'fromWarehouseFilter', 'toWarehouseFilter', 'dateFrom', 'dateTo'];
    }

    public function reportColumns(): array
    {
        return [
            'number' => ['label' => 'No. Transfer'],
            'date' => ['label' => 'Tanggal'],
            'from' => ['label' => 'Dari'],
            'to' => ['label' => 'Tujuan'],
            'sku' => ['label' => 'SKU'],
            'item' => ['label' => 'Barang'],
            'quantity' => ['label' => 'Qty', 'align' => 'right'],
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

    public function updatedFromWarehouseFilter(): void
    {
        $this->resetPage();
    }

    public function updatedToWarehouseFilter(): void
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
        $this->reset(['search', 'statusFilter', 'fromWarehouseFilter', 'toWarehouseFilter', 'dateFrom', 'dateTo']);
        $this->statusFilter = '';
        $this->resetPage();
    }

    public function exportCsv(): StreamedResponse
    {
        abort_unless(auth()->user()->hasPermission('reports.export'), 403);

        return $this->csvResponse(
            'transfer-report-'.now()->format('Ymd-His').'.csv',
            ['Transfer No', 'Date', 'From (Wh/Loc)', 'To (Wh/Loc)', 'SKU', 'Item', 'Qty', 'Status'],
            $this->csvRows(),
        );
    }

    public function exportExcel()
    {
        abort_unless(auth()->user()->hasPermission('reports.export'), 403);

        return Excel::download(new TransferExport($this->filterPayload()), 'transfer-report-'.now()->format('Ymd-His').'.xlsx');
    }

    public function exportPdf(): StreamedResponse
    {
        abort_unless(auth()->user()->hasPermission('reports.export'), 403);

        return $this->pdfResponse('reports.pdf.transfer', [
            'rows' => $this->exportRows(),
            'filters' => $this->filterLines(),
            'totals' => $this->totals(),
            'generatedAt' => now()->format('d M Y H:i'),
        ], 'transfer-report-'.now()->format('Ymd-His').'.pdf');
    }

    protected function exportRows()
    {
        return $this->baseQuery()->get()->map(fn ($r) => [
            'tr_number' => $r->tr_number,
            'transfer_date' => $r->transfer_date,
            'from_warehouse' => $r->from_warehouse,
            'from_location' => $r->from_location,
            'to_warehouse' => $r->to_warehouse,
            'to_location' => $r->to_location,
            'item_sku' => $r->sku,
            'item_name' => $r->item_name,
            'quantity' => (int) $r->quantity,
            'status' => $r->status,
        ]);
    }

    protected function csvRows(): array
    {
        return $this->exportRows()->map(fn ($r) => [
            $r['tr_number'],
            $r['transfer_date'],
            $r['from_warehouse'].($r['from_location'] ? ' / '.$r['from_location'] : ''),
            $r['to_warehouse'].($r['to_location'] ? ' / '.$r['to_location'] : ''),
            $r['item_sku'],
            $r['item_name'],
            $r['quantity'],
            $r['status'],
        ])->all();
    }

    protected function totals(): array
    {
        $row = $this->baseQuery()
            ->reorder()
            ->select(DB::raw('COUNT(*) as total_rows, COALESCE(SUM(stock_transfer_items.quantity),0) as total_qty'))
            ->first();

        return [
            'rows' => (int) ($row->total_rows ?? 0),
            'qty' => (int) ($row->total_qty ?? 0),
        ];
    }

    protected function filterLines(): array
    {
        return array_filter([
            'Status' => $this->statusFilter !== '' ? $this->statusLabel($this->statusFilter) : null,
            'From Warehouse' => $this->fromWarehouseFilter !== '' ? Warehouse::whereKey($this->fromWarehouseFilter)->value('name') : null,
            'To Warehouse' => $this->toWarehouseFilter !== '' ? Warehouse::whereKey($this->toWarehouseFilter)->value('name') : null,
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
            'from_warehouse' => $this->fromWarehouseFilter,
            'to_warehouse' => $this->toWarehouseFilter,
            'from' => $this->dateFrom,
            'to' => $this->dateTo,
        ];
    }

    protected function baseQuery(): QueryBuilder
    {
        return DB::table('stock_transfers')
            ->join('stock_transfer_items', 'stock_transfer_items.stock_transfer_id', '=', 'stock_transfers.id')
            ->join('items', 'items.id', '=', 'stock_transfer_items.item_id')
            ->join('warehouses as fw', 'fw.id', '=', 'stock_transfers.from_warehouse_id')
            ->join('warehouses as tw', 'tw.id', '=', 'stock_transfers.to_warehouse_id')
            ->leftJoin('locations as fl', 'fl.id', '=', 'stock_transfers.from_location_id')
            ->leftJoin('locations as tl', 'tl.id', '=', 'stock_transfers.to_location_id')
            ->select([
                'stock_transfers.number as tr_number',
                'stock_transfers.transfer_date',
                'stock_transfers.status',
                'stock_transfers.id as transfer_id',
                'fw.name as from_warehouse',
                'fl.code as from_location',
                'tw.name as to_warehouse',
                'tl.code as to_location',
                'items.sku',
                'items.name as item_name',
                'stock_transfer_items.quantity',
            ])
            ->when($this->search !== '', function (QueryBuilder $query): void {
                $term = '%'.$this->search.'%';
                $query->where(function (QueryBuilder $inner) use ($term): void {
                    $inner->where('stock_transfers.number', 'like', $term)
                        ->orWhere('items.sku', 'like', $term)
                        ->orWhere('items.name', 'like', $term);
                });
            })
            ->when($this->statusFilter !== '', fn (QueryBuilder $query) => $query->where('stock_transfers.status', $this->statusFilter))
            ->when($this->fromWarehouseFilter !== '', fn (QueryBuilder $query) => $query->where('stock_transfers.from_warehouse_id', $this->fromWarehouseFilter))
            ->when($this->toWarehouseFilter !== '', fn (QueryBuilder $query) => $query->where('stock_transfers.to_warehouse_id', $this->toWarehouseFilter))
            ->when($this->dateFrom !== '', fn (QueryBuilder $query) => $query->whereDate('stock_transfers.transfer_date', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn (QueryBuilder $query) => $query->whereDate('stock_transfers.transfer_date', '<=', $this->dateTo))
            ->orderBy('stock_transfers.transfer_date', 'desc')
            ->orderBy('stock_transfers.number', 'desc');
    }

    protected function statusLabel(string $status): string
    {
        return match ($status) {
            'draft' => 'Draft',
            'requested' => 'Requested',
            'approved' => 'Approved',
            'in_transit' => 'In Transit',
            'received' => 'Received',
            'rejected' => 'Rejected',
            'completed' => 'Completed',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }

    public function render()
    {
        return view('livewire.reports.transfer-report', [
            'rows' => $this->baseQuery()->paginate($this->perPage),
            'totals' => $this->totals(),
            'warehouses' => Warehouse::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
