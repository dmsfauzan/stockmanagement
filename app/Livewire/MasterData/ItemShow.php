<?php

namespace App\Livewire\MasterData;

use App\Enums\TrackingType;
use App\Models\Item;
use App\Models\StockBalance;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Services\Inventory\StockStatusService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Detail Barang')]
class ItemShow extends Component
{
    public int $item;

    public string $tab = 'info';

    public function mount($item): void
    {
        $model = $item instanceof Item ? $item : Item::findOrFail($item);

        $this->authorize('view', $model);

        $this->item = $model->id;
    }

    public function setTab(string $tab): void
    {
        if (in_array($tab, ['info', 'inventory', 'movement', 'summary'], true)) {
            $this->tab = $tab;
        }
    }

    public function render()
    {
        $item = Item::with(['category', 'unit', 'primarySupplier'])->findOrFail($this->item);

        $balances = StockBalance::where('item_id', $item->id)
            ->with(['warehouse', 'location.rack.zone.warehouse'])
            ->get();

        $movements = StockMovement::where('item_id', $item->id)
            ->with(['warehouse', 'location', 'creator'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->take(20)
            ->get();

        $lots = collect();
        if ($item->tracking_type !== TrackingType::None) {
            $lots = StockLot::where('item_id', $item->id)
                ->with(['warehouse', 'location.rack.zone.warehouse'])
                ->available()
                ->fefo()
                ->get();
        }

        $totalOnHand = (int) $balances->sum('quantity_on_hand');
        $totalReserved = (int) $balances->sum('quantity_reserved');
        $stockStatus = StockStatusService::evaluate($item, $totalOnHand);

        return view('livewire.master-data.item-show', [
            'itemModel' => $item,
            'balances' => $balances,
            'movements' => $movements,
            'lots' => $lots,
            'totalOnHand' => $totalOnHand,
            'totalReserved' => $totalReserved,
            'totalAvailable' => $totalOnHand - $totalReserved,
            'stockStatus' => $stockStatus,
        ]);
    }
}
