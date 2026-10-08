<?php

namespace App\Http\Resources\Api;

use App\Models\StockTransfer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StockTransfer
 */
class StockTransferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'transfer_date' => $this->transfer_date?->toDateString(),
            'from_warehouse' => $this->whenLoaded('fromWarehouse', fn () => $this->fromWarehouse ? ['id' => $this->fromWarehouse->id, 'name' => $this->fromWarehouse->name] : null),
            'to_warehouse' => $this->whenLoaded('toWarehouse', fn () => $this->toWarehouse ? ['id' => $this->toWarehouse->id, 'name' => $this->toWarehouse->name] : null),
            'status' => $this->status,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($i) => [
                'id' => $i->id,
                'item_id' => $i->item_id,
                'sku' => $i->item?->sku,
                'item_name' => $i->item?->name,
                'quantity' => (int) $i->quantity,
            ])->values()->all()),
        ];
    }
}
