<?php

namespace App\Services\Inventory;

use App\Enums\PurchaseOrderStatus;
use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Services\Support\AuditLogger;

class PurchaseOrderService
{
    public static function registerReceipt(GoodsReceipt $receipt): void
    {
        if (! $receipt->purchase_order_id) {
            return;
        }

        $order = PurchaseOrder::whereKey($receipt->purchase_order_id)->lockForUpdate()->first();

        if (! $order) {
            return;
        }

        if (! in_array($order->status, [PurchaseOrderStatus::Approved->value, PurchaseOrderStatus::Partial->value], true)) {
            return;
        }

        $receipt->loadMissing('receiptItems');

        foreach ($receipt->receiptItems as $item) {
            $poItem = PurchaseOrderItem::where('purchase_order_id', $order->id)
                ->where('item_id', $item->item_id)
                ->lockForUpdate()
                ->first();

            if (! $poItem) {
                continue;
            }

            $poItem->increment('received_quantity', (int) $item->quantity);
        }

        static::recomputeStatus($order);

        AuditLogger::log('RECEIVE', 'purchase_order', $order);
    }

    public static function revertReceipt(GoodsReceipt $receipt): void
    {
        if (! $receipt->purchase_order_id) {
            return;
        }

        $order = PurchaseOrder::whereKey($receipt->purchase_order_id)->lockForUpdate()->first();

        if (! $order) {
            return;
        }

        $receipt->loadMissing('receiptItems');

        foreach ($receipt->receiptItems as $item) {
            $poItem = PurchaseOrderItem::where('purchase_order_id', $order->id)
                ->where('item_id', $item->item_id)
                ->lockForUpdate()
                ->first();

            if (! $poItem) {
                continue;
            }

            $poItem->update([
                'received_quantity' => max(0, (int) $poItem->received_quantity - (int) $item->quantity),
            ]);
        }

        static::recomputeStatus($order, true);

        AuditLogger::log('REVERT', 'purchase_order', $order);
    }

    private static function recomputeStatus(PurchaseOrder $order, bool $allowRevert = false): void
    {
        $order->refresh()->load('items');

        $allReceived = $order->items->isNotEmpty()
            && $order->items->every(fn ($item) => (int) $item->received_quantity >= (int) $item->quantity);
        $anyReceived = $order->items->contains(fn ($item) => (int) $item->received_quantity > 0);

        if ($allReceived) {
            if ($order->status !== PurchaseOrderStatus::Received->value) {
                $order->update(['status' => PurchaseOrderStatus::Received->value]);
            }

            return;
        }

        if ($anyReceived) {
            if ($order->status !== PurchaseOrderStatus::Partial->value) {
                $order->update(['status' => PurchaseOrderStatus::Partial->value]);
            }

            return;
        }

        if ($allowRevert && in_array($order->status, [PurchaseOrderStatus::Partial->value, PurchaseOrderStatus::Received->value], true)) {
            $order->update(['status' => PurchaseOrderStatus::Approved->value]);
        }
    }
}
