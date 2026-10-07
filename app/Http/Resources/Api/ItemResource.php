<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
