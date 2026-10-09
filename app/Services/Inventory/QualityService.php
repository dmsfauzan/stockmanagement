<?php

namespace App\Services\Inventory;

use App\Enums\QualityStatus;
use App\Enums\TransactionType;
use App\Models\Item;
use App\Models\StockBalance;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Services\Support\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Quality control: hold stock into quarantine, release it back to
 * available, or reject (write off) quarantined stock.
 *
 * On-hand balance is conserved:
 *   available = on_hand − reserved − quarantine
 * Hold/release only move quantity between the available and quarantine
 * buckets. Reject removes quarantined quantity from on-hand entirely and
 * is recorded as an adjustment-out movement for audit.
 */
class QualityService
{
    public static function hold(
        int $itemId,
        int $warehouseId,
        int $locationId,
        int $quantity,
        ?string $reason = null,
        ?string $batchNumber = null,
        ?string $serialNumber = null,
    ): void {
        static::assertPositive($quantity);

        DB::transaction(function () use ($itemId, $warehouseId, $locationId, $quantity, $reason, $batchNumber, $serialNumber): void {
            $balance = static::lockedBalance($itemId, $warehouseId, $locationId);
            $available = (int) $balance->quantity_on_hand - (int) $balance->quantity_reserved - (int) $balance->quantity_quarantine;

            if ($quantity > $available) {
                throw new \RuntimeException('Insufficient available stock to hold.');
            }

            $balance->quantity_quarantine = (int) $balance->quantity_quarantine + $quantity;
            $balance->last_movement_at = now();
            $balance->save();

            static::markLots($itemId, $warehouseId, $locationId, $quantity, QualityStatus::Quarantine, QualityStatus::Good, $batchNumber, $serialNumber);

            AuditLogger::log('QUALITY_HOLD', 'stock_balance', $balance, null, ['quantity' => $quantity, 'reason' => $reason]);
        });
    }

    public static function release(int $itemId, int $warehouseId, int $locationId, int $quantity, ?string $reason = null): void
    {
        static::assertPositive($quantity);

        DB::transaction(function () use ($itemId, $warehouseId, $locationId, $quantity, $reason): void {
            $balance = static::lockedBalance($itemId, $warehouseId, $locationId);

            if ($quantity > (int) $balance->quantity_quarantine) {
                throw new \RuntimeException('Insufficient quarantined stock to release.');
            }

            $balance->quantity_quarantine = (int) $balance->quantity_quarantine - $quantity;
            $balance->last_movement_at = now();
            $balance->save();

            static::markLots($itemId, $warehouseId, $locationId, $quantity, QualityStatus::Good, QualityStatus::Quarantine);

            AuditLogger::log('QUALITY_RELEASE', 'stock_balance', $balance, null, ['quantity' => $quantity, 'reason' => $reason]);
        });
    }

    public static function reject(int $itemId, int $warehouseId, int $locationId, int $quantity, ?string $reason = null): void
    {
        static::assertPositive($quantity);

        DB::transaction(function () use ($itemId, $warehouseId, $locationId, $quantity, $reason): void {
            $balance = static::lockedBalance($itemId, $warehouseId, $locationId);

            if ($quantity > (int) $balance->quantity_quarantine) {
                throw new \RuntimeException('Insufficient quarantined stock to reject.');
            }

            $balance->quantity_quarantine = (int) $balance->quantity_quarantine - $quantity;
            $balance->quantity_on_hand = (int) $balance->quantity_on_hand - $quantity;
            $balance->last_movement_at = now();
            $balance->save();

            static::markLots($itemId, $warehouseId, $locationId, $quantity, QualityStatus::Rejected, QualityStatus::Quarantine, null, null, true);

            $sku = Item::whereKey($itemId)->value('sku') ?? $itemId;

            StockMovement::create([
                'item_id' => $itemId,
                'warehouse_id' => $warehouseId,
                'location_id' => $locationId,
                'transaction_type' => TransactionType::AdjustmentOut->value,
                'reference_type' => StockBalance::class,
                'reference_id' => $balance->id,
                'quantity_in' => 0,
                'quantity_out' => $quantity,
                'balance_after' => (int) $balance->quantity_on_hand,
                'unit_cost' => 0,
                'total_cost' => 0,
                'quality_status' => QualityStatus::Rejected->value,
                'notes' => $sku.' — '.($reason ?? 'Quality reject'),
                'created_by' => auth()->id(),
                'created_at' => now(),
            ]);

            AuditLogger::log('QUALITY_REJECT', 'stock_balance', $balance, null, ['quantity' => $quantity, 'reason' => $reason]);
        });
    }

    public static function quarantineSummary(): array
    {
        $row = StockBalance::query()
            ->where('quantity_quarantine', '>', 0)
            ->selectRaw('COALESCE(SUM(quantity_quarantine),0) as total, COUNT(DISTINCT item_id) as items')
            ->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'items' => (int) ($row->items ?? 0),
        ];
    }

    private static function assertPositive(int $quantity): void
    {
        if ($quantity <= 0) {
            throw new \RuntimeException('Quantity must be positive.');
        }
    }

    private static function lockedBalance(int $itemId, int $warehouseId, int $locationId): StockBalance
    {
        $balance = StockBalance::where('item_id', $itemId)
            ->where('warehouse_id', $warehouseId)
            ->where('location_id', $locationId)
            ->lockForUpdate()
            ->first();

        if (! $balance) {
            $created = StockBalance::create([
                'item_id' => $itemId,
                'warehouse_id' => $warehouseId,
                'location_id' => $locationId,
                'quantity_on_hand' => 0,
                'quantity_reserved' => 0,
                'quantity_quarantine' => 0,
            ]);

            return StockBalance::whereKey($created->id)->lockForUpdate()->firstOrFail();
        }

        return $balance;
    }

    private static function markLots(
        int $itemId,
        int $warehouseId,
        int $locationId,
        int $quantity,
        QualityStatus $to,
        ?QualityStatus $from = null,
        ?string $batchNumber = null,
        ?string $serialNumber = null,
        bool $consume = false,
    ): void {
        if (! LotService::tracksLots($itemId)) {
            return;
        }

        $query = StockLot::where('item_id', $itemId)
            ->where('warehouse_id', $warehouseId)
            ->where('location_id', $locationId)
            ->where('quality_status', $from?->value ?? QualityStatus::Good->value)
            ->where('quantity', '>', 0)
            ->when($batchNumber !== null, fn ($q) => $q->where('batch_number', $batchNumber))
            ->when($serialNumber !== null, fn ($q) => $q->where('serial_number', $serialNumber))
            ->fefo()
            ->lockForUpdate();

        $remaining = $quantity;

        foreach ($query->get() as $lot) {
            if ($remaining <= 0) {
                break;
            }

            $take = min((int) $lot->quantity, $remaining);

            if ($take <= 0) {
                continue;
            }

            $remaining -= $take;

            if ($consume) {
                $lot->decrement('quantity', $take);

                continue;
            }

            if ($take === (int) $lot->quantity) {
                $lot->update(['quality_status' => $to->value]);

                continue;
            }

            $lot->decrement('quantity', $take);

            StockLot::create([
                'item_id' => $lot->item_id,
                'warehouse_id' => $lot->warehouse_id,
                'location_id' => $lot->location_id,
                'batch_number' => $lot->batch_number,
                'serial_number' => $lot->serial_number,
                'expiry_date' => $lot->expiry_date,
                'quantity' => $take,
                'quality_status' => $to->value,
                'unit_cost' => $lot->unit_cost,
            ]);
        }
    }
}
