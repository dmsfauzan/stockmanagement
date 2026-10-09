<?php

namespace App\Services\Inventory;

use App\Enums\AssemblyType;
use App\Enums\TransactionType;
use App\Models\AssemblyOrder;
use App\Models\ItemBom;
use App\Services\Support\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Posting / reversing assembly (kit) & disassembly orders.
 * An assembly consumes components (outgoing) and produces the kit item
 * (incoming); a disassembly does the opposite. Posting is atomic via
 * LedgerService so stock never goes negative.
 */
class AssemblyService
{
    public static function post(AssemblyOrder $order): void
    {
        DB::transaction(function () use ($order): void {
            $locked = static::locked($order->getKey());

            if ($locked->status !== 'approved') {
                throw new \RuntimeException('Only approved assembly orders can be posted.');
            }

            $kitItemId = (int) $locked->item_id;
            $qty = (int) $locked->quantity;
            $warehouseId = (int) $locked->warehouse_id;
            $locationId = (int) $locked->location_id;
            $type = $locked->type instanceof AssemblyType
                ? $locked->type
                : (AssemblyType::tryFrom((string) $locked->type) ?? AssemblyType::Assembly);

            $bom = ItemBom::where('kit_item_id', $kitItemId)->get();

            if ($bom->isEmpty()) {
                throw new \RuntimeException('BOM tidak ditemukan untuk barang kit ini.');
            }

            $lineItems = $locked->items;

            if ($type === AssemblyType::Assembly) {
                foreach ($bom as $component) {
                    $componentQty = (int) $component->quantity * $qty;
                    $unitCost = (float) ($lineItems->firstWhere('item_id', $component->component_item_id)?->unit_cost ?? 0);

                    LedgerService::record(
                        (int) $component->component_item_id,
                        $warehouseId,
                        $locationId,
                        TransactionType::Outgoing,
                        AssemblyOrder::class,
                        (int) $locked->id,
                        0,
                        $componentQty,
                        null,
                        null,
                        'Assembly consume '.$locked->number,
                        $unitCost > 0 ? 0 : null,
                    );
                }

                $kitCost = (float) $bom->sum(fn ($c) => (float) ($lineItems->firstWhere('item_id', $c->component_item_id)?->unit_cost ?? 0) * (int) $c->quantity * $qty) / max(1, $qty);

                LedgerService::record(
                    $kitItemId,
                    $warehouseId,
                    $locationId,
                    TransactionType::Incoming,
                    AssemblyOrder::class,
                    (int) $locked->id,
                    $qty,
                    0,
                    null,
                    null,
                    'Assembly produce '.$locked->number,
                    $kitCost,
                );
            } else {
                LedgerService::record(
                    $kitItemId,
                    $warehouseId,
                    $locationId,
                    TransactionType::Outgoing,
                    AssemblyOrder::class,
                    (int) $locked->id,
                    0,
                    $qty,
                );

                foreach ($bom as $component) {
                    $componentQty = (int) $component->quantity * $qty;

                    LedgerService::record(
                        (int) $component->component_item_id,
                        $warehouseId,
                        $locationId,
                        TransactionType::Incoming,
                        AssemblyOrder::class,
                        (int) $locked->id,
                        $componentQty,
                        0,
                        null,
                        null,
                        'Disassembly produce '.$locked->number,
                    );
                }
            }

            $locked->update([
                'status' => 'posted',
                'posted_by' => auth()->id(),
                'posted_at' => now(),
            ]);

            AuditLogger::log('POST', 'assembly_order', $locked);
        });
    }

    public static function reverse(AssemblyOrder $order, string $reason): void
    {
        DB::transaction(function () use ($order, $reason): void {
            $locked = static::locked($order->getKey());

            if ($locked->status !== 'posted') {
                throw new \RuntimeException('Only posted assembly orders can be reversed.');
            }

            if (! is_null($locked->reversed_at)) {
                throw new \RuntimeException('Transaction already reversed');
            }

            $type = $locked->type instanceof AssemblyType
                ? $locked->type
                : (AssemblyType::tryFrom((string) $locked->type) ?? AssemblyType::Assembly);
            $qty = (int) $locked->quantity;
            $warehouseId = (int) $locked->warehouse_id;
            $locationId = (int) $locked->location_id;

            if ($type === AssemblyType::Assembly) {
                LedgerService::record(
                    (int) $locked->item_id,
                    $warehouseId,
                    $locationId,
                    TransactionType::Outgoing,
                    AssemblyOrder::class,
                    (int) $locked->id,
                    0,
                    $qty,
                    null,
                    null,
                    "Reversal of {$locked->number} — {$reason}"
                );

                foreach (ItemBom::where('kit_item_id', $locked->item_id)->get() as $component) {
                    LedgerService::record(
                        (int) $component->component_item_id,
                        $warehouseId,
                        $locationId,
                        TransactionType::Incoming,
                        AssemblyOrder::class,
                        (int) $locked->id,
                        (int) $component->quantity * $qty,
                        0,
                        null,
                        null,
                        "Reversal of {$locked->number} — {$reason}"
                    );
                }
            } else {
                LedgerService::record(
                    (int) $locked->item_id,
                    $warehouseId,
                    $locationId,
                    TransactionType::Incoming,
                    AssemblyOrder::class,
                    (int) $locked->id,
                    $qty,
                    0,
                    null,
                    null,
                    "Reversal of {$locked->number} — {$reason}"
                );

                foreach (ItemBom::where('kit_item_id', $locked->item_id)->get() as $component) {
                    LedgerService::record(
                        (int) $component->component_item_id,
                        $warehouseId,
                        $locationId,
                        TransactionType::Outgoing,
                        AssemblyOrder::class,
                        (int) $locked->id,
                        0,
                        (int) $component->quantity * $qty,
                        null,
                        null,
                        "Reversal of {$locked->number} — {$reason}"
                    );
                }
            }

            $locked->update([
                'reversed_at' => now(),
                'reversed_by' => auth()->id(),
                'reversal_reason' => $reason,
            ]);

            AuditLogger::log('REVERSE', 'assembly_order', $locked);
        });
    }

    private static function locked(int $id): AssemblyOrder
    {
        return AssemblyOrder::with('items')->whereKey($id)->lockForUpdate()->firstOrFail();
    }
}
