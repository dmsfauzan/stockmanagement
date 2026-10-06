<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['sku', 'barcode', 'name', 'category_id', 'unit_id', 'brand', 'description', 'minimum_stock', 'maximum_stock', 'cost', 'primary_supplier_id', 'status', 'created_by', 'updated_by'])]
class Item extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'minimum_stock' => 'integer',
            'maximum_stock' => 'integer',
            'cost' => 'decimal:2',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function primarySupplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'primary_supplier_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
