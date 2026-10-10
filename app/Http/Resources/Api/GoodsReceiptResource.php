<?php

namespace App\Http\Resources\Api;

use App\Models\GoodsReceipt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin GoodsReceipt
 */
class GoodsReceiptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'transaction_date' => $this->transaction_date?->toDateString(),
            'supplier' => $this->whenLoaded('supplier', fn () => $this->supplier ? ['id' => $this->supplier->id, 'name' => $this->supplier->name] : null),
            'warehouse' => $this->whenLoaded('warehouse', fn () => $this->warehouse ? ['id' => $this->warehouse->id, 'name' => $this->warehouse->name] : null),
            'po_number' => $this->po_number,
            'delivery_note' => $this->delivery_note,
            'requires_inspection' => (bool) ($this->requires_inspection ?? false),
            'status' => $this->status,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
            'items' => $this->whenLoaded('receiptItems', fn () => $this->receiptItems->map(fn ($i) => [
                'id' => $i->id,
                'item_id' => $i->item_id,
                'sku' => $i->item?->sku,
                'item_name' => $i->item?->name,
                'quantity' => (int) $i->quantity,
                'unit_cost' => $i->unit_cost,
                'unit_id' => $i->unit_id,
                'unit_code' => $i->unit?->code,
                'location_id' => $i->location_id,
                'location_code' => $i->location?->code,
                'batch_number' => $i->batch_number,
                'expiry_date' => $i->expiry_date?->toDateString(),
            ])->values()->all()),
        ];
    }
}
