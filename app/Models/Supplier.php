<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['code', 'name', 'contact_person', 'phone', 'email', 'address', 'status', 'lead_time_days', 'payment_terms', 'region'])]
class Supplier extends Model
{
    use HasFactory, SoftDeletes;

    public function primaryItems(): HasMany
    {
        return $this->hasMany(Item::class, 'primary_supplier_id');
    }

    public function itemPrices(): HasMany
    {
        return $this->hasMany(SupplierItemPrice::class);
    }

    public function pricesFor(int $itemId): ?SupplierItemPrice
    {
        return $this->itemPrices()->where('item_id', $itemId)->first();
    }
}
