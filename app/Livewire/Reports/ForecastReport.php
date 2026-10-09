<?php

namespace App\Livewire\Reports;

use App\Exports\ArrayExport;
use App\Livewire\Reports\Concerns\HandlesReportExport;
use App\Models\Item;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryForecastService;
use App\Services\Support\WarehouseAccess;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts.app')]
#[Title('Prakiraan Permintaan')]
class ForecastReport extends Component
{
    use HandlesReportExport;

    public string $warehouseFilter = '';

    public ?int $detailItemId = null;

    public function mount(): void
    {
        abort_unless(auth()->user()->hasPermission('reports.view'), 403);
    }

    public function showDetail(int $itemId): void
    {
        $this->detailItemId = $itemId;
    }

    public function closeDetail(): void
    {
        $this->detailItemId = null;
    }

    protected function warehouseId(): ?int
    {
        return $this->warehouseFilter !== '' ? (int) $this->warehouseFilter : null;
    }

    protected function exportRows(): array
    {
        return InventoryForecastService::topNeeds(50, $this->warehouseId())
            ->map(fn ($r) => [$r['sku'], $r['item_name'], $r['on_hand'], $r['usage_30'], $r['daily_mean'], $r['days_cover'] ?? '-'])
            ->all();
    }

    public function exportCsv(): StreamedResponse
    {
        abort_unless(auth()->user()->hasPermission('reports.export'), 403);

        return $this->csvResponse(
            'forecast-'.now()->format('Ymd-His').'.csv',
            ['SKU', 'Item', 'On Hand', 'Usage 30 Hari', 'Rata-rata Harian', 'Cover (hari)'],
            $this->exportRows(),
        );
    }

    public function exportExcel()
    {
        abort_unless(auth()->user()->hasPermission('reports.export'), 403);

        return Excel::download(
            new ArrayExport(['SKU', 'Item', 'On Hand', 'Usage 30 Hari', 'Rata-rata Harian', 'Cover (hari)'], $this->exportRows()),
            'forecast-'.now()->format('Ymd-His').'.xlsx',
        );
    }

    public function render()
    {
        $detail = null;
        $detailItem = null;

        if ($this->detailItemId !== null) {
            $detail = InventoryForecastService::forecast($this->detailItemId, 30, $this->warehouseId());
            $detailItem = Item::find($this->detailItemId);
        }

        return view('livewire.reports.forecast-report', [
            'rows' => InventoryForecastService::topNeeds(20, $this->warehouseId()),
            'detail' => $detail,
            'detailItem' => $detailItem,
            'serviceLevel' => InventoryForecastService::serviceLevel(),
            'leadTime' => InventoryForecastService::leadTimeDaysDefault(),
            'warehouses' => Warehouse::whereIn('id', WarehouseAccess::ids())->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
