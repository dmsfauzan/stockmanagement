<?php

namespace App\Http\Controllers\Api;

use App\Models\Item;
use App\Models\PurchaseRequisition;
use App\Services\Support\DocumentNumberService;
use App\Services\Support\WarehouseAccess;
use App\Services\Workflow\RequisitionWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RequisitionController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = PurchaseRequisition::query()
            ->with(['warehouse:id,name', 'purchaseOrder:id,number', 'items'])
            ->whereIn('warehouse_id', WarehouseAccess::ids())
            ->when($request->filled('status'), fn ($q) => $q->where('status', (string) $request->string('status')))
            ->when($request->filled('warehouse_id'), fn ($q) => $q->where('warehouse_id', $request->integer('warehouse_id')))
            ->orderByDesc('id');

        $paginator = $query->paginate(min(100, max(1, (int) $request->integer('per_page', 15))))->withQueryString();

        $paginator->getCollection()->transform(fn ($r) => [
            'id' => $r->id,
            'number' => $r->number,
            'request_date' => $r->request_date?->toDateString(),
            'warehouse' => $r->warehouse?->name,
            'status' => $r->status,
            'purchase_order' => $r->purchaseOrder?->number,
        ]);

        return $this->paginated($paginator);
    }

    public function show(int $id): JsonResponse
    {
        $req = PurchaseRequisition::with(['warehouse:id,name', 'items.item:id,sku,name', 'items.unit:id,code'])
            ->whereIn('warehouse_id', WarehouseAccess::ids())
            ->findOrFail($id);

        return $this->ok([
            'id' => $req->id,
            'number' => $req->number,
            'request_date' => $req->request_date?->toDateString(),
            'required_date' => $req->required_date?->toDateString(),
            'warehouse' => $req->warehouse?->name,
            'status' => $req->status,
            'notes' => $req->notes,
            'items' => $req->items->map(fn ($i) => [
                'item_id' => $i->item_id,
                'sku' => $i->item?->sku,
                'quantity' => (int) $i->quantity,
                'estimated_price' => (float) $i->estimated_price,
            ])->values()->all(),
        ], 'OK');
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'request_date' => ['required', 'date'],
            'required_date' => ['nullable', 'date', 'after_or_equal:request_date'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'notes' => ['nullable', 'string'],
            'submit' => ['sometimes', 'boolean'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'exists:items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_id' => ['nullable', 'exists:units,id'],
            'items.*.estimated_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        if (! auth()->user()?->canAccessWarehouse((int) $data['warehouse_id'])) {
            abort(403);
        }

        $req = DB::transaction(function () use ($data): PurchaseRequisition {
            $req = PurchaseRequisition::create([
                'number' => DocumentNumberService::generate('PR'),
                'request_date' => $data['request_date'],
                'required_date' => $data['required_date'] ?? null,
                'warehouse_id' => $data['warehouse_id'],
                'requester_id' => auth()->id(),
                'status' => 'draft',
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            foreach ($data['items'] as $row) {
                $req->items()->create([
                    'item_id' => $row['item_id'],
                    'quantity' => (int) $row['quantity'],
                    'unit_id' => $row['unit_id'] ?? Item::whereKey($row['item_id'])->value('unit_id'),
                    'estimated_price' => (float) ($row['estimated_price'] ?? 0),
                ]);
            }

            return $req;
        });

        if ((bool) ($data['submit'] ?? false)) {
            RequisitionWorkflow::submit($req->id);
        }

        return $this->created(['id' => $req->id, 'number' => $req->number], 'Requisition dibuat.');
    }

    public function submit(int $id): JsonResponse
    {
        return $this->run(fn () => RequisitionWorkflow::submit($id));
    }

    public function approve(int $id): JsonResponse
    {
        return $this->run(fn () => RequisitionWorkflow::approve($id));
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']]);

        return $this->run(fn () => RequisitionWorkflow::reject($id, $data['reason']));
    }

    public function convert(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'currency_code' => ['nullable', 'string', 'max:10', 'exists:currencies,code'],
        ]);

        $order = $this->run(fn () => RequisitionWorkflow::convertToPurchaseOrder($id, $data['supplier_id'] ?? null, $data['currency_code'] ?? null));

        return $order instanceof JsonResponse ? $order : $this->ok(['purchase_order' => $order->number], 'OK');
    }

    protected function run(callable $callback): mixed
    {
        try {
            $result = $callback();
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $result ?? $this->ok(null, 'OK');
    }
}
