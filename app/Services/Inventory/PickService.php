<?php

namespace App\Services\Inventory;

use App\Enums\PickStatus;
use App\Models\GoodsIssue;
use App\Models\PickList;
use App\Services\Support\AuditLogger;
use App\Services\Support\DocumentNumberService;
use Illuminate\Support\Facades\DB;

/**
 * Pick & pack workflow for outbound goods. A pick list is generated from
 * an approved goods issue (optionally linked to a sales order), ordered
 * by walking path (warehouse → zone → rack → bin) so pickers seldom
 * backtrack. Lines carry the ledger batch/serial for FEFO accuracy.
 */
class PickService
{
    public static function generateFromIssue(GoodsIssue $issue, ?int $assignedTo = null, ?string $notes = null): PickList
    {
        $issue->loadMissing('issueItems.item');

        if (! in_array($issue->status, ['approved', 'partial'], true)) {
            throw new \RuntimeException('Pick list hanya bisa dibuat dari barang keluar yang sudah disetujui.');
        }

        return DB::transaction(function () use ($issue, $assignedTo, $notes): PickList {
            $pickList = PickList::create([
                'number' => DocumentNumberService::generate('PL'),
                'goods_issue_id' => $issue->id,
                'sales_order_id' => $issue->sales_order_id,
                'warehouse_id' => $issue->warehouse_id,
                'status' => PickStatus::Pending->value,
                'assigned_to' => $assignedTo,
                'notes' => $notes,
                'created_by' => auth()->id(),
            ]);

            $lines = collect($issue->issueItems)
                ->sortBy(fn ($line) => $line->location?->fullPath() ?? '');

            foreach ($lines as $line) {
                $pickList->items()->create([
                    'item_id' => $line->item_id,
                    'location_id' => $line->location_id,
                    'unit_id' => $line->unit_id,
                    'batch_number' => $line->batch_number,
                    'serial_number' => $line->serial_number,
                    'quantity' => $line->baseQuantity(),
                    'picked_quantity' => 0,
                    'status' => PickStatus::Pending->value,
                ]);
            }

            AuditLogger::logModel('create', $pickList);

            return $pickList;
        });
    }

    public static function start(PickList $pickList): void
    {
        if ($pickList->status !== PickStatus::Pending) {
            throw new \RuntimeException('Pick list sudah dimulai / selesai.');
        }

        $pickList->update(['status' => PickStatus::Picking->value]);
    }

    public static function confirmItem(PickList $pickList, int $itemId, int $pickedQuantity, ?string $batchNumber = null, ?string $serialNumber = null): void
    {
        if (in_array($pickList->status, [PickStatus::Packed, PickStatus::Cancelled], true)) {
            throw new \RuntimeException('Pick list sudah dikemas / dibatalkan.');
        }

        $line = $pickList->items()->whereKey($itemId)->firstOrFail();

        $pickedQuantity = max(0, $pickedQuantity);

        if ($pickedQuantity > (int) $line->quantity) {
            throw new \RuntimeException('Jumlah picking melebihi permintaan.');
        }

        $line->update([
            'picked_quantity' => $pickedQuantity,
            'batch_number' => $batchNumber ?? $line->batch_number,
            'serial_number' => $serialNumber ?? $line->serial_number,
            'status' => ($pickedQuantity >= (int) $line->quantity ? PickStatus::Picked : PickStatus::Short)->value,
        ]);

        if ($pickList->status === PickStatus::Pending) {
            $pickList->update(['status' => PickStatus::Picking->value]);
        }
    }

    public static function complete(PickList $pickList): void
    {
        if (! in_array($pickList->status, [PickStatus::Pending, PickStatus::Picking], true)) {
            throw new \RuntimeException('Pick list tidak dapat diselesaikan.');
        }

        if ($pickList->items()->where('status', PickStatus::Pending->value)->exists()) {
            throw new \RuntimeException('Masih ada baris yang belum dikonfirmasi.');
        }

        $pickList->update([
            'status' => PickStatus::Picked->value,
            'picked_by' => auth()->id(),
            'picked_at' => now(),
        ]);

        AuditLogger::logModel('complete', $pickList);
    }

    public static function pack(PickList $pickList): void
    {
        if ($pickList->status !== PickStatus::Picked) {
            throw new \RuntimeException('Hanya pick list selesai yang dapat dikemas.');
        }

        $pickList->update([
            'status' => PickStatus::Packed->value,
            'packed_by' => auth()->id(),
            'packed_at' => now(),
        ]);

        AuditLogger::logModel('pack', $pickList);
    }

    public static function cancel(PickList $pickList, ?string $reason = null): void
    {
        if ($pickList->status === PickStatus::Packed) {
            throw new \RuntimeException('Pick list yang sudah dikemas tidak dapat dibatalkan.');
        }

        $pickList->update([
            'status' => PickStatus::Cancelled->value,
            'notes' => trim(($pickList->notes ? $pickList->notes.' — ' : '').($reason ?? 'Dibatalkan')),
        ]);

        AuditLogger::logModel('cancel', $pickList);
    }
}
