<?php

namespace App\Livewire\Scanning;

use App\Enums\StockStatus;
use App\Models\Item;
use App\Models\Location;
use App\Models\StockBalance;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Scan')]
class ScanIndex extends Component
{
    public string $code = '';

    public ?array $result = null;

    public ?array $locationResult = null;

    public array $recent = [];

    public function mount(): void
    {
        $this->authorize('viewAny', Item::class);
        $this->recent = (array) session('scan_recent', []);
    }

    public function lookup(): void
    {
        $this->validate(['code' => ['nullable', 'string', 'max:60']]);

        $code = trim($this->code);

        if ($code === '') {
            $this->dispatch('toast', type: 'error', message: 'Masukkan barcode / SKU.');

            return;
        }

        $item = Item::with(['category', 'unit'])
            ->where('barcode', $code)
            ->first();

        if (! $item) {
            $item = Item::with(['category', 'unit'])
                ->where('sku', $code)
                ->first();
        }

        if (! $item) {
            $item = Item::with(['category', 'unit'])
                ->where('name', 'like', '%'.$code.'%')
                ->first();
        }

        if ($item) {
            $item = $item->fresh(['category', 'unit', 'primarySupplier']);
            $balances = StockBalance::where('item_id', $item->id)
                ->with(['warehouse', 'location.rack.zone.warehouse'])
                ->get();
            $totalOnHand = (int) $balances->sum('quantity_on_hand');
            $status = StockStatus::evaluate($totalOnHand, (int) $item->minimum_stock, (int) $item->maximum_stock);

            $this->result = [
                'type' => 'item',
                'item' => $item,
                'balances' => $balances,
                'status' => $status,
            ];
            $this->locationResult = null;
            $this->pushRecent($code);

            return;
        }

        $location = Location::where('code', $code)->first();

        if ($location) {
            $location = Location::with('rack.zone.warehouse')->find($location->id);
            $items = StockBalance::where('location_id', $location->id)
                ->with('item')
                ->get();

            $this->locationResult = [
                'location' => $location,
                'items' => $items,
            ];
            $this->result = null;
            $this->pushRecent($code);

            return;
        }

        $this->dispatch('toast', type: 'error', message: 'Tidak ada item atau location dengan code '.$code.'.');
    }

    public function lookupByCode(string $code): void
    {
        $this->code = $code;
        $this->lookup();
    }

    protected function pushRecent(string $code): void
    {
        $recent = array_values(array_filter(array_unique(array_merge([$code], $this->recent))));
        $this->recent = array_slice($recent, 0, 5);
        session(['scan_recent' => $this->recent]);
    }

    public function render()
    {
        return view('livewire.scanning.scan-index');
    }
}
