<?php

namespace App\Http\Resources\Api;

use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StockMovement
 *
 * @property string $sku
 * @property string $item_name
 * @property string $warehouse_name
 * @property string $location_code
 * @property string|null $user_name
 */
class StockMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'item_name' => $this->item_name,
            'warehouse' => $this->warehouse_name,
            'location' => $this->location_code,
            'type' => $this->transaction_type,
            'quantity_in' => (int) $this->quantity_in,
            'quantity_out' => (int) $this->quantity_out,
            'balance_after' => (int) $this->balance_after,
            'unit_cost' => (float) $this->unit_cost,
            'total_cost' => (float) $this->total_cost,
            'reference' => trim(($this->reference_type ?? '').'#'.($this->reference_id ?? '')),
            'created_by' => $this->user_name,
            'created_at' => $this->created_at,
        ];
    }
}
