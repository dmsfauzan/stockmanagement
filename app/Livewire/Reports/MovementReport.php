<?php

namespace App\Livewire\Reports;

use App\Enums\TransactionType;
use App\Exports\MovementExport;
use App\Livewire\Reports\Concerns\HandlesReportColumns;
use App\Livewire\Reports\Concerns\HandlesReportExport;
use App\Livewire\Reports\Concerns\HandlesSavedFilters;
use App\Models\Item;
use App\Models\User;
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
#[Title('Laporan Pergerakan Stok')]
class MovementReport extends Component
{
    use HandlesReportColumns, HandlesReportExport, HandlesSavedFilters, WithPagination;

    public string $search = '';

    public string $itemFilter = '';

    public string $warehouseFilter = '';

    public string $transactionTypeFilter = '';

    public string $userFilter = '';

    public string $fromDate = '';

    public string $toDate = '';

    public int $perPage = 15;

    protected function filterKeys(): array
    {
        return ['search', 'itemFilter', 'warehouseFilter', 'transactionTypeFilter', 'userFilter', 'fromDate', 'toDate'];
    }

    public function reportColumns(): array
    {
        return [
            'date' => ['label' => 'Tanggal'],
            'sku' => ['label' => 'SKU'],
            'item' => ['label' => 'Barang'],
            'warehouse' => ['label' => 'Warehouse'],
            'location' => ['label' => 'Lokasi'],
            'transaction_type' => ['label' => 'Tipe'],
            'quantity_in' => ['label' => 'In', 'align' => 'right'],
            'quantity_out' => ['label' => 'Out', 'align' => 'right'],
            'balance_after' => ['label' => 'Balance', 'align' => 'right'],
            'unit_cost' => ['label' => 'Hrg Satuan', 'align' => 'right'],
            'total_cost' => ['label' => 'Nilai', 'align' => 'right'],
            'user' => ['label' => 'User'],
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

    public function updatedItemFilter(): void
    {
        $this->resetPage();
    }

    public function updatedWarehouseFilter(): void
    {
        $this->resetPage();
    }

    public function updatedTransactionTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatedUserFilter(): void
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
        $this->reset(['search', 'itemFilter', 'warehouseFilter', 'transactionTypeFilter', 'userFilter', 'fromDate', 'toDate']);
        $this->resetPage();
    }

    public function exportCsv(): StreamedResponse
    {
        abort_unless(auth()->user()->hasPermission('reports.export'), 403);

        return $this->csvResponse(
            'movement-report-'.now()->format('Ymd-His').'.csv',
            ['Date', 'SKU', 'Item', 'Warehouse', 'Location', 'Type', 'Qty In', 'Qty Out', 'Balance', 'Unit Cost', 'Total Cost', 'User'],
            $this->exportRows(),
        );
    }

    public function exportExcel()
    {
        abort_unless(auth()->user()->hasPermission('reports.export'), 403);

        return Excel::download(new MovementExport($this->filterPayload()), 'movement-report-'.now()->format('Ymd-His').'.xlsx');
    }

    public function exportPdf(): StreamedResponse
    {
        abort_unless(auth()->user()->hasPermission('reports.export'), 403);

        return $this->pdfResponse('reports.pdf.movement', [
            'rows' => $this->exportRows(),
            'filters' => $this->filterLines(),
            'totals' => $this->totals(),
            'generatedAt' => now()->format('d M Y H:i'),
        ], 'movement-report-'.now()->format('Ymd-His').'.pdf');
    }

    protected function exportRows()
    {
        return $this->baseQuery()->get()->map(fn ($r) => [
            'created_at' => $r->created_at,
            'sku' => $r->sku,
            'item_name' => $r->item_name,
            'warehouse_name' => $r->warehouse_name,
            'location_code' => $r->location_code,
            'transaction_type' => $r->transaction_type,
            'quantity_in' => (int) $r->quantity_in,
            'quantity_out' => (int) $r->quantity_out,
            'balance_after' => (int) $r->balance_after,
            'unit_cost' => (float) $r->unit_cost,
            'total_cost' => (float) $r->total_cost,
            'user_name' => $r->user_name,
        ]);
    }

    protected function totals(): array
    {
        $row = $this->baseQuery()
            ->reorder()
            ->select(DB::raw('COALESCE(SUM(stock_movements.quantity_in),0) as total_in, COALESCE(SUM(stock_movements.quantity_out),0) as total_out, COUNT(*) as total_rows'))
            ->first();

        return [
            'in' => (int) ($row->total_in ?? 0),
            'out' => (int) ($row->total_out ?? 0),
            'rows' => (int) ($row->total_rows ?? 0),
        ];
    }

    protected function filterLines(): array
    {
        return array_filter([
            'Item' => $this->itemFilter !== '' ? Item::whereKey($this->itemFilter)->value('sku') : null,
            'Warehouse' => $this->warehouseFilter !== '' ? Warehouse::whereKey($this->warehouseFilter)->value('name') : null,
            'Type' => $this->transactionTypeFilter !== '' ? $this->transactionTypeFilter : null,
            'User' => $this->userFilter !== '' ? User::whereKey($this->userFilter)->value('name') : null,
            'From' => $this->fromDate ?: null,
            'To' => $this->toDate ?: null,
            'Search' => $this->search !== '' ? $this->search : null,
        ]);
    }

    protected function filterPayload(): array
    {
        return [
            'search' => $this->search,
            'item' => $this->itemFilter,
            'warehouse' => $this->warehouseFilter,
            'transaction_type' => $this->transactionTypeFilter,
            'user' => $this->userFilter,
            'fromDate' => $this->fromDate,
            'toDate' => $this->toDate,
        ];
    }

    protected function baseQuery(): QueryBuilder
    {
        return DB::table('stock_movements')
            ->forAccessibleWarehouses('stock_movements.warehouse_id')
            ->leftJoin('items', 'stock_movements.item_id', '=', 'items.id')
            ->leftJoin('warehouses', 'stock_movements.warehouse_id', '=', 'warehouses.id')
            ->leftJoin('locations', 'stock_movements.location_id', '=', 'locations.id')
            ->leftJoin('users', 'stock_movements.created_by', '=', 'users.id')
            ->select([
                'stock_movements.id',
                'stock_movements.created_at',
                'stock_movements.reference_type',
                'stock_movements.reference_id',
                'stock_movements.transaction_type',
                'stock_movements.quantity_in',
                'stock_movements.quantity_out',
                'stock_movements.balance_after',
                'stock_movements.unit_cost',
                'stock_movements.total_cost',
                'items.sku',
                'items.name as item_name',
                'warehouses.name as warehouse_name',
                'locations.code as location_code',
                'users.name as user_name',
            ])
            ->when($this->search !== '', function (QueryBuilder $query): void {
                $term = '%'.$this->search.'%';
                $query->where(function (QueryBuilder $inner) use ($term): void {
                    $inner->where('items.sku', 'like', $term)->orWhere('items.name', 'like', $term);
                });
            })
            ->when($this->itemFilter !== '', fn (QueryBuilder $query) => $query->where('stock_movements.item_id', $this->itemFilter))
            ->when($this->warehouseFilter !== '', fn (QueryBuilder $query) => $query->where('stock_movements.warehouse_id', $this->warehouseFilter))
            ->when($this->transactionTypeFilter !== '', fn (QueryBuilder $query) => $query->where('stock_movements.transaction_type', $this->transactionTypeFilter))
            ->when($this->userFilter !== '', fn (QueryBuilder $query) => $query->where('stock_movements.created_by', $this->userFilter))
            ->when($this->fromDate !== '', fn (QueryBuilder $query) => $query->whereDate('stock_movements.created_at', '>=', $this->fromDate))
            ->when($this->toDate !== '', fn (QueryBuilder $query) => $query->whereDate('stock_movements.created_at', '<=', $this->toDate))
            ->orderBy('stock_movements.created_at', 'desc')
            ->orderByDesc('stock_movements.id');
    }

    public function render()
    {
        return view('livewire.reports.movement-report', [
            'rows' => $this->baseQuery()->paginate($this->perPage),
            'totals' => $this->totals(),
            'warehouses' => Warehouse::whereIn('id', WarehouseAccess::ids())->orderBy('name')->get(['id', 'name']),
            'items' => Item::orderBy('name')->get(['id', 'sku', 'name']),
            'users' => User::orderBy('name')->get(['id', 'name']),
            'transactionTypes' => TransactionType::cases(),
        ]);
    }
}
