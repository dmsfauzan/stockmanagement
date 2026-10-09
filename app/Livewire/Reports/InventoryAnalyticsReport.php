<?php

namespace App\Livewire\Reports;

use App\Exports\ArrayExport;
use App\Livewire\Reports\Concerns\HandlesReportExport;
use App\Models\Category;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryAnalyticsService;
use App\Services\Support\WarehouseAccess;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts.app')]
#[Title('Analitik Inventori')]
class InventoryAnalyticsReport extends Component
{
    use HandlesReportExport;

    public string $tab = 'aging';

    public string $warehouseFilter = '';

    public string $categoryFilter = '';

    public string $fromDate = '';

    public string $toDate = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->hasPermission('reports.view'), 403);

        $this->fromDate = now()->subMonths(6)->format('Y-m-d');
        $this->toDate = now()->format('Y-m-d');
    }

    public function setTab(string $tab): void
    {
        if (in_array($tab, ['aging', 'abc', 'turnover', 'slow'], true)) {
            $this->tab = $tab;
        }
    }

    public function resetFilters(): void
    {
        $this->warehouseFilter = '';
        $this->categoryFilter = '';
        $this->fromDate = now()->subMonths(6)->format('Y-m-d');
        $this->toDate = now()->format('Y-m-d');
    }

    protected function warehouseId(): ?int
    {
        return $this->warehouseFilter !== '' ? (int) $this->warehouseFilter : null;
    }

    protected function categoryId(): ?int
    {
        return $this->categoryFilter !== '' ? (int) $this->categoryFilter : null;
    }

    protected function exportData(): array
    {
        return match ($this->tab) {
            'abc' => [
                ['SKU', 'Item', 'Pemakaian Qty', 'Nilai Pemakaian', 'Share %', 'Kumulatif %', 'Kelas'],
                InventoryAnalyticsService::abc($this->warehouseId(), $this->fromDate, $this->toDate)
                    ->map(fn ($r) => [$r['sku'], $r['item_name'], $r['usage_qty'], $r['usage_value'], $r['share'], $r['cumulative_share'], $r['class']])->all(),
            ],
            'slow' => [
                ['SKU', 'Item', 'Warehouse', 'Qty', 'Terakhir Keluar', 'Idle (hari)'],
                InventoryAnalyticsService::slowMoving($this->warehouseId())
                    ->map(fn ($r) => [$r['sku'], $r['item_name'], $r['warehouse_name'], $r['quantity'], $r['last_out'] ?? 'Belum pernah', $r['idle_days'] ?? '-'])->all(),
            ],
            default => [
                ['SKU', 'Item', 'Warehouse', 'Qty', 'Nilai', 'Terakhir Masuk', 'Umur (hari)', 'Bucket'],
                InventoryAnalyticsService::aging($this->warehouseId(), $this->categoryId())
                    ->map(fn ($r) => [$r['sku'], $r['item_name'], $r['warehouse_name'], $r['quantity'], $r['value'], $r['last_in'] ?? '-', $r['age_days'] ?? '-', $r['bucket']])->all(),
            ],
        };
    }

    public function exportCsv(): StreamedResponse
    {
        abort_unless(auth()->user()->hasPermission('reports.export'), 403);

        [$headings, $rows] = $this->exportData();

        return $this->csvResponse('inventory-analytics-'.$this->tab.'-'.now()->format('Ymd-His').'.csv', $headings, $rows);
    }

    public function exportExcel()
    {
        abort_unless(auth()->user()->hasPermission('reports.export'), 403);

        [$headings, $rows] = $this->exportData();

        return Excel::download(new ArrayExport($headings, $rows), 'inventory-analytics-'.$this->tab.'-'.now()->format('Ymd-His').'.xlsx');
    }

    public function render()
    {
        $data = [
            'aging' => [],
            'abc' => [],
            'slow' => [],
            'turnover' => [],
        ];

        if ($this->tab === 'aging') {
            $data['aging'] = InventoryAnalyticsService::aging($this->warehouseId(), $this->categoryId());
        } elseif ($this->tab === 'abc') {
            $data['abc'] = InventoryAnalyticsService::abc($this->warehouseId(), $this->fromDate, $this->toDate);
        } elseif ($this->tab === 'slow') {
            $data['slow'] = InventoryAnalyticsService::slowMoving($this->warehouseId());
        } else {
            $data['turnover'] = InventoryAnalyticsService::turnover($this->warehouseId(), $this->fromDate, $this->toDate);
        }

        return view('livewire.reports.inventory-analytics-report', [
            'data' => $data,
            'slowDays' => InventoryAnalyticsService::slowDays(),
            'warehouses' => Warehouse::whereIn('id', WarehouseAccess::ids())->orderBy('name')->get(['id', 'name']),
            'categories' => Category::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
