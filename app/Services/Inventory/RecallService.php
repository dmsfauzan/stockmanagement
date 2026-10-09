<?php

namespace App\Services\Inventory;

use App\Models\BatchRecall;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Services\Support\AuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Batch / serial recall: given a batch or serial number, find the stock
 * still on hand and every document it passed through, then register a
 * recall so the batch is flagged until lifted.
 */
class RecallService
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function affectedStock(string $column, string $value): Collection
    {
        if (! in_array($column, ['batch_number', 'serial_number'], true)) {
            return collect();
        }

        return StockLot::query()
            ->with(['item:id,sku,name', 'warehouse:id,name', 'location:id,code'])
            ->where($column, $value)
            ->where('quantity', '>', 0)
            ->get()
            ->map(fn (StockLot $lot) => [
                'lot_id' => $lot->id,
                'sku' => $lot->item?->sku,
                'item_name' => $lot->item?->name,
                'warehouse' => $lot->warehouse?->name,
                'location' => $lot->location?->code,
                'quantity' => (int) $lot->quantity,
                'quality_status' => $lot->quality_status?->value,
            ]);
    }

    /**
     * Documents (references) touched by the batch/serial.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function affectedDocuments(string $column, string $value): Collection
    {
        if (! in_array($column, ['batch_number', 'serial_number'], true)) {
            return collect();
        }

        return StockMovement::query()
            ->with(['item:id,sku,name', 'warehouse:id,name'])
            ->where($column, $value)
            ->orderBy('created_at')
            ->get()
            ->map(function (StockMovement $m) {
                $class = (string) $m->reference_type;
                $number = ($class !== '' && class_exists($class) && $m->reference_id)
                    ? $class::query()->whereKey($m->reference_id)->value('number')
                    : null;

                return [
                    'date' => $m->created_at?->toIso8601String(),
                    'transaction_type' => $m->transaction_type,
                    'reference' => $number ?? class_basename($class),
                    'sku' => $m->item?->sku,
                    'warehouse' => $m->warehouse?->name,
                    'quantity_in' => (int) $m->quantity_in,
                    'quantity_out' => (int) $m->quantity_out,
                ];
            });
    }

    /**
     * @return array{type:string, value:string, on_hand:int, stock:Collection, documents:Collection, active_recall:?BatchRecall}
     */
    public static function impact(string $type, string $value): array
    {
        $column = $type === 'serial' ? 'serial_number' : 'batch_number';
        $stock = static::affectedStock($column, $value);
        $documents = static::affectedDocuments($column, $value);

        $recall = BatchRecall::where('type', $type)
            ->where('status', 'active')
            ->where(fn ($q) => $q->where('batch_number', $value)->orWhere('serial_number', $value))
            ->first();

        return [
            'type' => $type,
            'value' => $value,
            'on_hand' => (int) $stock->sum('quantity'),
            'stock' => $stock,
            'documents' => $documents,
            'active_recall' => $recall,
        ];
    }

    public static function recall(string $type, string $value, string $reason): BatchRecall
    {
        $recall = DB::transaction(function () use ($type, $value, $reason): BatchRecall {
            $recall = BatchRecall::updateOrCreate(
                [
                    'batch_number' => $type === 'serial' ? ($value) : $value,
                    'serial_number' => $type === 'serial' ? $value : null,
                ],
                [
                    'type' => $type,
                    'reason' => $reason,
                    'status' => 'active',
                    'recalled_by' => auth()->id(),
                    'recalled_at' => now(),
                ],
            );

            // Mark affected lots as recalled via the quality status dimension.
            StockLot::where($type === 'serial' ? 'serial_number' : 'batch_number', $value)
                ->update(['quality_status' => 'recalled']);

            AuditLogger::log('RECALL', 'batch_recall', $recall, null, ['value' => $value, 'type' => $type, 'reason' => $reason]);

            return $recall;
        });

        return $recall;
    }

    public static function lift(BatchRecall $recall): void
    {
        DB::transaction(function () use ($recall): void {
            $recall->update([
                'status' => 'lifted',
                'lifted_by' => auth()->id(),
                'lifted_at' => now(),
            ]);

            StockLot::where($recall->type === 'serial' ? 'serial_number' : 'batch_number', $recall->type === 'serial' ? $recall->serial_number : $recall->batch_number)
                ->where('quality_status', 'recalled')
                ->update(['quality_status' => 'good']);

            AuditLogger::log('RECALL_LIFT', 'batch_recall', $recall);
        });
    }
}
