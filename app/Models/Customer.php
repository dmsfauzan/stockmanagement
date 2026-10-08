<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['code', 'name', 'type', 'contact_person', 'phone', 'email', 'address', 'status'])]
class Customer extends Model
{
    use HasFactory, SoftDeletes;

    public function itemPrices(): HasMany
    {
        return $this->hasMany(CustomerItemPrice::class);
    }

    public function priceFor(int $itemId, int $quantity = 1): ?CustomerItemPrice
    {
        return $this->itemPrices()
            ->where('item_id', $itemId)
            ->where('min_quantity', '<=', max(1, $quantity))
            ->orderByDesc('min_quantity')
            ->first();
    }
}
