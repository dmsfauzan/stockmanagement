<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['kit_item_id', 'component_item_id', 'quantity', 'created_by'])]
class ItemBom extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    public function kit(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'kit_item_id');
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'component_item_id');
    }
}
