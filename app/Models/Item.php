<?php

namespace App\Models;

use App\Enums\TrackingType;
use App\Services\Support\ImageService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['sku', 'barcode', 'name', 'category_id', 'unit_id', 'brand', 'description', 'image_path', 'minimum_stock', 'maximum_stock', 'cost', 'price', 'primary_supplier_id', 'status', 'tracking_type', 'created_by', 'updated_by'])]
class Item extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'minimum_stock' => 'integer',
            'maximum_stock' => 'integer',
            'cost' => 'decimal:2',
            'price' => 'decimal:2',
            'tracking_type' => TrackingType::class,
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

    public function conversions(): HasMany
    {
        return $this->hasMany(ItemUnitConversion::class);
    }

    public function bomComponents(): HasMany
    {
        return $this->hasMany(ItemBom::class, 'kit_item_id');
    }

    public function customerPrices(): HasMany
    {
        return $this->hasMany(CustomerItemPrice::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function imageUrl(): ?string
    {
        return app(ImageService::class)->url($this->image_path);
    }

    public function thumbUrl(): ?string
    {
        return app(ImageService::class)->thumbUrl($this->image_path);
    }
}
