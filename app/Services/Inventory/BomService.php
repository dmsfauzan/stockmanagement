<?php

namespace App\Services\Inventory;

use App\Models\Item;
use App\Models\ItemBom;
use Illuminate\Support\Collection;

class BomService
{
    /**
     * @return Collection<int, ItemBom>
     */
    public static function components(int $kitItemId): Collection
    {
        return ItemBom::with('component.unit')
            ->where('kit_item_id', $kitItemId)
            ->orderBy('id')
            ->get();
    }

    public static function saveComponent(int $kitItemId, int $componentItemId, int $quantity): ItemBom
    {
        if ($kitItemId === $componentItemId) {
            throw new \RuntimeException('Komponen tidak boleh sama dengan barang kit.');
        }

        if ($quantity <= 0) {
            throw new \RuntimeException('Jumlah komponen harus lebih dari 0.');
        }

        return ItemBom::updateOrCreate(
            ['kit_item_id' => $kitItemId, 'component_item_id' => $componentItemId],
            ['quantity' => $quantity, 'created_by' => auth()->id()],
        );
    }

    public static function removeComponent(int $kitItemId, int $componentItemId): void
    {
        ItemBom::where('kit_item_id', $kitItemId)
            ->where('component_item_id', $componentItemId)
            ->delete();
    }

    /**
     * Component cost total for producing N kits.
     *
     * @return array{total: float, unit: float}
     */
    public static function costForQuantity(int $kitItemId, int $quantity): array
    {
        $components = static::components($kitItemId);
        $total = 0.0;

        foreach ($components as $component) {
            $unitCost = (float) ($component->component?->cost ?? 0);
            $total += $unitCost * (int) $component->quantity * $quantity;
        }

        return [
            'total' => $total,
            'unit' => $quantity > 0 ? $total / $quantity : 0.0,
        ];
    }

    public static function isKit(int $itemId): bool
    {
        return Item::whereKey($itemId)->exists()
            && ItemBom::where('kit_item_id', $itemId)->exists();
    }
}
