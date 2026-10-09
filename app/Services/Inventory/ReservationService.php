<?php

namespace App\Services\Inventory;

use App\Models\StockBalance;
use App\Models\StockReservation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReservationService
{
    /**
     * @param  array<int, array{item_id:int, warehouse_id:int, location_id:int, quantity:int}>  $rows
     */
    public static function reserve(string $referenceType, int $referenceId, Collection|array $rows): void
    {
        $rows = collect($rows);

        DB::transaction(function () use ($referenceType, $referenceId, $rows): void {
            foreach ($rows as $row) {
                $itemId = (int) $row['item_id'];
                $warehouseId = (int) $row['warehouse_id'];
                $locationId = (int) $row['location_id'];
                $quantity = (int) $row['quantity'];

                if ($quantity <= 0) {
                    continue;
                }

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
                        'quantity_quarantine' => 0,
                    ]);
                }

                $available = (int) $balance->quantity_on_hand - (int) $balance->quantity_reserved - (int) ($balance->quantity_quarantine ?? 0);

                if ($quantity > $available) {
                    throw new \RuntimeException('Insufficient available stock to reserve');
                }

                $already = StockReservation::where('reference_type', $referenceType)
                    ->where('reference_id', $referenceId)
                    ->where('item_id', $itemId)
                    ->where('location_id', $locationId)
                    ->where('status', 'active')
                    ->exists();

                if ($already) {
                    continue;
                }

                StockReservation::create([
                    'item_id' => $itemId,
                    'warehouse_id' => $warehouseId,
                    'location_id' => $locationId,
                    'quantity' => $quantity,
                    'reference_type' => $referenceType,
                    'reference_id' => $referenceId,
                    'status' => 'active',
                    'created_by' => auth()->id(),
                ]);

                $balance->increment('quantity_reserved', $quantity);
                $balance->touch();
            }
        });
    }

    public static function release(string $referenceType, int $referenceId, ?string $toStatus = 'released'): void
    {
        DB::transaction(function () use ($referenceType, $referenceId, $toStatus): void {
            $reservations = StockReservation::where('reference_type', $referenceType)
                ->where('reference_id', $referenceId)
                ->where('status', 'active')
                ->get();

            foreach ($reservations as $reservation) {
                $balance = StockBalance::where('item_id', $reservation->item_id)
                    ->where('warehouse_id', $reservation->warehouse_id)
                    ->where('location_id', $reservation->location_id)
                    ->lockForUpdate()
                    ->first();

                if ($balance) {
                    $balance->decrement('quantity_reserved', min($balance->quantity_reserved, $reservation->quantity));
                    $balance->touch();
                }

                $reservation->update([
                    'status' => $toStatus,
                    'released_at' => now(),
                    'released_by' => auth()->id(),
                ]);
            }
        });
    }

    public static function releaseFor(string $referenceType, int $referenceId, string $toStatus = 'consumed'): void
    {
        static::release($referenceType, $referenceId, $toStatus);
    }
}
