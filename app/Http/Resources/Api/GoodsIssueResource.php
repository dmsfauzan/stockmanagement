<?php

namespace App\Http\Resources\Api;

use App\Models\GoodsIssue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin GoodsIssue
 */
class GoodsIssueResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'transaction_date' => $this->transaction_date?->toDateString(),
            'customer' => $this->whenLoaded('customer', fn () => $this->customer ? ['id' => $this->customer->id, 'name' => $this->customer->name] : null),
            'warehouse' => $this->whenLoaded('warehouse', fn () => $this->warehouse ? ['id' => $this->warehouse->id, 'name' => $this->warehouse->name] : null),
            'destination' => $this->destination,
            'sales_order_number' => $this->sales_order_number,
            'status' => $this->status,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
            'items' => $this->whenLoaded('issueItems', fn () => $this->issueItems->map(fn ($i) => [
                'id' => $i->id,
                'item_id' => $i->item_id,
                'sku' => $i->item?->sku,
                'item_name' => $i->item?->name,
                'quantity' => (int) $i->quantity,
            ])->values()->all()),
        ];
    }
}
