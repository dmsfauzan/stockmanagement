<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['number', 'request_date', 'required_date', 'requester_id', 'warehouse_id', 'status', 'notes', 'submitted_by', 'approved_by', 'rejected_by', 'converted_purchase_order_id', 'submitted_at', 'approved_at', 'rejected_at', 'rejection_reason', 'created_by'])]
class PurchaseRequisition extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'request_date' => 'date',
            'required_date' => 'date',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'converted_purchase_order_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseRequisitionItem::class);
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'submitted'], true);
    }
}
