<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\Api\GoodsIssueResource;
use App\Http\Resources\Api\GoodsReceiptResource;
use App\Http\Resources\Api\PurchaseOrderResource;
use App\Http\Resources\Api\SalesOrderResource;
use App\Http\Resources\Api\StockAdjustmentResource;
use App\Http\Resources\Api\StockOpnameResource;
use App\Http\Resources\Api\StockTransferResource;
use App\Models\GoodsIssue;
use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\StockAdjustment;
use App\Models\StockOpname;
use App\Models\StockTransfer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionController extends ApiController
{
    protected function perPage(Request $request): int
    {
        return min(100, max(1, (int) $request->integer('per_page', 15)));
    }

    protected function applyCommon($query, Request $request, ?string $warehouseColumn = 'warehouse_id')
    {
        return $query
            ->when($request->filled('search'), fn ($q) => $q->where('number', 'like', '%'.$request->string('search').'%'))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('warehouse_id') && $warehouseColumn, fn ($q) => $q->where($warehouseColumn, $request->integer('warehouse_id')));
    }

    public function receipts(Request $request): JsonResponse
    {
        $query = $this->applyCommon(
            GoodsReceipt::query()->with(['supplier:id,name', 'warehouse:id,name', 'receiptItems.item:id,sku,name', 'receiptItems.unit:id,code', 'receiptItems.location:id,code']),
            $request
        )->orderByDesc('transaction_date')->orderByDesc('id');

        return $this->paginated(GoodsReceiptResource::collection($query->paginate($this->perPage($request))->withQueryString()));
    }

    public function receipt(GoodsReceipt $receipt): JsonResponse
    {
        $receipt->loadMissing(['supplier:id,name', 'warehouse:id,name', 'receiptItems.item:id,sku,name', 'receiptItems.unit:id,code', 'receiptItems.location:id,code']);

        return $this->ok(new GoodsReceiptResource($receipt));
    }

    public function issues(Request $request): JsonResponse
    {
        $query = $this->applyCommon(
            GoodsIssue::query()->with(['customer:id,name', 'warehouse:id,name', 'issueItems.item:id,sku,name', 'issueItems.unit:id,code', 'issueItems.location:id,code']),
            $request
        )->orderByDesc('transaction_date')->orderByDesc('id');

        return $this->paginated(GoodsIssueResource::collection($query->paginate($this->perPage($request))->withQueryString()));
    }

    public function issue(GoodsIssue $issue): JsonResponse
    {
        $issue->loadMissing(['customer:id,name', 'warehouse:id,name', 'issueItems.item:id,sku,name', 'issueItems.unit:id,code', 'issueItems.location:id,code']);

        return $this->ok(new GoodsIssueResource($issue));
    }

    public function adjustments(Request $request): JsonResponse
    {
        $query = $this->applyCommon(
            StockAdjustment::query()->with(['warehouse:id,name', 'items.item:id,sku,name']),
            $request
        )->orderByDesc('transaction_date')->orderByDesc('id');

        return $this->paginated(StockAdjustmentResource::collection($query->paginate($this->perPage($request))->withQueryString()));
    }

    public function adjustment(StockAdjustment $adjustment): JsonResponse
    {
        $adjustment->loadMissing(['warehouse:id,name', 'items.item:id,sku,name']);

        return $this->ok(new StockAdjustmentResource($adjustment));
    }

    public function opnames(Request $request): JsonResponse
    {
        $query = $this->applyCommon(
            StockOpname::query()->with(['warehouse:id,name', 'location:id,code', 'items.item:id,sku,name']),
            $request
        )->orderByDesc('opname_date')->orderByDesc('id');

        return $this->paginated(StockOpnameResource::collection($query->paginate($this->perPage($request))->withQueryString()));
    }

    public function opname(StockOpname $opname): JsonResponse
    {
        $opname->loadMissing(['warehouse:id,name', 'location:id,code', 'items.item:id,sku,name']);

        return $this->ok(new StockOpnameResource($opname));
    }

    public function transfers(Request $request): JsonResponse
    {
        $query = StockTransfer::query()
            ->with(['fromWarehouse:id,name', 'toWarehouse:id,name', 'items.item:id,sku,name', 'items.unit:id,code'])
            ->when($request->filled('search'), fn ($q) => $q->where('number', 'like', '%'.$request->string('search').'%'))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('warehouse_id'), fn ($q) => $q->where(function ($inner) use ($request): void {
                $inner->where('from_warehouse_id', $request->integer('warehouse_id'))
                    ->orWhere('to_warehouse_id', $request->integer('warehouse_id'));
            }))
            ->orderByDesc('transfer_date')->orderByDesc('id');

        return $this->paginated(StockTransferResource::collection($query->paginate($this->perPage($request))->withQueryString()));
    }

    public function transfer(StockTransfer $transfer): JsonResponse
    {
        $transfer->loadMissing(['fromWarehouse:id,name', 'toWarehouse:id,name', 'items.item:id,sku,name', 'items.unit:id,code']);

        return $this->ok(new StockTransferResource($transfer));
    }

    public function purchaseOrders(Request $request): JsonResponse
    {
        $query = $this->applyCommon(
            PurchaseOrder::query()->with(['supplier:id,name', 'warehouse:id,name', 'items.item:id,sku,name', 'items.unit:id,code']),
            $request
        )->orderByDesc('order_date')->orderByDesc('id');

        return $this->paginated(PurchaseOrderResource::collection($query->paginate($this->perPage($request))->withQueryString()));
    }

    public function purchaseOrder(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $purchaseOrder->loadMissing(['supplier:id,name', 'warehouse:id,name', 'items.item:id,sku,name', 'items.unit:id,code']);

        return $this->ok(new PurchaseOrderResource($purchaseOrder));
    }

    public function salesOrders(Request $request): JsonResponse
    {
        $query = $this->applyCommon(
            SalesOrder::query()->with(['customer:id,name', 'warehouse:id,name', 'items.item:id,sku,name', 'items.unit:id,code']),
            $request
        )->orderByDesc('order_date')->orderByDesc('id');

        return $this->paginated(SalesOrderResource::collection($query->paginate($this->perPage($request))->withQueryString()));
    }

    public function salesOrder(SalesOrder $salesOrder): JsonResponse
    {
        $salesOrder->loadMissing(['customer:id,name', 'warehouse:id,name', 'items.item:id,sku,name', 'items.unit:id,code']);

        return $this->ok(new SalesOrderResource($salesOrder));
    }
}
