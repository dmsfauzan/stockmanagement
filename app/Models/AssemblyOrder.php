<?php

namespace App\Models;

use App\Enums\AssemblyType;
use App\Enums\TransactionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['number', 'type', 'assembly_date', 'item_id', 'quantity', 'warehouse_id', 'location_id', 'status', 'notes', 'created_by', 'updated_by', 'submitted_by', 'approved_by', 'rejected_by', 'posted_by', 'submitted_at', 'approved_at', 'rejected_at', 'posted_at', 'rejection_reason', 'reversed_at', 'reversed_by', 'reversal_reason'])]
class AssemblyOrder extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => AssemblyType::class,
            'assembly_date' => 'date',
            'quantity' => 'integer',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'posted_at' => 'datetime',
            'reversed_at' => 'datetime',
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

    public function items(): HasMany
    {
        return $this->hasMany(AssemblyOrderItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function statusEnum(): TransactionStatus
    {
        return TransactionStatus::from($this->status);
    }

    public function isPosted(): bool
    {
        return $this->status === TransactionStatus::Posted->value;
    }

    public function isReversed(): bool
    {
        return ! is_null($this->reversed_at);
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }
}
