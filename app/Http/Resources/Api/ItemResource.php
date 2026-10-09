<?php

namespace App\Http\Resources\Api;

use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Item
 */
class ItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'name' => $this->name,
            'category' => $this->whenLoaded('category', fn () => $this->category ? [
                'id' => $this->category->id,
                'code' => $this->category->code,
                'name' => $this->category->name,
            ] : null),
            'unit' => $this->whenLoaded('unit', fn () => $this->unit ? [
                'id' => $this->unit->id,
                'code' => $this->unit->code,
                'name' => $this->unit->name,
            ] : null),
            'brand' => $this->brand,
            'description' => $this->description,
            'minimum_stock' => (int) $this->minimum_stock,
            'maximum_stock' => (int) $this->maximum_stock,
            'cost' => $this->cost,
            'status' => $this->status,
            'conversions' => $this->whenLoaded('conversions', fn () => $this->conversions->map(fn ($conversion) => [
                'id' => $conversion->id,
                'unit_id' => (int) $conversion->unit_id,
                'unit_code' => $conversion->unit?->code,
                'unit_name' => $conversion->unit?->name,
                'factor' => (float) $conversion->factor,
            ])->values()->all(), null),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
