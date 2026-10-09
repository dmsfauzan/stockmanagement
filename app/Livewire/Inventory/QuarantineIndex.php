<?php

namespace App\Livewire\Inventory;

use App\Models\Warehouse;
use App\Services\Inventory\QualityService;
use App\Services\Support\WarehouseAccess;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts.app')]
#[Title('Karantina')]
class QuarantineIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $warehouseFilter = '';

    public int $perPage = 10;

    /** @var array<int, array{quantity: string}> */
    public array $quantities = [];

    /** @var array<int, string> */
    public array $reasons = [];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedWarehouseFilter(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function releaseRow(int $balanceId): void
    {
        $this->guardQuarantine();

        $row = $this->rowQuery()->where('stock_balances.id', $balanceId)->firstOrFail();

        $quantity = (int) ($this->quantities[$balanceId]['quantity'] ?? 0);
        $reason = trim((string) ($this->reasons[$balanceId] ?? ''));

        if ($quantity <= 0) {
            $this->addError('quantities.'.$balanceId.'.quantity', __('Qty harus lebih dari 0.'));

            return;
        }

        QualityService::release((int) $row->item_id, (int) $row->warehouse_id, (int) $row->location_id, $quantity, $reason !== '' ? $reason : null);

        $this->reset('quantities', 'reasons');
        $this->dispatch('toast', type: 'success', message: __('Stok diloloskan dari karantina.'));
    }

    public function rejectRow(int $balanceId): void
    {
        $this->guardQuarantine();

        $row = $this->rowQuery()->where('stock_balances.id', $balanceId)->firstOrFail();

        $quantity = (int) ($this->quantities[$balanceId]['quantity'] ?? 0);
        $reason = trim((string) ($this->reasons[$balanceId] ?? 'Reject karantina'));

        if ($quantity <= 0) {
            $this->addError('quantities.'.$balanceId.'.quantity', __('Qty harus lebih dari 0.'));

            return;
        }

        QualityService::reject((int) $row->item_id, (int) $row->warehouse_id, (int) $row->location_id, $quantity, $reason);

        $this->reset('quantities', 'reasons');
        $this->dispatch('toast', type: 'success', message: __('Stok karantina ditolak.'));
    }

    public function export(): StreamedResponse
    {
        $rows = $this->rowQuery()->orderBy('items.sku')->get();

        return response()->streamDownload(function () use ($rows): void {
            $h = fopen('php://output', 'w');
            fputcsv($h, ['SKU', 'Item', 'Warehouse', 'Location', 'On Hand', 'Quarantined', 'Available']);
            foreach ($rows as $row) {
                fputcsv($h, [
                    $row->sku, $row->item_name, $row->warehouse_name, $row->location_path,
                    $row->quantity_on_hand, $row->quantity_quarantine, $row->quantity_available,
                ]);
            }
            fclose($h);
        }, 'quarantine-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    protected function rowQuery()
    {
        return DB::table('stock_balances')
            ->join('items', 'stock_balances.item_id', '=', 'items.id')
            ->join('warehouses', 'stock_balances.warehouse_id', '=', 'warehouses.id')
            ->join('locations', 'stock_balances.location_id', '=', 'locations.id')
            ->join('racks', 'locations.rack_id', '=', 'racks.id')
            ->join('zones', 'racks.zone_id', '=', 'zones.id')
            ->whereNull('items.deleted_at')
            ->whereIn('warehouses.id', WarehouseAccess::ids())
            ->where('stock_balances.quantity_quarantine', '>', 0)
            ->when($this->search !== '', function ($q): void {
                $term = '%'.$this->search.'%';
                $q->where(function ($inner) use ($term): void {
                    $inner->where('items.sku', 'like', $term)->orWhere('items.name', 'like', $term);
                });
            })
            ->when($this->warehouseFilter !== '', fn ($q) => $q->where('warehouses.id', $this->warehouseFilter))
            ->select([
                'stock_balances.id',
                'stock_balances.item_id',
                'stock_balances.warehouse_id',
                'stock_balances.location_id',
                'stock_balances.quantity_on_hand',
                'stock_balances.quantity_reserved',
                'stock_balances.quantity_quarantine',
                'stock_balances.quantity_available',
                'items.sku',
                'items.name as item_name',
                'warehouses.name as warehouse_name',
                DB::raw("CONCAT_WS(' / ', warehouses.name, zones.name, racks.name, locations.code) as location_path"),
            ]);
    }

    protected function guardQuarantine(): void
    {
        abort_unless(auth()->user()?->hasPermission('stock.quarantine'), 403);
    }

    public function render()
    {
        $rows = $this->rowQuery()->orderBy('items.sku')->paginate($this->perPage);
        $summary = QualityService::quarantineSummary();

        return view('livewire.inventory.quarantine-index', [
            'rows' => $rows,
            'warehouses' => Warehouse::whereIn('id', WarehouseAccess::ids())->orderBy('name')->get(['id', 'name']),
            'summary' => $summary,
        ]);
    }
}
