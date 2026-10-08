<?php

namespace App\Http\Resources\Api;

use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Location
 */
class LocationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $rack = $this->relationLoaded('rack') ? $this->rack : null;
        $zone = $rack && $rack->relationLoaded('zone') ? $rack->zone : null;
        $warehouse = $zone && $zone->relationLoaded('warehouse') ? $zone->warehouse : null;

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'rack' => $rack ? ['id' => $rack->id, 'code' => $rack->code, 'name' => $rack->name] : null,
            'zone' => $zone ? ['id' => $zone->id, 'code' => $zone->code, 'name' => $zone->name] : null,
            'warehouse' => $warehouse ? ['id' => $warehouse->id, 'code' => $warehouse->code, 'name' => $warehouse->name] : null,
        ];
    }
}
