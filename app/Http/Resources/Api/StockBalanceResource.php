<?php

namespace App\Http\Resources\Api;

use App\Models\StockBalance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StockBalance
 *
 * @property string $sku
 * @property string $item_name
 * @property string $warehouse_name
 * @property string|null $location_path
 * @property string|null $location_code
 * @property int|null $min_stock
 * @property int|null $max_stock
 * @property string|null $stock_status
 * @property string|null $last_movement_at
 */
class StockBalanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'item_name' => $this->item_name,
            'warehouse' => $this->warehouse_name,
            'location' => $this->location_path ?? $this->location_code,
            'on_hand' => (int) $this->quantity_on_hand,
            'reserved' => (int) $this->quantity_reserved,
            'quarantine' => (int) ($this->quantity_quarantine ?? 0),
            'available' => (int) $this->quantity_available,
            'min' => (int) ($this->min_stock ?? 0),
            'max' => (int) ($this->max_stock ?? 0),
            'status' => $this->stock_status ?? null,
            'last_movement_at' => $this->last_movement_at,
        ];
    }
}
