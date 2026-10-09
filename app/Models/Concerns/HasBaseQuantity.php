<?php

namespace App\Models\Concerns;

use App\Services\Inventory\UnitConversionService;

trait HasBaseQuantity
{
    protected static function bootHasBaseQuantity(): void
    {
        static::saving(function ($model): void {
            if ((float) ($model->conversion_factor ?? 0) <= 0) {
                $model->conversion_factor = UnitConversionService::factor((int) $model->item_id, (int) $model->unit_id);
            }

            if ($model->base_quantity === null) {
                $model->base_quantity = max(0, (int) round((float) $model->quantity * (float) $model->conversion_factor));
            }
        });
    }

    public function baseQuantity(): int
    {
        return (int) ($this->base_quantity ?? $this->quantity);
    }
}
