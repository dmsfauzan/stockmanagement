<?php

namespace App\Services\Inventory;

use App\Enums\OpnameStatus;
use App\Enums\QualityStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\TransferStatus;
use App\Models\CustomerReturn;
use App\Models\GoodsIssue;
use App\Models\GoodsReceipt;
use App\Models\Item;
use App\Models\Location;
use App\Models\Setting;
use App\Models\StockAdjustment;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\StockOpname;
use App\Models\StockTransfer;
use App\Models\SupplierReturn;
use App\Services\Integration\WebhookService;
use App\Services\Support\AuditLogger;
use App\Services\Support\DocumentNumberService;
use App\Services\Support\NotificationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    public static function postGoodsReceipt(GoodsReceipt $receipt): void
    {
        DB::transaction(function () use ($receipt): void {
            $locked = static::lockedReceipt($receipt->getKey());

            if ($locked->status !== TransactionStatus::Approved->value) {
                throw new \RuntimeException('Only approved receipts can be posted.');
            }

            $totalLanded = (float) ($locked->freight_cost ?? 0) + (float) ($locked->other_cost ?? 0);
            $method = (string) ($locked->landed_cost_method ?? 'value');
            $baseTotal = $method === 'quantity'
                ? (float) $locked->receiptItems->sum(fn ($row) => $row->baseQuantity())
                : (float) $locked->receiptItems->sum(fn ($row) => (int) $row->quantity * (float) ($row->unit_cost ?? 0));

            foreach ($locked->receiptItems as $item) {
                $expiry = $item->expiry_date;
                $expiryDate = $expiry instanceof \DateTimeInterface
                    ? $expiry->format('Y-m-d')
                    : ($expiry !== null ? (string) $expiry : null);

                $rowLanded = null;
                $baseQty = $item->baseQuantity();
                $factor = max(0.000001, (float) ($item->conversion_factor ?: 1));

                if ($totalLanded > 0 && $baseTotal > 0) {
                    $rowBase = $method === 'quantity'
                        ? (float) $baseQty
                        : (float) $item->quantity * (float) ($item->unit_cost ?? 0);

                    $rowLanded = $totalLanded * $rowBase / $baseTotal;
                }

                $effectiveCost = (float) ($item->unit_cost ?? 0) / $factor;

                if ($rowLanded !== null) {
                    $effectiveCost += $rowLanded / max(1, $baseQty);
                }

                $quarantine = (bool) ($locked->requires_inspection ?? false);
                $baseQtyIn = $item->baseQuantity();

                LedgerService::record(
                    (int) $item->item_id,
                    (int) $locked->warehouse_id,
                    (int) $item->location_id,
                    TransactionType::Incoming,
                    GoodsReceipt::class,
                    (int) $locked->id,
                    $baseQtyIn,
                    0,
                    $item->batch_number,
                    $expiryDate,
                    $item->notes,
                    $effectiveCost,
                    $item->serial_number ?? null,
                    $quarantine ? QualityStatus::Quarantine->value : QualityStatus::Good->value
                );

                if ($quarantine) {
                    QualityService::hold(
                        (int) $item->item_id,
                        (int) $locked->warehouse_id,
                        (int) $item->location_id,
                        $baseQtyIn,
                        'Inspection required ('.$locked->number.')',
                        $item->batch_number,
                        $item->serial_number ?? null
                    );
                }
            }

            $locked->update([
                'status' => TransactionStatus::Posted->value,
                'posted_by' => auth()->id(),
                'posted_at' => now(),
            ]);

            AuditLogger::log('POST', 'goods_receipt', $locked);

            static::emitWebhook('goods_receipt.posted', GoodsReceipt::class, $locked);

            if ($locked->purchase_order_id) {
                try {
                    PurchaseOrderService::registerReceipt($locked);
                } catch (\Throwable $e) {
                }
            }

            foreach ($locked->receiptItems as $item) {
                try {
                    NotificationService::notifyLowStock((int) $item->item_id, (int) $locked->warehouse_id);
                } catch (\Throwable $e) {
                }

                try {
                    $exp = $item->expiry_date;
                    $expStr = $exp instanceof \DateTimeInterface ? $exp->format('Y-m-d') : ($exp !== null ? (string) $exp : null);
                    if ($expStr !== null && $expStr !== '') {
                        $critical = (int) (Setting::get('expiry.critical_days', 7) ?? 7);
                        $days = (int) Carbon::today()->diffInDays(Carbon::parse($expStr), false);
                        if ($days <= $critical) {
                            $sku = Item::whereKey($item->item_id)->value('sku') ?? $item->item_id;
                            $label = $days < 0 ? 'lewat '.abs($days).' hari' : 'H-'.$days;
                            NotificationService::notifyApprovers('stock.expiring', 'Batch hampir kedaluwarsa', $sku.' batch '.$item->batch_number.' '.$label.' ('.$expStr.')', GoodsReceipt::class, (int) $locked->id);
                        }
                    }
                } catch (\Throwable $e) {
                }
            }
        });
    }

    public static function postGoodsIssue(GoodsIssue $issue): void
    {
        DB::transaction(function () use ($issue): void {
            $locked = static::lockedIssue($issue->getKey());

            if ($locked->status !== TransactionStatus::Approved->value) {
                throw new \RuntimeException('Only approved issues can be posted.');
            }

            ReservationService::releaseFor(GoodsIssue::class, $locked->id, 'consumed');

            foreach ($locked->issueItems as $item) {
                LedgerService::record(
                    (int) $item->item_id,
                    (int) $locked->warehouse_id,
                    (int) $item->location_id,
                    TransactionType::Outgoing,
                    GoodsIssue::class,
                    (int) $locked->id,
                    0,
                    $item->baseQuantity(),
                    $item->batch_number ?? null,
                    null,
                    $item->notes,
                    null,
                    $item->serial_number ?? null
                );
            }

            $locked->update([
                'status' => TransactionStatus::Posted->value,
                'posted_by' => auth()->id(),
                'posted_at' => now(),
            ]);

            SalesOrderService::registerFulfillment($locked);

            AuditLogger::log('POST', 'goods_issue', $locked);

            static::emitWebhook('goods_issue.posted', GoodsIssue::class, $locked);

            foreach ($locked->issueItems as $item) {
                try {
                    NotificationService::notifyLowStock((int) $item->item_id, (int) $locked->warehouse_id);
                } catch (\Throwable $e) {
                }
            }
        });
    }

    public static function postStockAdjustment(StockAdjustment $adjustment): void
    {
        DB::transaction(function () use ($adjustment): void {
            $locked = static::lockedAdjustment($adjustment->getKey());

            if ($locked->status !== TransactionStatus::Approved->value) {
                throw new \RuntimeException('Only approved adjustments can be posted.');
            }

            foreach ($locked->items as $item) {
                $current = StockBalance::where('item_id', $item->item_id)
                    ->where('warehouse_id', $locked->warehouse_id)
                    ->where('location_id', $locked->location_id)
                    ->value('quantity_on_hand');

                $current = (int) ($current ?? 0);
                $delta = (int) $item->actual_quantity - $current;

                if ($delta > 0) {
                    LedgerService::record(
                        (int) $item->item_id,
                        (int) $locked->warehouse_id,
                        (int) $locked->location_id,
                        TransactionType::AdjustmentIn,
                        StockAdjustment::class,
                        (int) $locked->id,
                        $delta,
                        0
                    );
                } elseif ($delta < 0) {
                    LedgerService::record(
                        (int) $item->item_id,
                        (int) $locked->warehouse_id,
                        (int) $locked->location_id,
                        TransactionType::AdjustmentOut,
                        StockAdjustment::class,
                        (int) $locked->id,
                        0,
                        -$delta
                    );
                }
            }

            $locked->update([
                'status' => TransactionStatus::Posted->value,
                'posted_by' => auth()->id(),
                'posted_at' => now(),
            ]);

            AuditLogger::log('POST', 'stock_adjustment', $locked);

            static::emitWebhook('adjustment.posted', StockAdjustment::class, $locked);

            foreach ($locked->items as $item) {
                try {
                    NotificationService::notifyLowStock((int) $item->item_id, (int) $locked->warehouse_id);
                } catch (\Throwable $e) {
                }
            }
        });
    }

    public static function syncOpnameItemDifferences(StockOpname $opname): void
    {
        foreach ($opname->items as $item) {
            $item->difference = $item->physical_quantity === null ? 0 : (int) $item->physical_quantity - (int) $item->system_quantity;
            $item->save();
        }
    }

    public static function completeStockOpname(StockOpname $opname): void
    {
        DB::transaction(function () use ($opname): void {
            $locked = static::lockedOpname($opname->getKey());

            if ($locked->status === OpnameStatus::Completed->value) {
                throw new \RuntimeException('Opname already completed.');
            }

            if (! in_array($locked->status, [OpnameStatus::Submitted->value, OpnameStatus::Approved->value], true)) {
                throw new \RuntimeException('Only submitted or approved opnames can be completed.');
            }

            foreach ($locked->items as $row) {
                if ($row->physical_quantity === null) {
                    throw new \RuntimeException('Opname belum lengkap');
                }
            }

            static::syncOpnameItemDifferences($locked);
            $locked->refresh();
            $locked->load('items');

            $diffItems = $locked->items->filter(fn ($r) => (int) $r->difference !== 0)->values();

            if ($diffItems->isEmpty()) {
                $locked->update(['status' => OpnameStatus::Completed->value]);
                AuditLogger::log('COMPLETE', 'stock_opname', $locked);

                static::emitWebhook('stock_opname.completed', StockOpname::class, $locked);

                return;
            }

            $adjustment = StockAdjustment::create([
                'number' => DocumentNumberService::generate('ADJ'),
                'transaction_date' => $locked->opname_date,
                'warehouse_id' => $locked->warehouse_id,
                'location_id' => $locked->location_id ?? Location::whereHas('rack.zone', fn ($q) => $q->where('warehouse_id', $locked->warehouse_id))->value('id'),
                'reason' => 'Opname Adjustment',
                'status' => TransactionStatus::Approved->value,
                'created_by' => $locked->approved_by ?? $locked->submitted_by ?? $locked->created_by,
                'approved_by' => $locked->approved_by ?? $locked->submitted_by ?? $locked->created_by,
                'approved_at' => now(),
            ]);

            foreach ($diffItems as $row) {
                $actual = (int) $row->physical_quantity;
                $system = (int) $row->system_quantity;
                $adjustment->items()->create([
                    'item_id' => $row->item_id,
                    'system_quantity' => $system,
                    'actual_quantity' => $actual,
                    'difference' => $actual - $system,
                    'notes' => $row->reason ?? $row->notes,
                ]);
            }

            static::postStockAdjustment($adjustment->fresh('items'));

            $locked->update([
                'status' => OpnameStatus::Completed->value,
                'stock_adjustment_id' => $adjustment->id,
            ]);

            AuditLogger::log('COMPLETE', 'stock_opname', $locked);

            static::emitWebhook('stock_opname.completed', StockOpname::class, $locked);
        });
    }

    public static function dispatchStockTransfer(StockTransfer $transfer): void
    {
        DB::transaction(function () use ($transfer): void {
            $locked = static::lockedTransfer($transfer->getKey());

            if ($locked->status !== TransferStatus::Approved->value) {
                throw new \RuntimeException('Only approved transfers can be dispatched.');
            }

            ReservationService::releaseFor(StockTransfer::class, $locked->id, 'consumed');

            foreach ($locked->items as $item) {
                LedgerService::record(
                    (int) $item->item_id,
                    (int) $locked->from_warehouse_id,
                    (int) $locked->from_location_id,
                    TransactionType::TransferOut,
                    StockTransfer::class,
                    (int) $locked->id,
                    0,
                    $item->baseQuantity(),
                    null,
                    null,
                    $item->notes
                );
            }

            $locked->update([
                'status' => TransferStatus::InTransit->value,
                'shipped_by' => auth()->id(),
                'shipped_at' => now(),
            ]);

            AuditLogger::log('DISPATCH', 'stock_transfer', $locked);

            static::emitWebhook('transfer.dispatched', StockTransfer::class, $locked);
        });
    }

    public static function receiveStockTransfer(StockTransfer $transfer): void
    {
        DB::transaction(function () use ($transfer): void {
            $locked = static::lockedTransfer($transfer->getKey());

            if ($locked->status !== TransferStatus::InTransit->value) {
                throw new \RuntimeException('Only in-transit transfers can be received.');
            }

            foreach ($locked->items as $item) {
                LedgerService::record(
                    (int) $item->item_id,
                    (int) $locked->to_warehouse_id,
                    (int) $locked->to_location_id,
                    TransactionType::TransferIn,
                    StockTransfer::class,
                    (int) $locked->id,
                    $item->baseQuantity(),
                    0,
                    null,
                    null,
                    $item->notes
                );
            }

            $locked->update([
                'status' => TransferStatus::Received->value,
                'received_by' => auth()->id(),
                'received_at' => now(),
            ]);

            AuditLogger::log('RECEIVE', 'stock_transfer', $locked);

            static::emitWebhook('transfer.received', StockTransfer::class, $locked);

            foreach ($locked->items as $item) {
                try {
                    NotificationService::notifyLowStock((int) $item->item_id, (int) $locked->to_warehouse_id);
                } catch (\Throwable $e) {
                }
            }
        });
    }

    public static function reverseGoodsReceipt(GoodsReceipt $receipt, string $reason): void
    {
        DB::transaction(function () use ($receipt, $reason): void {
            $locked = static::lockedReceipt($receipt->getKey());
            $locked->loadMissing('receiptItems');

            if ($locked->status !== TransactionStatus::Posted->value) {
                throw new \RuntimeException('Only posted receipts can be reversed.');
            }

            if (! is_null($locked->reversed_at)) {
                throw new \RuntimeException('Transaction already reversed');
            }

            foreach ($locked->receiptItems as $item) {
                $expiry = $item->expiry_date;
                $expiryDate = $expiry instanceof \DateTimeInterface
                    ? $expiry->format('Y-m-d')
                    : ($expiry !== null ? (string) $expiry : null);

                LedgerService::record(
                    (int) $item->item_id,
                    (int) $locked->warehouse_id,
                    (int) $item->location_id,
                    TransactionType::Outgoing,
                    GoodsReceipt::class,
                    (int) $locked->id,
                    0,
                    $item->baseQuantity(),
                    $item->batch_number,
                    $expiryDate,
                    "Reversal of {$locked->number} — {$reason}"
                );
            }

            $locked->update([
                'reversed_at' => now(),
                'reversed_by' => auth()->id(),
                'reversal_reason' => $reason,
            ]);

            if ($locked->purchase_order_id) {
                try {
                    PurchaseOrderService::revertReceipt($locked);
                } catch (\Throwable $e) {
                }
            }

            AuditLogger::log('REVERSE', 'goods_receipt', $locked);
        });
    }

    public static function reverseGoodsIssue(GoodsIssue $issue, string $reason): void
    {
        DB::transaction(function () use ($issue, $reason): void {
            $locked = static::lockedIssue($issue->getKey());
            $locked->loadMissing('issueItems');

            if ($locked->status !== TransactionStatus::Posted->value) {
                throw new \RuntimeException('Only posted issues can be reversed.');
            }

            if (! is_null($locked->reversed_at)) {
                throw new \RuntimeException('Transaction already reversed');
            }

            foreach ($locked->issueItems as $item) {
                LedgerService::record(
                    (int) $item->item_id,
                    (int) $locked->warehouse_id,
                    (int) $item->location_id,
                    TransactionType::Incoming,
                    GoodsIssue::class,
                    (int) $locked->id,
                    $item->baseQuantity(),
                    0,
                    null,
                    null,
                    "Reversal of {$locked->number} — {$reason}"
                );
            }

            $locked->update([
                'reversed_at' => now(),
                'reversed_by' => auth()->id(),
                'reversal_reason' => $reason,
            ]);

            SalesOrderService::revertFulfillment($locked);

            AuditLogger::log('REVERSE', 'goods_issue', $locked);
        });
    }

    public static function reverseStockAdjustment(StockAdjustment $adjustment, string $reason): void
    {
        DB::transaction(function () use ($adjustment, $reason): void {
            $locked = static::lockedAdjustment($adjustment->getKey());

            if ($locked->status !== TransactionStatus::Posted->value) {
                throw new \RuntimeException('Only posted adjustments can be reversed.');
            }

            if (! is_null($locked->reversed_at)) {
                throw new \RuntimeException('Transaction already reversed');
            }

            $movements = StockMovement::where('reference_type', StockAdjustment::class)
                ->where('reference_id', $locked->id)
                ->lockForUpdate()
                ->get();

            foreach ($movements as $movement) {
                $qtyIn = (int) $movement->quantity_in;
                $qtyOut = (int) $movement->quantity_out;

                if ($qtyIn > 0) {
                    LedgerService::record(
                        (int) $movement->item_id,
                        (int) $movement->warehouse_id,
                        (int) $movement->location_id,
                        TransactionType::AdjustmentOut,
                        StockAdjustment::class,
                        (int) $locked->id,
                        0,
                        $qtyIn,
                        null,
                        null,
                        "Reversal of {$locked->number} — {$reason}"
                    );
                } elseif ($qtyOut > 0) {
                    LedgerService::record(
                        (int) $movement->item_id,
                        (int) $movement->warehouse_id,
                        (int) $movement->location_id,
                        TransactionType::AdjustmentIn,
                        StockAdjustment::class,
                        (int) $locked->id,
                        $qtyOut,
                        0,
                        null,
                        null,
                        "Reversal of {$locked->number} — {$reason}"
                    );
                }
            }

            $locked->update([
                'reversed_at' => now(),
                'reversed_by' => auth()->id(),
                'reversal_reason' => $reason,
            ]);

            AuditLogger::log('REVERSE', 'stock_adjustment', $locked);
        });
    }

    private static function lockedTransfer(int $id): StockTransfer
    {
        return StockTransfer::with('items')->whereKey($id)->lockForUpdate()->firstOrFail();
    }

    private static function lockedCustomerReturn(int $id): CustomerReturn
    {
        return CustomerReturn::with('items')->whereKey($id)->lockForUpdate()->firstOrFail();
    }

    private static function lockedSupplierReturn(int $id): SupplierReturn
    {
        return SupplierReturn::with('items')->whereKey($id)->lockForUpdate()->firstOrFail();
    }

    public static function postCustomerReturn(CustomerReturn $ret): void
    {
        DB::transaction(function () use ($ret): void {
            $locked = static::lockedCustomerReturn($ret->getKey());

            if ($locked->status !== TransactionStatus::Approved->value) {
                throw new \RuntimeException('Only approved customer returns can be posted.');
            }

            foreach ($locked->items as $item) {
                LedgerService::record(
                    (int) $item->item_id,
                    (int) $locked->warehouse_id,
                    (int) $item->location_id,
                    TransactionType::ReturnIn,
                    CustomerReturn::class,
                    (int) $locked->id,
                    $item->baseQuantity(),
                    0,
                    $item->batch_number,
                    null,
                    $item->notes,
                    (float) ($item->unit_cost ?? 0),
                    $item->serial_number ?? null
                );
            }

            $locked->update([
                'status' => TransactionStatus::Posted->value,
                'posted_by' => auth()->id(),
                'posted_at' => now(),
            ]);

            static::emitWebhook('customer_return.posted', CustomerReturn::class, $locked);

            AuditLogger::log('POST', 'customer_return', $locked);
        });
    }

    public static function postSupplierReturn(SupplierReturn $ret): void
    {
        DB::transaction(function () use ($ret): void {
            $locked = static::lockedSupplierReturn($ret->getKey());

            if ($locked->status !== TransactionStatus::Approved->value) {
                throw new \RuntimeException('Only approved supplier returns can be posted.');
            }

            foreach ($locked->items as $item) {
                LedgerService::record(
                    (int) $item->item_id,
                    (int) $locked->warehouse_id,
                    (int) $item->location_id,
                    TransactionType::ReturnOut,
                    SupplierReturn::class,
                    (int) $locked->id,
                    0,
                    $item->baseQuantity(),
                    null,
                    null,
                    $item->notes,
                    null,
                    $item->serial_number ?? null
                );
            }

            $locked->update([
                'status' => TransactionStatus::Posted->value,
                'posted_by' => auth()->id(),
                'posted_at' => now(),
            ]);

            static::emitWebhook('supplier_return.posted', SupplierReturn::class, $locked);

            AuditLogger::log('POST', 'supplier_return', $locked);
        });
    }

    public static function reverseCustomerReturn(CustomerReturn $ret, string $reason): void
    {
        DB::transaction(function () use ($ret, $reason): void {
            $locked = static::lockedCustomerReturn($ret->getKey());
            $locked->loadMissing('items');

            if ($locked->status !== TransactionStatus::Posted->value) {
                throw new \RuntimeException('Only posted customer returns can be reversed.');
            }

            if (! is_null($locked->reversed_at)) {
                throw new \RuntimeException('Transaction already reversed');
            }

            foreach ($locked->items as $item) {
                LedgerService::record(
                    (int) $item->item_id,
                    (int) $locked->warehouse_id,
                    (int) $item->location_id,
                    TransactionType::ReturnOut,
                    CustomerReturn::class,
                    (int) $locked->id,
                    0,
                    $item->baseQuantity(),
                    $item->batch_number,
                    null,
                    "Reversal of {$locked->number} — {$reason}"
                );
            }

            $locked->update([
                'reversed_at' => now(),
                'reversed_by' => auth()->id(),
                'reversal_reason' => $reason,
            ]);

            AuditLogger::log('REVERSE', 'customer_return', $locked);
        });
    }

    public static function reverseSupplierReturn(SupplierReturn $ret, string $reason): void
    {
        DB::transaction(function () use ($ret, $reason): void {
            $locked = static::lockedSupplierReturn($ret->getKey());
            $locked->loadMissing('items');

            if ($locked->status !== TransactionStatus::Posted->value) {
                throw new \RuntimeException('Only posted supplier returns can be reversed.');
            }

            if (! is_null($locked->reversed_at)) {
                throw new \RuntimeException('Transaction already reversed');
            }

            foreach ($locked->items as $item) {
                LedgerService::record(
                    (int) $item->item_id,
                    (int) $locked->warehouse_id,
                    (int) $item->location_id,
                    TransactionType::ReturnIn,
                    SupplierReturn::class,
                    (int) $locked->id,
                    $item->baseQuantity(),
                    0,
                    $item->batch_number,
                    null,
                    "Reversal of {$locked->number} — {$reason}"
                );
            }

            $locked->update([
                'reversed_at' => now(),
                'reversed_by' => auth()->id(),
                'reversal_reason' => $reason,
            ]);

            AuditLogger::log('REVERSE', 'supplier_return', $locked);
        });
    }

    public static function lockedOpname(int $id): StockOpname
    {
        return StockOpname::with('items')->whereKey($id)->lockForUpdate()->firstOrFail();
    }

    private static function lockedAdjustment(int $id): StockAdjustment
    {
        return StockAdjustment::with('items')->whereKey($id)->lockForUpdate()->firstOrFail();
    }

    private static function lockedReceipt(int $id): GoodsReceipt
    {
        return GoodsReceipt::whereKey($id)->lockForUpdate()->firstOrFail();
    }

    private static function lockedIssue(int $id): GoodsIssue
    {
        return GoodsIssue::whereKey($id)->lockForUpdate()->firstOrFail();
    }

    private static function emitWebhook(string $event, string $modelClass, mixed $model): void
    {
        try {
            WebhookService::emit($event, [
                'event' => $event,
                'reference' => [
                    'type' => class_basename($modelClass),
                    'id' => $model->getKey(),
                    'number' => $model->number ?? null,
                ],
                'warehouse_id' => $model->warehouse_id ?? null,
                'status' => $model->status ?? null,
                'occurred_at' => now()->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
        }
    }
}
