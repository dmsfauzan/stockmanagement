<?php

namespace App\Livewire\Reports;

use App\Exports\JournalExport;
use App\Livewire\Reports\Concerns\HandlesReportExport;
use App\Livewire\Reports\Concerns\HandlesSavedFilters;
use App\Models\Warehouse;
use App\Services\Accounting\JournalService;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts.app')]
#[Title('Jurnal Akuntansi')]
class JournalReport extends Component
{
    use HandlesReportExport, HandlesSavedFilters, WithPagination;

    public string $search = '';

    public string $warehouseFilter = '';

    public string $transactionType = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public int $perPage = 15;

    protected function filterKeys(): array
    {
        return ['search', 'warehouseFilter', 'transactionType', 'dateFrom', 'dateTo'];
    }

    public function mount(): void
    {
        abort_unless(auth()->user()->hasPermission('reports.view'), 403);

        $this->dateFrom = now()->subDays(30)->toDateString();
        $this->dateTo = now()->toDateString();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedWarehouseFilter(): void
    {
        $this->resetPage();
    }

    public function updatedTransactionType(): void
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
        $this->reset(['search', 'warehouseFilter', 'transactionType']);
        $this->dateFrom = now()->subDays(30)->toDateString();
        $this->dateTo = now()->toDateString();
        $this->resetPage();
    }

    public function exportCsv(): StreamedResponse
    {
        abort_unless(auth()->user()->hasPermission('reports.export'), 403);

        return $this->csvResponse(
            'journal-report-'.now()->format('Ymd-His').'.csv',
            ['Date', 'Reference', 'Type', 'SKU', 'Item', 'Warehouse', 'Qty', 'Unit Cost', 'Total', 'Debit Account', 'Credit Account', 'Description'],
            $this->exportRows(),
        );
    }

    public function exportExcel()
    {
        abort_unless(auth()->user()->hasPermission('reports.export'), 403);

        return Excel::download(new JournalExport($this->filterPayload()), 'journal-report-'.now()->format('Ymd-His').'.xlsx');
    }

    public function exportPdf(): StreamedResponse
    {
        abort_unless(auth()->user()->hasPermission('reports.export'), 403);

        return $this->pdfResponse('reports.pdf.journal', [
            'rows' => $this->exportRows(),
            'filters' => $this->filterLines(),
            'totals' => $this->totals(),
            'generatedAt' => now()->format('d M Y H:i'),
        ], 'journal-report-'.now()->format('Ymd-His').'.pdf');
    }

    protected function exportRows()
    {
        return $this->baseQuery()->get()->map(function ($r): array {
            [$debit, $credit] = JournalService::mapping((string) $r->transaction_type);
            $qty = (int) $r->quantity_in > 0 ? (int) $r->quantity_in : (int) $r->quantity_out;

            return [
                'created_at' => $r->created_at,
                'reference' => $r->reference_type.' #'.$r->reference_id,
                'transaction_type' => $r->transaction_type,
                'sku' => $r->sku,
                'item_name' => $r->item_name,
                'warehouse_name' => $r->warehouse_name,
                'quantity' => $qty,
                'unit_cost' => (float) $r->unit_cost,
                'total_cost' => (float) $r->total_cost,
                'debit_account' => $debit,
                'credit_account' => $credit,
                'description' => $r->notes ?? '',
            ];
        });
    }

    protected function totals(): array
    {
        $row = $this->baseQuery()
            ->reorder()
            ->select(DB::raw('COALESCE(SUM(stock_movements.total_cost),0) as total_amount, COUNT(*) as total_rows'))
            ->first();

        $amount = (float) ($row->total_amount ?? 0);

        return [
            'debits' => $amount,
            'credits' => $amount,
            'net' => 0.0,
            'rows' => (int) ($row->total_rows ?? 0),
        ];
    }

    protected function filterLines(): array
    {
        return array_filter([
            'Warehouse' => $this->warehouseFilter !== '' ? Warehouse::whereKey($this->warehouseFilter)->value('name') : null,
            'Type' => $this->transactionType !== '' ? $this->transactionType : null,
            'From' => $this->dateFrom ?: null,
            'To' => $this->dateTo ?: null,
            'Search' => $this->search !== '' ? $this->search : null,
        ]);
    }

    protected function filterPayload(): array
    {
        return [
            'search' => $this->search,
            'warehouse' => $this->warehouseFilter,
            'transactionType' => $this->transactionType,
            'fromDate' => $this->dateFrom,
            'toDate' => $this->dateTo,
        ];
    }

    protected function baseQuery(): QueryBuilder
    {
        return DB::table('stock_movements')
            ->join('items', 'stock_movements.item_id', '=', 'items.id')
            ->join('warehouses', 'stock_movements.warehouse_id', '=', 'warehouses.id')
            ->whereNull('items.deleted_at')
            ->select([
                'stock_movements.id',
                'stock_movements.created_at',
                'stock_movements.reference_type',
                'stock_movements.reference_id',
                'stock_movements.transaction_type',
                'stock_movements.quantity_in',
                'stock_movements.quantity_out',
                'stock_movements.unit_cost',
                'stock_movements.total_cost',
                'stock_movements.notes',
                'items.sku',
                'items.name as item_name',
                'warehouses.name as warehouse_name',
                'warehouses.id as warehouse_id',
            ])
            ->when($this->search !== '', function (QueryBuilder $query): void {
                $term = '%'.$this->search.'%';
                $query->where(function (QueryBuilder $inner) use ($term): void {
                    $inner->where('items.sku', 'like', $term)
                        ->orWhere('items.name', 'like', $term)
                        ->orWhere('stock_movements.reference_type', 'like', $term)
                        ->orWhere('stock_movements.notes', 'like', $term);
                });
            })
            ->when($this->warehouseFilter !== '', fn (QueryBuilder $query) => $query->where('warehouses.id', $this->warehouseFilter))
            ->when($this->transactionType !== '', fn (QueryBuilder $query) => $query->where('stock_movements.transaction_type', $this->transactionType))
            ->when($this->dateFrom !== '', fn (QueryBuilder $query) => $query->whereDate('stock_movements.created_at', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn (QueryBuilder $query) => $query->whereDate('stock_movements.created_at', '<=', $this->dateTo))
            ->orderBy('stock_movements.created_at', 'desc')
            ->orderByDesc('stock_movements.id');
    }

    public function render()
    {
        return view('livewire.reports.journal-report', [
            'rows' => $this->baseQuery()->paginate($this->perPage),
            'totals' => $this->totals(),
            'warehouses' => Warehouse::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
