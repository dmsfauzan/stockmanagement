<?php

namespace App\Http\Controllers\Api;

use App\Enums\AssemblyType;
use App\Models\AssemblyOrder;
use App\Services\Inventory\AssemblyService;
use App\Services\Inventory\BomService;
use App\Services\Support\DocumentNumberService;
use App\Services\Support\WarehouseAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssemblyController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = AssemblyOrder::query()
            ->with(['item:id,sku,name', 'warehouse:id,name'])
            ->whereIn('warehouse_id', WarehouseAccess::ids())
            ->when($request->filled('status'), fn ($q) => $q->where('status', (string) $request->string('status')))
            ->when($request->filled('warehouse_id'), fn ($q) => $q->where('warehouse_id', $request->integer('warehouse_id')))
            ->orderByDesc('id');

        $paginator = $query->paginate(min(100, max(1, (int) $request->integer('per_page', 15))))->withQueryString();

        $paginator->getCollection()->transform(fn ($r) => [
            'id' => $r->id,
            'number' => $r->number,
            'type' => $r->type instanceof AssemblyType ? $r->type->value : $r->type,
            'item' => $r->item?->sku,
            'quantity' => (int) $r->quantity,
            'warehouse' => $r->warehouse?->name,
            'status' => $r->status,
        ]);

        return $this->paginated($paginator);
    }

    public function show(int $id): JsonResponse
    {
        $order = AssemblyOrder::with(['item:id,sku,name', 'warehouse:id,name', 'location:id,code', 'items.item:id,sku,name'])
            ->whereIn('warehouse_id', WarehouseAccess::ids())
            ->findOrFail($id);

        return $this->ok([
            'id' => $order->id,
            'number' => $order->number,
            'type' => $order->type instanceof AssemblyType ? $order->type->value : $order->type,
            'item_id' => $order->item_id,
            'quantity' => (int) $order->quantity,
            'status' => $order->status,
            'warehouse_id' => $order->warehouse_id,
            'location_id' => $order->location_id,
            'items' => $order->items->map(fn ($i) => [
                'id' => $i->id,
                'item_id' => $i->item_id,
                'role' => $i->role,
                'quantity' => (int) $i->quantity,
            ])->values()->all(),
        ], 'OK');
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:assembly,disassembly'],
            'assembly_date' => ['required', 'date'],
            'item_id' => ['required', 'exists:items,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'location_id' => ['required', 'exists:locations,id'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        if (! auth()->user()?->canAccessWarehouse((int) $data['warehouse_id'])) {
            abort(403);
        }

        $kitId = (int) $data['item_id'];

        if (! BomService::isKit($kitId)) {
            return $this->error('Barang belum memiliki BOM.', 422);
        }

        $order = AssemblyOrder::create([
            'number' => DocumentNumberService::generate($data['type'] === 'assembly' ? 'ASM' : 'DIS'),
            'type' => $data['type'],
            'assembly_date' => $data['assembly_date'],
            'item_id' => $kitId,
            'quantity' => (int) $data['quantity'],
            'warehouse_id' => $data['warehouse_id'],
            'location_id' => $data['location_id'],
            'status' => 'approved',
            'notes' => $data['notes'] ?? null,
            'created_by' => auth()->id(),
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        foreach (BomService::components($kitId) as $component) {
            $order->items()->create([
                'item_id' => $component->component_item_id,
                'role' => 'component',
                'quantity' => (int) $component->quantity,
                'unit_cost' => (float) ($component->component?->cost ?? 0),
            ]);
        }

        try {
            AssemblyService::post($order->fresh('items'));
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->created(['id' => $order->id, 'number' => $order->number], 'Assembly order dibuat.');
    }
}
