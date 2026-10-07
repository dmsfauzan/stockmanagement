<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockOpnameResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'opname_date' => $this->opname_date?->toDateString(),
            'warehouse' => $this->whenLoaded('warehouse', fn () => $this->warehouse ? ['id' => $this->warehouse->id, 'name' => $this->warehouse->name] : null),
            'location' => $this->whenLoaded('location', fn () => $this->location ? ['id' => $this->location->id, 'code' => $this->location->code] : null),
            'status' => $this->status,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($i) => [
                'id' => $i->id,
                'item_id' => $i->item_id,
                'sku' => $i->item?->sku,
                'item_name' => $i->item?->name,
                'system_quantity' => (int) $i->system_quantity,
                'physical_quantity' => $i->physical_quantity !== null ? (int) $i->physical_quantity : null,
                'difference' => (int) $i->difference,
                'reason' => $i->reason,
            ])->values()->all()),
        ];
    }
}
