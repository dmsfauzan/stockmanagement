<?php

namespace App\Livewire\Transactions;

use App\Enums\OpnameStatus;
use App\Models\StockOpname;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts.app')]
#[Title('Stock Opname')]
class StockOpnameIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public string $warehouseFilter = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public string $sortDirection = 'desc';

    public int $perPage = 10;

    public function mount(): void
    {
        $this->authorize('viewAny', StockOpname::class);
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

    public function sortByDate(): void
    {
        $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        $this->resetPage();
    }

    protected function baseQuery(): Builder
    {
        return StockOpname::query()
            ->with(['warehouse', 'location', 'creator'])
            ->withCount('items')
            ->when($this->search !== '', function (Builder $query): void {
                $term = '%'.$this->search.'%';
                $query->where(function (Builder $inner) use ($term): void {
                    $inner->where('number', 'like', $term)
                        ->orWhere('notes', 'like', $term);
                });
            })
            ->when($this->statusFilter !== '', fn (Builder $query) => $query->where('status', $this->statusFilter))
            ->when($this->warehouseFilter !== '', fn (Builder $query) => $query->where('warehouse_id', $this->warehouseFilter))
            ->when($this->dateFrom !== '', fn (Builder $query) => $query->whereDate('opname_date', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn (Builder $query) => $query->whereDate('opname_date', '<=', $this->dateTo));
    }

    public function export(): StreamedResponse
    {
        $this->authorize('viewAny', StockOpname::class);

        $rows = $this->baseQuery()->orderBy('opname_date', $this->sortDirection)->orderByDesc('id')->get();

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Number', 'Date', 'Warehouse', 'Location', 'Items', 'Status']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->number,
                    $row->opname_date?->format('Y-m-d'),
                    $row->warehouse?->name,
                    $row->location?->fullPath() ?? $row->location?->code,
                    $row->items_count,
                    $row->status,
                ]);
            }

            fclose($handle);
        }, 'stock-opnames-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function render()
    {
        return view('livewire.transactions.stock-opname-index', [
            'opnames' => $this->baseQuery()
                ->orderBy('opname_date', $this->sortDirection)
                ->orderByDesc('id')
                ->paginate($this->perPage),
            'warehouses' => Warehouse::orderBy('name')->get(['id', 'name']),
            'statuses' => OpnameStatus::cases(),
        ]);
    }
}
