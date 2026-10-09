<?php

namespace App\Services\Inventory;

use App\Models\Item;
use App\Models\ItemUnitConversion;
use Illuminate\Support\Collection;

class UnitConversionService
{
    public static function conversions(int $itemId): Collection
    {
        return ItemUnitConversion::with('unit')
            ->where('item_id', $itemId)
            ->orderByDesc('factor')
            ->get();
    }

    public static function factor(int $itemId, int $unitId): float
    {
        $item = Item::find($itemId);

        if ($item && (int) $item->unit_id === $unitId) {
            return 1.0;
        }

        $factor = ItemUnitConversion::where('item_id', $itemId)
            ->where('unit_id', $unitId)
            ->value('factor');

        return (float) ($factor ?? 1);
    }

    /** @return array{factor: float, base_quantity: int} */
    public static function resolve(int $itemId, int $unitId, int $quantity): array
    {
        $factor = static::factor($itemId, $unitId);

        return [
            'factor' => $factor,
            'base_quantity' => max(0, (int) round($quantity * $factor)),
        ];
    }

    /**
     * Unit ids valid for an item: its base unit plus every registered conversion.
     *
     * @return array<int, int>
     */
    public static function allowedUnitIds(int $itemId): array
    {
        $item = Item::find($itemId);

        if (! $item) {
            return [];
        }

        $ids = [(int) $item->unit_id];

        foreach (ItemUnitConversion::where('item_id', $itemId)->pluck('unit_id') as $unitId) {
            $ids[] = (int) $unitId;
        }

        return array_values(array_unique($ids));
    }

    public static function saveConversion(int $itemId, int $unitId, float $factor): ItemUnitConversion
    {
        if ($factor <= 0) {
            throw new \RuntimeException('Faktor konversi harus lebih dari 0.');
        }

        return ItemUnitConversion::updateOrCreate(
            ['item_id' => $itemId, 'unit_id' => $unitId],
            ['factor' => $factor],
        );
    }
}
