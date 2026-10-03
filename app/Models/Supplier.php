<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'contact_person', 'phone', 'email', 'address', 'status'])]
class Supplier extends Model
{
    use HasFactory;

    public function primaryItems(): HasMany
    {
        return $this->hasMany(Item::class, 'primary_supplier_id');
    }
}
