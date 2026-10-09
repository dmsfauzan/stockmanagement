<?php

namespace App\Http\Controllers\Api;

use App\Models\CustomerReturn;
use App\Models\SupplierReturn;
use App\Services\Support\AuditLogger;
use App\Services\Support\DocumentNumberService;
use App\Services\Workflow\DocumentWorkflow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReturnController extends ApiController
{
    protected function run(callable $callback): JsonResponse
    {
        try {
            $callback();
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->ok(null, 'OK');
    }

    // ---- Customer returns ----

    public function customerReturns(Request $request): JsonResponse
    {
        $query = CustomerReturn::query()
            ->with(['customer:id,name', 'warehouse:id,name'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('transaction_date')
            ->orderByDesc('id');

        $paginator = $query->paginate(min(100, max(1, $request->integer('per_page', 15))))->withQueryString();

        $paginator->getCollection()->transform(fn ($r) => [
            'id' => $r->id,
            'number' => $r->number,
            'transaction_date' => $r->transaction_date?->toDateString(),
            'customer' => $r->customer?->name,
            'warehouse' => $r->warehouse?->name,
            'status' => $r->status,
        ]);

        return $this->paginated($paginator);
    }

    public function customerReturn(int $id): JsonResponse
    {
        $return = CustomerReturn::with(['customer', 'warehouse', 'location', 'items.item', 'items.unit'])->findOrFail($id);

        return $this->ok($this->present($return));
    }

    public function storeCustomerReturn(Request $request): JsonResponse
    {
        $data = $request->validate([
            'transaction_date' => ['required', 'date'],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'goods_issue_id' => ['nullable', 'exists:goods_issues,id'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'location_id' => ['required', 'exists:locations,id'],
            'reason' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'exists:items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_id' => ['required', 'exists:units,id'],
            'items.*.location_id' => ['required', 'exists:locations,id'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'items.*.batch_number' => ['nullable', 'string', 'max:60'],
            'items.*.serial_number' => ['nullable', 'string', 'max:80'],
        ]);

        $return = DB::transaction(function () use ($data): CustomerReturn {
            $return = CustomerReturn::create([
                'number' => DocumentNumberService::generate('CRT'),
                'transaction_date' => $data['transaction_date'],
                'customer_id' => $data['customer_id'] ?? null,
                'goods_issue_id' => $data['goods_issue_id'] ?? null,
                'warehouse_id' => $data['warehouse_id'],
                'location_id' => $data['location_id'],
                'reason' => $data['reason'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => 'draft',
                'created_by' => auth()->id(),
            ]);

            foreach ($data['items'] as $row) {
                $return->items()->create([
                    'item_id' => $row['item_id'],
                    'quantity' => $row['quantity'],
                    'unit_id' => $row['unit_id'],
                    'location_id' => $row['location_id'],
                    'unit_cost' => $row['unit_cost'] ?? 0,
                    'batch_number' => $row['batch_number'] ?? null,
                    'serial_number' => $row['serial_number'] ?? null,
                ]);
            }

            AuditLogger::logModel('create', $return, null, $return->fresh('items')->toArray());

            return $return;
        });

        return $this->created(['id' => $return->id, 'number' => $return->number]);
    }

    public function customerReturnSubmit(int $id): JsonResponse
    {
        return $this->run(fn () => DocumentWorkflow::submitCustomerReturn($id));
    }

    public function customerReturnApprove(int $id): JsonResponse
    {
        return $this->run(fn () => DocumentWorkflow::approveCustomerReturn($id));
    }

    public function customerReturnReject(Request $request, int $id): JsonResponse
    {
        $reason = (string) $request->validate(['reason' => ['required', 'string', 'min:3', 'max:1000']])['reason'];

        return $this->run(fn () => DocumentWorkflow::rejectCustomerReturn($id, $reason));
    }

    public function customerReturnPost(int $id): JsonResponse
    {
        return $this->run(fn () => DocumentWorkflow::postCustomerReturn($id));
    }

    // ---- Supplier returns ----

    public function supplierReturns(Request $request): JsonResponse
    {
        $query = SupplierReturn::query()
            ->with(['supplier:id,name', 'warehouse:id,name'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('transaction_date')
            ->orderByDesc('id');

        $paginator = $query->paginate(min(100, max(1, $request->integer('per_page', 15))))->withQueryString();

        $paginator->getCollection()->transform(fn ($r) => [
            'id' => $r->id,
            'number' => $r->number,
            'transaction_date' => $r->transaction_date?->toDateString(),
            'supplier' => $r->supplier?->name,
            'warehouse' => $r->warehouse?->name,
            'status' => $r->status,
        ]);

        return $this->paginated($paginator);
    }

    public function supplierReturn(int $id): JsonResponse
    {
        $return = SupplierReturn::with(['supplier', 'warehouse', 'location', 'items.item', 'items.unit'])->findOrFail($id);

        return $this->ok($this->present($return));
    }

    public function storeSupplierReturn(Request $request): JsonResponse
    {
        $data = $request->validate([
            'transaction_date' => ['required', 'date'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'goods_receipt_id' => ['nullable', 'exists:goods_receipts,id'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'location_id' => ['required', 'exists:locations,id'],
            'reason' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'exists:items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_id' => ['required', 'exists:units,id'],
            'items.*.location_id' => ['required', 'exists:locations,id'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'items.*.batch_number' => ['nullable', 'string', 'max:60'],
            'items.*.serial_number' => ['nullable', 'string', 'max:80'],
        ]);

        $return = DB::transaction(function () use ($data): SupplierReturn {
            $return = SupplierReturn::create([
                'number' => DocumentNumberService::generate('SRT'),
                'transaction_date' => $data['transaction_date'],
                'supplier_id' => $data['supplier_id'] ?? null,
                'goods_receipt_id' => $data['goods_receipt_id'] ?? null,
                'warehouse_id' => $data['warehouse_id'],
                'location_id' => $data['location_id'],
                'reason' => $data['reason'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => 'draft',
                'created_by' => auth()->id(),
            ]);

            foreach ($data['items'] as $row) {
                $return->items()->create([
                    'item_id' => $row['item_id'],
                    'quantity' => $row['quantity'],
                    'unit_id' => $row['unit_id'],
                    'location_id' => $row['location_id'],
                    'unit_cost' => $row['unit_cost'] ?? 0,
                    'batch_number' => $row['batch_number'] ?? null,
                    'serial_number' => $row['serial_number'] ?? null,
                ]);
            }

            AuditLogger::logModel('create', $return, null, $return->fresh('items')->toArray());

            return $return;
        });

        return $this->created(['id' => $return->id, 'number' => $return->number]);
    }

    public function supplierReturnSubmit(int $id): JsonResponse
    {
        return $this->run(fn () => DocumentWorkflow::submitSupplierReturn($id));
    }

    public function supplierReturnApprove(int $id): JsonResponse
    {
        return $this->run(fn () => DocumentWorkflow::approveSupplierReturn($id));
    }

    public function supplierReturnReject(Request $request, int $id): JsonResponse
    {
        $reason = (string) $request->validate(['reason' => ['required', 'string', 'min:3', 'max:1000']])['reason'];

        return $this->run(fn () => DocumentWorkflow::rejectSupplierReturn($id, $reason));
    }

    public function supplierReturnPost(int $id): JsonResponse
    {
        return $this->run(fn () => DocumentWorkflow::postSupplierReturn($id));
    }

    public function report(Request $request): JsonResponse
    {
        $from = $request->filled('date_from') ? Carbon::parse((string) $request->input('date_from'))->startOfDay() : null;
        $to = $request->filled('date_to') ? Carbon::parse((string) $request->input('date_to'))->endOfDay() : null;
        $type = $request->string('type')->toString();

        $out = [];

        $collect = function (Builder $query, string $kind) use ($from, $to, &$out): int {
            $rows = $query->with(['items', 'warehouse'])
                ->when($from !== null, fn ($q) => $q->where('transaction_date', '>=', $from->toDateString()))
                ->when($to !== null, fn ($q) => $q->where('transaction_date', '<=', $to->toDateString()))
                ->get();

            foreach ($rows as $row) {
                $out[] = [
                    'number' => $row->number,
                    'type' => $kind,
                    'date' => $row->transaction_date?->toDateString(),
                    'warehouse' => $row->warehouse?->name,
                    'qty' => (int) $row->items->sum('quantity'),
                    'value' => (float) $row->items->sum(fn ($i) => (float) $i->quantity * (float) $i->unit_cost),
                    'status' => $row->status,
                ];
            }

            return $rows->count();
        };

        $total = 0;

        if (! in_array($type, ['customer', 'supplier'], true)) {
            $total += $collect(CustomerReturn::query(), 'customer');
            $total += $collect(SupplierReturn::query(), 'supplier');
        } elseif ($type === 'customer') {
            $total += $collect(CustomerReturn::query(), 'customer');
        } else {
            $total += $collect(SupplierReturn::query(), 'supplier');
        }

        return $this->ok([
            'total' => $total,
            'rows' => $out,
        ], 'OK');
    }

    /**
     * @return array<string, mixed>
     */
    protected function present(CustomerReturn|SupplierReturn $return): array
    {
        return [
            'id' => $return->id,
            'number' => $return->number,
            'transaction_date' => $return->transaction_date?->toDateString(),
            'status' => $return->status,
            'warehouse' => $return->warehouse?->name,
            'location' => $return->location?->code,
            'reason' => $return->reason,
            'notes' => $return->notes,
            'items' => $return->items->map(fn ($row) => [
                'item_id' => $row->item_id,
                'sku' => $row->item?->sku,
                'item_name' => $row->item?->name,
                'quantity' => (int) $row->quantity,
                'unit' => $row->unit?->code,
                'location' => $row->location?->code,
                'unit_cost' => (float) $row->unit_cost,
            ])->all(),
        ];
    }
}
