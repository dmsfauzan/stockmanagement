<?php

namespace App\Models;

use App\Models\Concerns\HasBaseQuantity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['supplier_return_id', 'item_id', 'quantity', 'unit_id', 'conversion_factor', 'base_quantity', 'location_id', 'unit_cost', 'batch_number', 'serial_number', 'notes'])]
class SupplierReturnItem extends Model
{
    use HasBaseQuantity, HasFactory;

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_cost' => 'decimal:2',
            'conversion_factor' => 'decimal:6',
            'base_quantity' => 'integer',
        ];
    }

    public function supplierReturn(): BelongsTo
    {
        return $this->belongsTo(SupplierReturn::class);
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
