<?php

namespace App\Models;

use App\Models\Concerns\HasBaseQuantity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['goods_receipt_id', 'item_id', 'quantity', 'unit_cost', 'landed_cost', 'unit_id', 'conversion_factor', 'base_quantity', 'location_id', 'batch_number', 'serial_number', 'expiry_date', 'notes'])]
class GoodsReceiptItem extends Model
{
    use HasBaseQuantity, HasFactory;

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_cost' => 'decimal:2',
            'expiry_date' => 'date',
            'conversion_factor' => 'decimal:6',
            'base_quantity' => 'integer',
        ];
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class, 'goods_receipt_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
