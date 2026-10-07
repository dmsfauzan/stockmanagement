<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['rack_id', 'code', 'name'])]
class Location extends Model
{
    use HasFactory, SoftDeletes;

    public function rack(): BelongsTo
    {
        return $this->belongsTo(Rack::class);
    }

    public function fullPath(): string
    {
        $rack = $this->relationLoaded('rack') ? $this->rack : $this->rack()->first();
        $zone = $rack?->relationLoaded('zone') ? $rack->zone : $rack?->zone()->first();
        $warehouse = $zone?->relationLoaded('warehouse') ? $zone->warehouse : $zone?->warehouse()->first();

        return implode(' / ', array_filter([
            $warehouse?->name,
            $zone?->name,
            $rack?->name,
            $this->code,
        ]));
    }
}
