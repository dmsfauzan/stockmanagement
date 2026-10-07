<?php

namespace App\Livewire\Reports;

use App\Livewire\Reports\Concerns\HandlesReportColumns;
use App\Livewire\Reports\Concerns\HandlesReportExport;
use App\Livewire\Reports\Concerns\HandlesSavedFilters;
use App\Models\Category;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierItemPrice;
use App\Models\Warehouse;
use App\Services\Inventory\ReplenishmentService;
use App\Services\Support\AuditLogger;
use App\Services\Support\DocumentNumberService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts.app')]
#[Title('Replenishment')]
class ReplenishmentReport extends Component
{
    use HandlesReportColumns, HandlesReportExport, HandlesSavedFilters, WithPagination;

    public string $search = '';

    public string $warehouseFilter = '';

    public string $categoryFilter = '';

    public int $perPage = 15;

    public array $selected = [];

    public string $supplierForPo = '';

    public bool $selectAll = false;

    protected function filterKeys(): array
    {
        return ['search', 'warehouseFilter', 'categoryFilter'];
    }

    public function reportColumns(): array
    {
        return [
            'sku' => ['label' => 'SKU'],
            'item' => ['label' => 'Barang'],
            'category' => ['label' => 'Kategori'],
            'warehouse' => ['label' => 'Warehouse'],
            'on_hand' => ['label' => 'On Hand', 'align' => 'right'],
            'min' => ['label' => 'Min', 'align' => 'right'],
            'max' => ['label' => 'Max', 'align' => 'right'],
            'suggested' => ['label' => 'Suggested', 'align' => 'right'],
            'supplier' => ['label' => 'Primary Supplier'],
            'price' => ['label' => 'Price', 'align' => 'right'],
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

    public function updatedWarehouseFilter(): void
    {
        $this->resetPage();
    }

    public function updatedCategoryFilter(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function updatedSelected(): void
    {
        $keys = $this->paginatedRows()->pluck('key')->all();
        $this->selectAll = $keys !== [] && empty(array_diff($keys, $this->selected));
    }

    public function updatedSelectAll(bool $value): void
    {
        $keys = $this->paginatedRows()->pluck('key')->all();

        if ($value) {
            $this->selected = array_values(array_unique(array_merge($this->selected, $keys)));
        } else {
            $this->selected = array_values(array_diff($this->selected, $keys));
        }
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'warehouseFilter', 'categoryFilter', 'selected', 'selectAll', 'supplierForPo']);
        $this->resetPage();
    }

    protected function baseRows()
    {
        return ReplenishmentService::suggestions(
            $this->warehouseFilter !== '' ? (int) $this->warehouseFilter : null,
            $this->search !== '' ? $this->search : null,
            $this->categoryFilter !== '' ? (int) $this->categoryFilter : null,
        )->map(fn ($row) => $row + ['key' => $row['item_id'].':'.$row['warehouse_id']]);
    }

    protected function paginatedRows(): LengthAwarePaginator
    {
        $all = $this->baseRows();
        $page = $this->getPage();
        $total = $all->count();
        $items = $all->forPage($page, $this->perPage)->values();

        return new LengthAwarePaginator($items, $total, $this->perPage, $page, [
            'path' => request()->url(),
            'pageName' => 'page',
        ]);
    }

    public function createPoFromSelection()
    {
        $this->authorize('create', PurchaseOrder::class);

        $this->validate([
            'selected' => ['required', 'array', 'min:1'],
        ], [
            'selected.required' => 'Pilih minimal satu baris.',
        ]);

        $rows = $this->baseRows()->keyBy('key');
        $lines = collect($this->selected)
            ->map(fn ($key) => $rows->get($key))
            ->filter()
            ->values();

        if ($lines->isEmpty()) {
            $this->dispatch('toast', type: 'error', message: 'Baris yang dipilih tidak valid.');

            return null;
        }

        $supplierId = $this->supplierForPo !== '' ? (int) $this->supplierForPo : null;

        if (! $supplierId) {
            $counts = $lines->pluck('primary_supplier_id')->filter()->countBy()->sortDesc();
            $supplierId = $counts->keys()->first()
                ?? $lines->first()['primary_supplier_id']
                ?? Supplier::where('status', 'active')->orderBy('name')->value('id');
        }

        if (! $supplierId) {
            $this->dispatch('toast', type: 'error', message: 'Tidak ada supplier aktif.');

            return null;
        }

        $warehouseId = $this->warehouseFilter !== ''
            ? (int) $this->warehouseFilter
            : ((int) $lines->first()['warehouse_id'] ?: Warehouse::orderBy('name')->value('id'));

        $po = DB::transaction(function () use ($lines, $supplierId, $warehouseId): PurchaseOrder {
            $order = PurchaseOrder::create([
                'number' => DocumentNumberService::generate('PO'),
                'order_date' => today(),
                'supplier_id' => $supplierId,
                'warehouse_id' => $warehouseId,
                'status' => 'draft',
                'created_by' => auth()->id(),
            ]);

            foreach ($lines as $line) {
                $item = Item::find($line['item_id']);

                if (! $item) {
                    continue;
                }

                $price = SupplierItemPrice::where('supplier_id', $supplierId)
                    ->where('item_id', $item->id)
                    ->value('price') ?? $item->cost ?? 0;

                $order->items()->create([
                    'item_id' => $item->id,
                    'quantity' => max(1, (int) $line['suggestedQty']),
                    'received_quantity' => 0,
                    'unit_id' => $item->unit_id,
                    'unit_price' => $price,
                ]);
            }

            AuditLogger::logModel('create', $order, null, $order->fresh('items')->toArray());

            return $order;
        });

        $this->reset(['selected', 'selectAll']);
        $this->dispatch('toast', type: 'success', message: 'PO '.$po->number.' dibuat.');

        return $this->redirect(route('purchase-orders.show', $po), navigate: true);
    }

    public function exportCsv(): StreamedResponse
    {
        abort_unless(auth()->user()->hasPermission('reports.export'), 403);

        $rows = $this->baseRows();

        return $this->csvResponse(
            'replenishment-'.now()->format('Ymd-His').'.csv',
            ['SKU', 'Item', 'Category', 'Warehouse', 'On Hand', 'Min', 'Max', 'Suggested', 'Primary Supplier'],
            $rows->map(fn ($r) => [
                $r['sku'],
                $r['item_name'],
                $r['category_name'],
                $r['warehouse_name'],
                $r['on_hand'],
                $r['min_stock'],
                $r['max_stock'],
                $r['suggestedQty'],
                $r['primary_supplier_name'],
            ])->all(),
        );
    }

    public function render()
    {
        $rows = $this->paginatedRows();
        $all = $this->baseRows();

        return view('livewire.reports.replenishment-report', [
            'rows' => $rows,
            'warehouses' => Warehouse::orderBy('name')->get(['id', 'name']),
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'suppliers' => Supplier::where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'totals' => [
                'rows' => $all->count(),
                'suggested' => (int) $all->sum('suggestedQty'),
            ],
        ]);
    }
}
