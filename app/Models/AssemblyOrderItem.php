<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['assembly_order_id', 'item_id', 'role', 'quantity', 'unit_cost'])]
class AssemblyOrderItem extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_cost' => 'decimal:4',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(AssemblyOrder::class, 'assembly_order_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
