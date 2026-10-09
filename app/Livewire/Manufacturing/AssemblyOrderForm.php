<?php

namespace App\Livewire\Manufacturing;

use App\Enums\AssemblyType;
use App\Models\AssemblyOrder;
use App\Models\Item;
use App\Models\Location;
use App\Models\StockBalance;
use App\Models\Warehouse;
use App\Services\Inventory\AssemblyService;
use App\Services\Inventory\BomService;
use App\Services\Support\AuditLogger;
use App\Services\Support\DocumentNumberService;
use App\Services\Support\WarehouseAccess;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Form Perakitan')]
class AssemblyOrderForm extends Component
{
    public string $type = 'assembly';

    public string $assembly_date = '';

    public string $item_id = '';

    public int $quantity = 1;

    public string $warehouse_id = '';

    public string $location_id = '';

    public string $notes = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->hasPermission('assembly.create'), 403);

        $this->assembly_date = now()->format('Y-m-d');
    }

    protected function rules(): array
    {
        return [
            'type' => ['required', 'in:assembly,disassembly'],
            'assembly_date' => ['required', 'date'],
            'item_id' => ['required', 'exists:items,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'location_id' => ['required', 'exists:locations,id'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function updatedWarehouseId(): void
    {
        $this->location_id = '';
    }

    public function save(bool $post = false)
    {
        $data = $this->validate();

        abort_unless(auth()->user()?->canAccessWarehouse((int) $data['warehouse_id']), 403);

        $kitId = (int) $data['item_id'];

        if (! BomService::isKit($kitId)) {
            $this->addError('item_id', __('Barang ini belum memiliki BOM / komponen.'));

            return;
        }

        $order = AssemblyOrder::create([
            'number' => DocumentNumberService::generate($data['type'] === 'assembly' ? 'ASM' : 'DIS'),
            'type' => $data['type'],
            'assembly_date' => $data['assembly_date'],
            'item_id' => $kitId,
            'quantity' => (int) $data['quantity'],
            'warehouse_id' => $data['warehouse_id'],
            'location_id' => $data['location_id'],
            'status' => 'draft',
            'notes' => $data['notes'] !== '' ? $data['notes'] : null,
            'created_by' => auth()->id(),
        ]);

        foreach (BomService::components($kitId) as $component) {
            $order->items()->create([
                'item_id' => $component->component_item_id,
                'role' => 'component',
                'quantity' => (int) $component->quantity,
                'unit_cost' => (float) ($component->component?->cost ?? 0),
            ]);
        }

        AuditLogger::logModel('create', $order);

        if ($post) {
            abort_unless(auth()->user()->hasPermission('assembly.post'), 403);

            $order->update(['status' => 'approved', 'approved_by' => auth()->id(), 'approved_at' => now()]);

            try {
                AssemblyService::post($order->fresh('items'));
                $this->dispatch('toast', type: 'success', message: __('Perakitan diposting.'));
            } catch (\RuntimeException $e) {
                $this->dispatch('toast', type: 'error', message: $e->getMessage());

                return $this->redirect(route('assembly-orders.index'), navigate: true);
            }
        } else {
            $this->dispatch('toast', type: 'success', message: __('Draf perakitan tersimpan.'));
        }

        return $this->redirect(route('assembly-orders.index'), navigate: true);
    }

    public function render()
    {
        $components = $this->item_id !== '' ? BomService::components((int) $this->item_id) : collect();

        $availability = [];
        if ($this->item_id !== '' && $this->location_id !== '') {
            foreach ($components as $component) {
                $availability[(int) $component->component_item_id] = (int) StockBalance::where('item_id', $component->component_item_id)
                    ->where('warehouse_id', $this->warehouse_id)
                    ->where('location_id', $this->location_id)
                    ->value('quantity_on_hand');
            }
        }

        $kitOnHand = ($this->item_id !== '' && $this->location_id !== '')
            ? (int) StockBalance::where('item_id', $this->item_id)->where('warehouse_id', $this->warehouse_id)->where('location_id', $this->location_id)->value('quantity_on_hand')
            : 0;

        return view('livewire.manufacturing.assembly-order-form', [
            'components' => $components,
            'availability' => $availability,
            'kitOnHand' => $kitOnHand,
            'itemsList' => Item::where('status', 'active')->orderBy('name')->get(['id', 'sku', 'name']),
            'warehouses' => Warehouse::whereIn('id', WarehouseAccess::ids())->orderBy('name')->get(['id', 'name']),
            'locations' => Location::query()
                ->when($this->warehouse_id !== '', fn ($q) => $q->whereHas('rack.zone', fn ($inner) => $inner->where('warehouse_id', $this->warehouse_id)))
                ->orderBy('code')
                ->get(['id', 'code']),
            'types' => AssemblyType::cases(),
        ]);
    }
}
