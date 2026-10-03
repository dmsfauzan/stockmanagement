<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['item_id', 'warehouse_id', 'location_id', 'transaction_type', 'reference_type', 'reference_id', 'quantity_in', 'quantity_out', 'balance_after', 'batch_number', 'expiry_date', 'notes', 'created_by'])]
class StockMovement extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    public const CREATED_AT = 'created_at';

    protected function casts(): array
    {
        return [
            'quantity_in' => 'integer',
            'quantity_out' => 'integer',
            'balance_after' => 'integer',
            'expiry_date' => 'date',
            'created_at' => 'datetime',
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

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('transaction_type', $type);
    }

    public function scopeInDateRange(Builder $query, $from, $to): Builder
    {
        return $query->whereBetween('created_at', [$from, $to]);
    }
}
