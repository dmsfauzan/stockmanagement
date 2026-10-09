<?php

namespace App\Models;

use App\Enums\TransactionStatus;
use App\Models\Concerns\HasApprovalFlow;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['number', 'transaction_date', 'warehouse_id', 'location_id', 'reason', 'notes', 'attachment', 'status', 'created_by', 'updated_by', 'submitted_by', 'approved_by', 'rejected_by', 'posted_by', 'submitted_at', 'approved_at', 'rejected_at', 'posted_at', 'rejection_reason', 'reversed_at', 'reversed_by', 'reversal_reason', 'required_levels', 'current_level', 'approval_total'])]
class StockAdjustment extends Model
{
    use HasApprovalFlow, HasFactory;

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'posted_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }

    public function isReversed(): bool
    {
        return ! is_null($this->reversed_at);
    }

    public function reverser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
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
        return $this->hasMany(StockAdjustmentItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejecter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
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
        return $this->statusEnum()->isPosted();
    }

    public function isEditable(): bool
    {
        return $this->statusEnum()->isEditable();
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }
}
