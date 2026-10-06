<?php

namespace App\Services\Inventory;

use App\Enums\TransactionType;
use App\Models\InventoryValuation;
use App\Models\Item;
use App\Models\StockBalance;
use App\Models\StockMovement;

class LedgerService
{
    public static function record(
        int $itemId,
        int $warehouseId,
        int $locationId,
        TransactionType $type,
        string $referenceType,
        int $referenceId,
        int $qtyIn,
        int $qtyOut,
        ?string $batchNumber = null,
        ?string $expiryDate = null,
        ?string $notes = null,
        ?float $unitCost = null
    ): StockMovement {
        $balance = StockBalance::where('item_id', $itemId)
            ->where('warehouse_id', $warehouseId)
            ->where('location_id', $locationId)
            ->lockForUpdate()
            ->first();

        if (! $balance) {
            $balance = StockBalance::create([
                'item_id' => $itemId,
                'warehouse_id' => $warehouseId,
                'location_id' => $locationId,
                'quantity_on_hand' => 0,
                'quantity_reserved' => 0,
            ]);
        }

        if (in_array($type, [TransactionType::Outgoing, TransactionType::AdjustmentOut, TransactionType::TransferOut], true)) {
            $available = (int) $balance->quantity_on_hand - (int) $balance->quantity_reserved;

            if ($qtyOut > $available) {
                throw new \RuntimeException('Insufficient stock');
            }
        }

        $newOnHand = (int) $balance->quantity_on_hand + $qtyIn - $qtyOut;

        if ($newOnHand < 0) {
            throw new \RuntimeException('Insufficient stock');
        }

        $balance->quantity_on_hand = $newOnHand;
        $balance->last_movement_at = now();
        $balance->save();

        [$movementUnitCost, $movementTotalCost] = static::updateValuation($itemId, $warehouseId, $qtyIn, $qtyOut, $unitCost);

        return StockMovement::create([
            'item_id' => $itemId,
            'warehouse_id' => $warehouseId,
            'location_id' => $locationId,
            'transaction_type' => $type->value,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'quantity_in' => $qtyIn,
            'quantity_out' => $qtyOut,
            'balance_after' => $newOnHand,
            'unit_cost' => $movementUnitCost,
            'total_cost' => $movementTotalCost,
            'batch_number' => $batchNumber,
            'expiry_date' => $expiryDate,
            'notes' => $notes,
            'created_by' => auth()->id(),
            'created_at' => now(),
        ]);
    }

    /**
     * Update the moving-average valuation for the item/warehouse pair.
     *
     * @return array{0: float, 1: float} [unit cost applied to the movement, total cost of the movement]
     */
    protected static function updateValuation(int $itemId, int $warehouseId, int $qtyIn, int $qtyOut, ?float $unitCost): array
    {
        $valuation = InventoryValuation::where('item_id', $itemId)
            ->where('warehouse_id', $warehouseId)
            ->lockForUpdate()
            ->first();

        if (! $valuation) {
            $valuation = InventoryValuation::create([
                'item_id' => $itemId,
                'warehouse_id' => $warehouseId,
                'quantity' => 0,
                'average_cost' => 0,
                'total_value' => 0,
            ]);
        }

        $currentQty = (int) $valuation->quantity;
        $currentAvg = (float) $valuation->average_cost;

        if ($qtyIn > 0) {
            $cost = $unitCost ?? $currentAvg;

            if ($cost <= 0 && $unitCost === null) {
                $cost = (float) (Item::whereKey($itemId)->value('cost') ?? 0);
            }

            $newQty = $currentQty + $qtyIn;
            $newAvg = $newQty > 0 ? (($currentQty * $currentAvg) + ($qtyIn * $cost)) / $newQty : 0;

            $valuation->quantity = $newQty;
            $valuation->average_cost = $newAvg;
            $valuation->total_value = $newQty * $newAvg;
            $valuation->save();

            return [(float) $cost, (float) ($qtyIn * $cost)];
        }

        if ($qtyOut > 0) {
            $cost = $unitCost ?? $currentAvg;
            $newQty = $currentQty - $qtyOut;
            $newValue = max(0, $newQty * $currentAvg);

            $valuation->quantity = $newQty;
            $valuation->total_value = $newValue;
            $valuation->save();

            return [(float) $cost, (float) ($qtyOut * $cost)];
        }

        return [0.0, 0.0];
    }
}
