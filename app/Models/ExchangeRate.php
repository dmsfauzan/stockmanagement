<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['currency_id', 'effective_date', 'rate'])]
class ExchangeRate extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'rate' => 'decimal:8',
        ];
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }
}
