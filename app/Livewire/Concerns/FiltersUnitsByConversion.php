<?php

namespace App\Livewire\Concerns;

use App\Models\Setting;
use App\Services\Inventory\UnitConversionService;

trait FiltersUnitsByConversion
{
    /**
     * Map of item_id => allowed unit ids (base + registered conversions).
     * Returns an empty array when the "restrict units" setting is disabled,
     * which tells the view to show every unit.
     *
     * @return array<int, array<int, int>>
     */
    protected function conversionUnitMap(): array
    {
        $restrict = (string) (Setting::get('inventory.restrict_units', '1') ?? '1');

        if ($restrict !== '1') {
            return [];
        }

        $map = [];

        foreach (collect($this->items)->pluck('item_id')->filter()->unique() as $itemId) {
            $map[(int) $itemId] = UnitConversionService::allowedUnitIds((int) $itemId);
        }

        return $map;
    }
}
