<?php

namespace App\Services\Inventory;

use App\Models\StockMovement;
use Illuminate\Support\Collection;

/**
 * End-to-end lot/serial traceability: follows a batch number or serial
 * number across the append-only ledger so a recall can see every
 * receipt, transfer, issue and return touching it.
 */
class TraceabilityService
{
    public static function forBatch(string $batchNumber): array
    {
        return static::build('batch', $batchNumber);
    }

    public static function forSerial(string $serialNumber): array
    {
        return static::build('serial', $serialNumber);
    }

    /**
     * @return array{type:string, value:string, events:array<int, array<string, mixed>>, summary:array<string, int>}
     */
    protected static function build(string $type, string $value): array
    {
        $column = $type === 'serial' ? 'serial_number' : 'batch_number';

        $movements = StockMovement::query()
            ->with(['item:id,sku,name', 'warehouse:id,name', 'location:id,code', 'creator:id,name'])
            ->where($column, $value)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $events = $movements->map(fn (StockMovement $movement) => static::present($movement))->all();

        $summary = [
            'events' => count($events),
            'in' => (int) $movements->sum('quantity_in'),
            'out' => (int) $movements->sum('quantity_out'),
            'balance' => (int) $movements->sum('quantity_in') - (int) $movements->sum('quantity_out'),
        ];

        return [
            'type' => $type,
            'value' => $value,
            'events' => $events,
            'summary' => $summary,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function present(StockMovement $movement): array
    {
        [$label, $number] = static::reference($movement);

        return [
            'id' => $movement->id,
            'date' => $movement->created_at?->toIso8601String(),
            'transaction_type' => $movement->transaction_type,
            'quantity_in' => (int) $movement->quantity_in,
            'quantity_out' => (int) $movement->quantity_out,
            'balance_after' => (int) $movement->balance_after,
            'quality_status' => $movement->quality_status?->value,
            'sku' => $movement->item?->sku,
            'item_name' => $movement->item?->name,
            'warehouse' => $movement->warehouse?->name,
            'location' => $movement->location?->code,
            'user' => $movement->creator?->name,
            'reference_label' => $label,
            'reference_number' => $number,
            'notes' => $movement->notes,
        ];
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    protected static function reference(StockMovement $movement): array
    {
        $class = (string) $movement->reference_type;
        $label = class_basename($class);
        $number = null;

        if ($class !== '' && class_exists($class) && $movement->reference_id) {
            $label = class_basename($class);
            $number = $class::query()->whereKey($movement->reference_id)->value('number');
        }

        return [$label, $number];
    }

    /**
     * @return Collection<int, StockMovement>
     */
    public static function movementsFor(string $column, string $value): Collection
    {
        if (! in_array($column, ['batch_number', 'serial_number'], true)) {
            return collect();
        }

        return StockMovement::query()->where($column, $value)->get();
    }
}
