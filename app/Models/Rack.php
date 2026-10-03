<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['zone_id', 'code', 'name'])]
class Rack extends Model
{
    use HasFactory;

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }
}
