<?php

namespace App\Http\Resources\Api;

use App\Models\SalesOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SalesOrder
 */
class SalesOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'order_date' => $this->order_date?->toDateString(),
            'expected_date' => $this->expected_date?->toDateString(),
            'customer' => $this->whenLoaded('customer', fn () => $this->customer ? ['id' => $this->customer->id, 'name' => $this->customer->name] : null),
            'warehouse' => $this->whenLoaded('warehouse', fn () => $this->warehouse ? ['id' => $this->warehouse->id, 'name' => $this->warehouse->name] : null),
            'status' => $this->status,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($i) => [
                'id' => $i->id,
                'item_id' => $i->item_id,
                'sku' => $i->item?->sku,
                'item_name' => $i->item?->name,
                'quantity' => (int) $i->quantity,
                'fulfilled_quantity' => (int) $i->fulfilled_quantity,
                'remaining' => $i->remaining(),
                'unit_id' => $i->unit_id,
                'unit_code' => $i->unit?->code,
                'unit_price' => $i->unit_price,
            ])->values()->all()),
        ];
    }
}
