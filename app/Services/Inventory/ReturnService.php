<?php

namespace App\Services\Inventory;

use App\Models\CustomerReturnItem;
use App\Models\GoodsIssue;
use App\Models\GoodsReceipt;
use App\Models\SupplierReturnItem;
use Illuminate\Support\Facades\DB;

class ReturnService
{
    /**
     * Remaining returnable quantity per (item_id, location_id) for a goods issue.
     *
     * @return array<string, array{item_id:int, location_id:int, unit_id:int, unit_cost:float, remaining:int, quantity:int}>
     */
    public static function remainingForIssue(int $goodsIssueId): array
    {
        $issue = GoodsIssue::with('issueItems')->find($goodsIssueId);

        if (! $issue) {
            return [];
        }

        $returned = CustomerReturnItem::query()
            ->join('customer_returns', 'customer_returns.id', '=', 'customer_return_items.customer_return_id')
            ->where('customer_returns.goods_issue_id', $goodsIssueId)
            ->whereIn('customer_returns.status', ['submitted', 'approved', 'posted'])
            ->groupBy('customer_return_items.item_id', 'customer_return_items.location_id')
            ->selectRaw('customer_return_items.item_id as item_id, customer_return_items.location_id as location_id, SUM(customer_return_items.quantity) as qty')
            ->get()
            ->keyBy(fn ($r) => $r->item_id.':'.$r->location_id);

        $out = [];

        foreach ($issue->issueItems as $item) {
            $key = $item->item_id.':'.$item->location_id;
            $already = (int) ($returned[$key]->qty ?? 0);
            $remaining = max(0, (int) $item->quantity - $already);

            $out[$key] = [
                'item_id' => (int) $item->item_id,
                'location_id' => (int) $item->location_id,
                'unit_id' => (int) $item->unit_id,
                'unit_cost' => (float) ($item->item?->cost ?? 0),
                'quantity' => (int) $item->quantity,
                'remaining' => $remaining,
            ];
        }

        return $out;
    }

    /**
     * Remaining returnable quantity per (item_id, location_id) for a goods receipt.
     *
     * @return array<string, array{item_id:int, location_id:int, unit_id:int, unit_cost:float, remaining:int, quantity:int}>
     */
    public static function remainingForReceipt(int $goodsReceiptId): array
    {
        $receipt = GoodsReceipt::with('receiptItems')->find($goodsReceiptId);

        if (! $receipt) {
            return [];
        }

        $returned = SupplierReturnItem::query()
            ->join('supplier_returns', 'supplier_returns.id', '=', 'supplier_return_items.supplier_return_id')
            ->where('supplier_returns.goods_receipt_id', $goodsReceiptId)
            ->whereIn('supplier_returns.status', ['submitted', 'approved', 'posted'])
            ->groupBy('supplier_return_items.item_id', 'supplier_return_items.location_id')
            ->selectRaw('supplier_return_items.item_id as item_id, supplier_return_items.location_id as location_id, SUM(supplier_return_items.quantity) as qty')
            ->get()
            ->keyBy(fn ($r) => $r->item_id.':'.$r->location_id);

        $out = [];

        foreach ($receipt->receiptItems as $item) {
            $key = $item->item_id.':'.$item->location_id;
            $already = (int) ($returned[$key]->qty ?? 0);
            $remaining = max(0, (int) $item->quantity - $already);

            $out[$key] = [
                'item_id' => (int) $item->item_id,
                'location_id' => (int) $item->location_id,
                'unit_id' => (int) $item->unit_id,
                'unit_cost' => (float) ($item->unit_cost ?? 0),
                'quantity' => (int) $item->quantity,
                'remaining' => $remaining,
            ];
        }

        return $out;
    }

    /**
     * Validate that a return line does not exceed the source document's remaining qty.
     *
     * @param  array<string, array{remaining:int}>  $remaining
     */
    public static function exceedsRemaining(array $remaining, int $itemId, int $locationId, int $qty): bool
    {
        $key = $itemId.':'.$locationId;

        if (! isset($remaining[$key])) {
            return true;
        }

        return $qty > (int) $remaining[$key]['remaining'];
    }

    public static function returnedSumForIssue(int $goodsIssueId): int
    {
        return (int) DB::table('customer_return_items')
            ->join('customer_returns', 'customer_returns.id', '=', 'customer_return_items.customer_return_id')
            ->where('customer_returns.goods_issue_id', $goodsIssueId)
            ->whereIn('customer_returns.status', ['submitted', 'approved', 'posted'])
            ->sum('customer_return_items.quantity');
    }
}
