<?php

namespace App\Services\Inventory;

use App\Enums\TrackingType;
use App\Models\Item;
use App\Models\StockLot;
use Illuminate\Support\Collection;

class LotService
{
    public static function receive(
        int $itemId,
        int $warehouseId,
        int $locationId,
        int $quantity,
        float $unitCost = 0,
        ?string $batchNumber = null,
        ?string $serialNumber = null,
        ?string $expiryDate = null,
    ): void {
        if ($quantity <= 0 || ! static::tracksLots($itemId)) {
            return;
        }

        $lot = StockLot::where('item_id', $itemId)
            ->where('warehouse_id', $warehouseId)
            ->where('location_id', $locationId)
            ->where('batch_number', $batchNumber)
            ->where('serial_number', $serialNumber)
            ->lockForUpdate()
            ->first();

        if ($lot) {
            $newQty = (int) $lot->quantity + $quantity;
            $newCost = $newQty > 0
                ? (((float) $lot->quantity * (float) $lot->unit_cost) + ($quantity * $unitCost)) / $newQty
                : $unitCost;

            $lot->update([
                'quantity' => $newQty,
                'unit_cost' => $newCost,
                'expiry_date' => $expiryDate ?? $lot->expiry_date,
            ]);

            return;
        }

        StockLot::create([
            'item_id' => $itemId,
            'warehouse_id' => $warehouseId,
            'location_id' => $locationId,
            'batch_number' => $batchNumber,
            'serial_number' => $serialNumber,
            'expiry_date' => $expiryDate,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
        ]);
    }

    public static function issue(
        int $itemId,
        int $warehouseId,
        int $locationId,
        int $quantity,
        ?string $batchNumber = null,
        ?string $serialNumber = null,
    ): void {
        if ($quantity <= 0 || ! static::tracksLots($itemId)) {
            return;
        }

        $remaining = $quantity;

        // Specific lot/serial requested.
        if ($batchNumber !== null || $serialNumber !== null) {
            $lots = StockLot::where('item_id', $itemId)
                ->where('warehouse_id', $warehouseId)
                ->where('location_id', $locationId)
                ->when($batchNumber !== null, fn ($q) => $q->where('batch_number', $batchNumber))
                ->when($serialNumber !== null, fn ($q) => $q->where('serial_number', $serialNumber))
                ->available()
                ->fefo()
                ->lockForUpdate()
                ->get();

            $remaining = static::consume($lots, $remaining);

            if ($remaining > 0) {
                throw new \RuntimeException('Insufficient lot stock');
            }

            return;
        }

        // FEFO across all available lots at the location.
        $lots = StockLot::where('item_id', $itemId)
            ->where('warehouse_id', $warehouseId)
            ->where('location_id', $locationId)
            ->available()
            ->fefo()
            ->lockForUpdate()
            ->get();

        $remaining = static::consume($lots, $remaining);

        if ($remaining > 0) {
            throw new \RuntimeException('Insufficient lot stock');
        }
    }

    /**
     * @param  Collection<int, StockLot>  $lots
     */
    private static function consume(Collection $lots, int $remaining): int
    {
        foreach ($lots as $lot) {
            if ($remaining <= 0) {
                break;
            }

            $take = min((int) $lot->quantity, $remaining);

            if ($take <= 0) {
                continue;
            }

            $lot->decrement('quantity', $take);
            $remaining -= $take;
        }

        return $remaining;
    }

    /**
     * @return Collection<int, StockLot>
     */
    public static function availableLots(int $itemId, int $warehouseId, ?int $locationId = null): Collection
    {
        return StockLot::where('item_id', $itemId)
            ->where('warehouse_id', $warehouseId)
            ->when($locationId !== null, fn ($q) => $q->where('location_id', $locationId))
            ->available()
            ->fefo()
            ->get();
    }

    public static function tracksLots(int $itemId): bool
    {
        $tracking = Item::whereKey($itemId)->value('tracking_type');

        return $tracking !== null && $tracking !== TrackingType::None->value;
    }
}
