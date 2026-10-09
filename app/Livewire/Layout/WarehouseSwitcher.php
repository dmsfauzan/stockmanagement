<?php

namespace App\Livewire\Layout;

use App\Models\Warehouse;
use App\Services\Support\WarehouseAccess;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;
use Livewire\Component;

class WarehouseSwitcher extends Component
{
    public ?int $activeWarehouseId = null;

    public Collection $warehouses;

    public function mount(): void
    {
        $this->warehouses = Warehouse::where('status', 'active')
            ->whereIn('id', WarehouseAccess::ids())
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        $this->syncFromSession();
    }

    public function select($id = null): void
    {
        $id = ($id === null || $id === '' || $id === '0') ? null : (int) $id;

        if ($id !== null && ! $this->warehouses->contains('id', $id)) {
            $id = null;
        }

        $this->activeWarehouseId = $id;

        if ($id === null) {
            session()->forget('active_warehouse_id');
        } else {
            session(['active_warehouse_id' => $id]);
        }

        $this->dispatch('warehouse-changed', warehouseId: $id);

        $referer = request()->header('Referer') ?: route('dashboard');

        $this->redirect($referer, navigate: true);
    }

    #[On('warehouse-changed')]
    public function syncFromSession(): void
    {
        $id = WarehouseAccess::activeId();
        $this->activeWarehouseId = $id;
    }

    public function render()
    {
        return view('livewire.layout.warehouse-switcher');
    }
}
