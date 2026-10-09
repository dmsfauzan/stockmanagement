<?php

namespace App\Livewire\Reports;

use App\Livewire\Reports\Concerns\HandlesReportExport;
use App\Models\Warehouse;
use App\Services\Inventory\CapacityService;
use App\Services\Support\WarehouseAccess;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Kapasitas Gudang')]
class CapacityReport extends Component
{
    use HandlesReportExport;

    public string $warehouseFilter = '';

    public bool $onlyConstrained = false;

    public function mount(): void
    {
        abort_unless(auth()->user()->hasPermission('reports.view'), 403);
    }

    public function exportCsv()
    {
        abort_unless(auth()->user()->hasPermission('reports.export'), 403);

        return $this->csvResponse(
            'capacity-'.now()->format('Ymd-His').'.csv',
            ['Lokasi', 'Warehouse', 'Kapasitas', 'Terpakai', 'Sisa', 'Persen', 'Status'],
            CapacityService::utilization($this->warehouseFilter !== '' ? (int) $this->warehouseFilter : null)
                ->map(fn ($r) => [$r['location_path'], $r['warehouse_name'], $r['capacity'], $r['used'], $r['free'], $r['percent'], $r['status']])
                ->all(),
        );
    }

    public function render()
    {
        $rows = CapacityService::utilization(
            $this->warehouseFilter !== '' ? (int) $this->warehouseFilter : null,
            $this->onlyConstrained,
        )->values()->all();

        return view('livewire.reports.capacity-report', [
            'rows' => $rows,
            'warehouses' => Warehouse::whereIn('id', WarehouseAccess::ids())->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
