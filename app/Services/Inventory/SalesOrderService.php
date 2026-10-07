<?php

namespace App\Services\Inventory;

use App\Enums\SalesOrderStatus;
use App\Models\GoodsIssue;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Services\Support\AuditLogger;

class SalesOrderService
{
    public static function registerFulfillment(GoodsIssue $issue): void
    {
        if (! $issue->sales_order_id) {
            return;
        }

        $order = SalesOrder::whereKey($issue->sales_order_id)->lockForUpdate()->first();

        if (! $order) {
            return;
        }

        if (! in_array($order->status, [SalesOrderStatus::Approved->value, SalesOrderStatus::Partial->value], true)) {
            return;
        }

        $issue->loadMissing('issueItems');

        foreach ($issue->issueItems as $item) {
            $soItem = SalesOrderItem::where('sales_order_id', $order->id)
                ->where('item_id', $item->item_id)
                ->lockForUpdate()
                ->first();

            if (! $soItem) {
                continue;
            }

            $soItem->increment('fulfilled_quantity', (int) $item->quantity);
        }

        static::recomputeStatus($order);

        AuditLogger::log('FULFILL', 'sales_order', $order);
    }

    public static function revertFulfillment(GoodsIssue $issue): void
    {
        if (! $issue->sales_order_id) {
            return;
        }

        $order = SalesOrder::whereKey($issue->sales_order_id)->lockForUpdate()->first();

        if (! $order) {
            return;
        }

        $issue->loadMissing('issueItems');

        foreach ($issue->issueItems as $item) {
            $soItem = SalesOrderItem::where('sales_order_id', $order->id)
                ->where('item_id', $item->item_id)
                ->lockForUpdate()
                ->first();

            if (! $soItem) {
                continue;
            }

            $soItem->update([
                'fulfilled_quantity' => max(0, (int) $soItem->fulfilled_quantity - (int) $item->quantity),
            ]);
        }

        static::recomputeStatus($order, true);

        AuditLogger::log('REVERT_FULFILL', 'sales_order', $order);
    }

    private static function recomputeStatus(SalesOrder $order, bool $allowRevert = false): void
    {
        $order->load('items');

        $allFulfilled = $order->items->isNotEmpty()
            && $order->items->every(fn ($item) => (int) $item->fulfilled_quantity >= (int) $item->quantity);
        $anyFulfilled = $order->items->contains(fn ($item) => (int) $item->fulfilled_quantity > 0);

        if ($allFulfilled) {
            if ($order->status !== SalesOrderStatus::Fulfilled->value) {
                $order->update(['status' => SalesOrderStatus::Fulfilled->value]);
            }

            return;
        }

        if ($anyFulfilled) {
            if ($order->status !== SalesOrderStatus::Partial->value) {
                $order->update(['status' => SalesOrderStatus::Partial->value]);
            }

            return;
        }

        if ($allowRevert && in_array($order->status, [SalesOrderStatus::Partial->value, SalesOrderStatus::Fulfilled->value], true)) {
            $order->update(['status' => SalesOrderStatus::Approved->value]);
        }
    }
}
