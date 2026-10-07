<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\StoreGoodsIssueRequest;
use App\Http\Requests\Api\StoreGoodsReceiptRequest;
use App\Http\Requests\Api\StorePurchaseOrderRequest;
use App\Http\Requests\Api\StoreStockAdjustmentRequest;
use App\Http\Requests\Api\StoreStockOpnameRequest;
use App\Http\Requests\Api\StoreStockTransferRequest;
use App\Http\Resources\Api\GoodsIssueResource;
use App\Http\Resources\Api\GoodsReceiptResource;
use App\Http\Resources\Api\PurchaseOrderResource;
use App\Http\Resources\Api\StockAdjustmentResource;
use App\Http\Resources\Api\StockOpnameResource;
use App\Http\Resources\Api\StockTransferResource;
use App\Models\GoodsIssue;
use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\StockAdjustment;
use App\Models\StockOpname;
use App\Models\StockTransfer;
use App\Services\Support\AuditLogger;
use App\Services\Support\DocumentNumberService;
use App\Services\Workflow\DocumentWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WriteTransactionController extends ApiController
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

    protected function reason(Request $request): string
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        return $data['reason'];
    }

    protected function nullableString(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;

        return ($value === null || $value === '') ? null : (string) $value;
    }

    public function storeReceipt(StoreGoodsReceiptRequest $request): JsonResponse
    {
        $data = $request->validated();

        $header = [
            'transaction_date' => $data['transaction_date'],
            'supplier_id' => $data['supplier_id'],
            'po_number' => $this->nullableString($data['po_number'] ?? null),
            'delivery_note' => $this->nullableString($data['delivery_note'] ?? null),
            'purchase_order_id' => $data['purchase_order_id'] ?? null,
            'warehouse_id' => $data['warehouse_id'],
            'received_by' => $this->nullableString($data['received_by'] ?? null),
            'notes' => $this->nullableString($data['notes'] ?? null),
            'number' => DocumentNumberService::generate('GR'),
            'status' => 'draft',
            'created_by' => auth()->id(),
        ];

        $rows = array_map(fn ($row) => [
            'item_id' => $row['item_id'],
            'quantity' => $row['quantity'],
            'unit_cost' => $row['unit_cost'] ?? 0,
            'unit_id' => $row['unit_id'],
            'location_id' => $row['location_id'],
            'batch_number' => $this->nullableString($row['batch_number'] ?? null),
            'expiry_date' => $this->nullableString($row['expiry_date'] ?? null),
            'notes' => $this->nullableString($row['notes'] ?? null),
        ], $data['items']);

        $receipt = DB::transaction(function () use ($header, $rows): GoodsReceipt {
            $receipt = GoodsReceipt::create($header);
            $receipt->receiptItems()->createMany($rows);

            AuditLogger::logModel('create', $receipt, null, $receipt->fresh('receiptItems')->toArray());

            return $receipt;
        });

        $receipt->load(['supplier:id,name', 'warehouse:id,name', 'receiptItems.item:id,sku,name']);

        return $this->created(new GoodsReceiptResource($receipt), 'Barang masuk tersimpan.');
    }

    public function submitReceipt(int $id): JsonResponse
    {
        return $this->run(fn () => DocumentWorkflow::submitReceipt($id));
    }

    public function approveReceipt(int $id): JsonResponse
    {
        return $this->run(fn () => DocumentWorkflow::approveReceipt($id));
    }

    public function rejectReceipt(Request $request, int $id): JsonResponse
    {
        $reason = $this->reason($request);

        return $this->run(fn () => DocumentWorkflow::rejectReceipt($id, $reason));
    }

    public function postReceipt(int $id): JsonResponse
    {
        return $this->run(fn () => DocumentWorkflow::postReceipt($id));
    }

    public function storeIssue(StoreGoodsIssueRequest $request): JsonResponse
    {
        $data = $request->validated();

        $header = [
            'transaction_date' => $data['transaction_date'],
            'customer_id' => $data['customer_id'] ?? null,
            'destination' => $data['destination'],
            'sales_order_number' => $this->nullableString($data['sales_order_number'] ?? null),
            'warehouse_id' => $data['warehouse_id'],
            'issued_by' => $this->nullableString($data['issued_by'] ?? null),
            'notes' => $this->nullableString($data['notes'] ?? null),
            'number' => DocumentNumberService::generate('GI'),
            'status' => 'draft',
            'created_by' => auth()->id(),
        ];

        $rows = array_map(fn ($row) => [
            'item_id' => $row['item_id'],
            'quantity' => $row['quantity'],
            'unit_id' => $row['unit_id'],
            'location_id' => $row['location_id'],
            'notes' => $this->nullableString($row['notes'] ?? null),
        ], $data['items']);

        $issue = DB::transaction(function () use ($header, $rows): GoodsIssue {
            $issue = GoodsIssue::create($header);
            $issue->issueItems()->createMany($rows);

            AuditLogger::logModel('create', $issue, null, $issue->fresh('issueItems')->toArray());

            return $issue;
        });

        $issue->load(['customer:id,name', 'warehouse:id,name', 'issueItems.item:id,sku,name']);

        return $this->created(new GoodsIssueResource($issue), 'Barang keluar tersimpan.');
    }

    public function submitIssue(int $id): JsonResponse
    {
        return $this->run(fn () => DocumentWorkflow::submitIssue($id));
    }

    public function approveIssue(int $id): JsonResponse
    {
        return $this->run(fn () => DocumentWorkflow::approveIssue($id));
    }

    public function rejectIssue(Request $request, int $id): JsonResponse
    {
        $reason = $this->reason($request);

        return $this->run(fn () => DocumentWorkflow::rejectIssue($id, $reason));
    }

    public function postIssue(int $id): JsonResponse
    {
        return $this->run(fn () => DocumentWorkflow::postIssue($id));
    }

    public function storeAdjustment(StoreStockAdjustmentRequest $request): JsonResponse
    {
        $data = $request->validated();

        $header = [
            'transaction_date' => $data['transaction_date'],
            'warehouse_id' => $data['warehouse_id'],
            'location_id' => $data['location_id'],
            'reason' => $data['reason'],
            'notes' => $this->nullableString($data['notes'] ?? null),
            'number' => DocumentNumberService::generate('ADJ'),
            'status' => 'draft',
            'created_by' => auth()->id(),
        ];

        $rows = array_map(function ($row): array {
            $system = (int) ($row['system_quantity'] ?? 0);
            $actual = (int) $row['actual_quantity'];

            return [
                'item_id' => $row['item_id'],
                'system_quantity' => $system,
                'actual_quantity' => $actual,
                'difference' => $actual - $system,
                'notes' => $this->nullableString($row['notes'] ?? null),
            ];
        }, $data['items']);

        $adjustment = DB::transaction(function () use ($header, $rows): StockAdjustment {
            $adjustment = StockAdjustment::create($header);
            $adjustment->items()->createMany($rows);

            AuditLogger::logModel('create', $adjustment, null, $adjustment->fresh('items')->toArray());

            return $adjustment;
        });

        $adjustment->load(['warehouse:id,name', 'items.item:id,sku,name']);

        return $this->created(new StockAdjustmentResource($adjustment), 'Stock adjustment tersimpan.');
    }

    public function submitAdjustment(int $id): JsonResponse
    {
        return $this->run(fn () => DocumentWorkflow::submitAdjustment($id));
    }

    public function approveAdjustment(int $id): JsonResponse
    {
        return $this->run(fn () => DocumentWorkflow::approveAdjustment($id));
    }

    public function rejectAdjustment(Request $request, int $id): JsonResponse
    {
        $reason = $this->reason($request);

        return $this->run(fn () => DocumentWorkflow::rejectAdjustment($id, $reason));
    }

    public function postAdjustment(int $id): JsonResponse
    {
        return $this->run(fn () => DocumentWorkflow::postAdjustment($id));
    }

    public function storeOpname(StoreStockOpnameRequest $request): JsonResponse
    {
        $data = $request->validated();

        $items = array_map(fn ($row) => [
            'item_id' => $row['item_id'],
            'system_quantity' => (int) $row['system_quantity'],
            'physical_quantity' => isset($row['physical_quantity']) ? (int) $row['physical_quantity'] : null,
            'difference' => isset($row['physical_quantity']) ? (int) $row['physical_quantity'] - (int) $row['system_quantity'] : 0,
            'reason' => $this->nullableString($row['reason'] ?? null),
            'notes' => $this->nullableString($row['notes'] ?? null),
        ], $data['items'] ?? []);

        $opname = DB::transaction(function () use ($data, $items): StockOpname {
            $opname = StockOpname::create([
                'number' => DocumentNumberService::generate('OPN'),
                'opname_date' => $data['opname_date'],
                'warehouse_id' => $data['warehouse_id'],
                'location_id' => $this->nullableString($data['location_id'] ?? null),
                'notes' => $this->nullableString($data['notes'] ?? null),
                'status' => 'draft',
                'created_by' => auth()->id(),
            ]);

            if ($items !== []) {
                $opname->items()->createMany($items);
            }

            AuditLogger::logModel('create', $opname, null, $opname->fresh('items')->toArray());

            return $opname;
        });

        $opname->load(['warehouse:id,name', 'location:id,code', 'items.item:id,sku,name']);

        return $this->created(new StockOpnameResource($opname), 'Stock opname tersimpan.');
    }

    public function startOpname(int $id): JsonResponse
    {
        return $this->run(fn () => DocumentWorkflow::startCountingOpname($id));
    }

    public function submitOpname(int $id): JsonResponse
    {
        return $this->run(fn () => DocumentWorkflow::submitOpname($id));
    }

    public function approveOpname(int $id): JsonResponse
    {
        return $this->run(fn () => DocumentWorkflow::approveAndCompleteOpname($id));
    }

    public function rejectOpname(Request $request, int $id): JsonResponse
    {
        $reason = $this->reason($request);

        return $this->run(fn () => DocumentWorkflow::rejectOpname($id, $reason));
    }

    public function storeTransfer(StoreStockTransferRequest $request): JsonResponse
    {
        $data = $request->validated();

        $header = [
            'transfer_date' => $data['transfer_date'],
            'from_warehouse_id' => $data['from_warehouse_id'],
            'from_location_id' => $data['from_location_id'],
            'to_warehouse_id' => $data['to_warehouse_id'],
            'to_location_id' => $data['to_location_id'],
            'notes' => $this->nullableString($data['notes'] ?? null),
            'number' => DocumentNumberService::generate('TR'),
            'status' => 'draft',
            'created_by' => auth()->id(),
        ];

        $rows = array_map(fn ($row) => [
            'item_id' => $row['item_id'],
            'quantity' => (int) $row['quantity'],
            'unit_id' => $row['unit_id'],
            'notes' => $this->nullableString($row['notes'] ?? null),
        ], $data['items']);

        $transfer = DB::transaction(function () use ($header, $rows): StockTransfer {
            $transfer = StockTransfer::create($header);
            $transfer->items()->createMany($rows);

            AuditLogger::logModel('create', $transfer, null, $transfer->fresh('items')->toArray());

            return $transfer;
        });

        $transfer->load(['fromWarehouse:id,name', 'toWarehouse:id,name', 'items.item:id,sku,name']);

        return $this->created(new StockTransferResource($transfer), 'Transfer barang tersimpan.');
    }

    public function requestTransfer(int $id): JsonResponse
    {
        return $this->run(fn () => DocumentWorkflow::requestTransfer($id));
    }

    public function approveTransfer(int $id): JsonResponse
    {
        return $this->run(fn () => DocumentWorkflow::approveTransfer($id));
    }

    public function rejectTransfer(Request $request, int $id): JsonResponse
    {
        $reason = $this->reason($request);

        return $this->run(fn () => DocumentWorkflow::rejectTransfer($id, $reason));
    }

    public function dispatchTransfer(int $id): JsonResponse
    {
        return $this->run(fn () => DocumentWorkflow::dispatchTransfer($id));
    }

    public function receiveTransfer(int $id): JsonResponse
    {
        return $this->run(fn () => DocumentWorkflow::receiveTransfer($id));
    }

    public function completeTransfer(int $id): JsonResponse
    {
        return $this->run(fn () => DocumentWorkflow::completeTransfer($id));
    }

    public function storePurchaseOrder(StorePurchaseOrderRequest $request): JsonResponse
    {
        $data = $request->validated();

        $header = [
            'order_date' => $data['order_date'],
            'expected_date' => $this->nullableString($data['expected_date'] ?? null),
            'supplier_id' => $data['supplier_id'],
            'warehouse_id' => $data['warehouse_id'],
            'notes' => $this->nullableString($data['notes'] ?? null),
            'number' => DocumentNumberService::generate('PO'),
            'status' => 'draft',
            'created_by' => auth()->id(),
        ];

        $rows = array_map(fn ($row) => [
            'item_id' => $row['item_id'],
            'quantity' => (int) $row['quantity'],
            'unit_id' => $row['unit_id'],
            'unit_price' => $row['unit_price'],
            'notes' => $this->nullableString($row['notes'] ?? null),
            'received_quantity' => 0,
        ], $data['items']);

        $order = DB::transaction(function () use ($header, $rows): PurchaseOrder {
            $order = PurchaseOrder::create($header);
            $order->items()->createMany($rows);

            AuditLogger::logModel('create', $order, null, $order->fresh('items')->toArray());

            return $order;
        });

        $order->load(['supplier:id,name', 'warehouse:id,name', 'items.item:id,sku,name']);

        return $this->created(new PurchaseOrderResource($order), 'Purchase order tersimpan.');
    }

    public function submitPurchaseOrder(int $id): JsonResponse
    {
        return $this->run(fn () => DocumentWorkflow::submitPo($id));
    }

    public function approvePurchaseOrder(int $id): JsonResponse
    {
        return $this->run(fn () => DocumentWorkflow::approvePo($id));
    }

    public function rejectPurchaseOrder(Request $request, int $id): JsonResponse
    {
        $reason = $this->reason($request);

        return $this->run(fn () => DocumentWorkflow::rejectPo($id, $reason));
    }

    public function closePurchaseOrder(int $id): JsonResponse
    {
        return $this->run(fn () => DocumentWorkflow::closePo($id));
    }
}
