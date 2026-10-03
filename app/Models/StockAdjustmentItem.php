<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['stock_adjustment_id', 'item_id', 'system_quantity', 'actual_quantity', 'difference', 'notes'])]
class StockAdjustmentItem extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'system_quantity' => 'integer',
            'actual_quantity' => 'integer',
            'difference' => 'integer',
        ];
    }

    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(StockAdjustment::class, 'stock_adjustment_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
