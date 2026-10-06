<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryValuation extends Model
{
    use HasFactory;

    protected $fillable = ['item_id', 'warehouse_id', 'quantity', 'average_cost', 'total_value'];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'average_cost' => 'decimal:4',
            'total_value' => 'decimal:2',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
