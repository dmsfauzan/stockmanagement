<?php

namespace App\Models;

use App\Enums\PickStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['number', 'goods_issue_id', 'sales_order_id', 'warehouse_id', 'status', 'assigned_to', 'notes', 'created_by', 'picked_by', 'picked_at', 'packed_by', 'packed_at'])]
class PickList extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => PickStatus::class,
            'picked_at' => 'datetime',
            'packed_at' => 'datetime',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function goodsIssue(): BelongsTo
    {
        return $this->belongsTo(GoodsIssue::class);
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PickListItem::class);
    }
}
