<?php

namespace App\Http\Resources\Api;

use App\Models\StockAdjustment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StockAdjustment
 */
class StockAdjustmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'transaction_date' => $this->transaction_date?->toDateString(),
            'warehouse' => $this->whenLoaded('warehouse', fn () => $this->warehouse ? ['id' => $this->warehouse->id, 'name' => $this->warehouse->name] : null),
            'reason' => $this->reason,
            'notes' => $this->notes,
            'status' => $this->status,
            'created_at' => $this->created_at?->toISOString(),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($i) => [
                'id' => $i->id,
                'item_id' => $i->item_id,
                'sku' => $i->item?->sku,
                'item_name' => $i->item?->name,
                'system_quantity' => (int) $i->system_quantity,
                'actual_quantity' => (int) $i->actual_quantity,
                'difference' => (int) $i->difference,
            ])->values()->all()),
        ];
    }
}
