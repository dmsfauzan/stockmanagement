<?php

namespace App\Services\Workflow;

use App\Models\PurchaseOrder;
use App\Models\PurchaseRequisition;
use App\Services\Support\AuditLogger;
use App\Services\Support\CurrencyService;
use App\Services\Support\DocumentNumberService;
use Illuminate\Support\Facades\DB;

/**
 * Purchase requisition workflow: draft → submitted → approved /
 * rejected; an approved requisition can be converted into a purchase
 * order exactly once.
 */
class RequisitionWorkflow
{
    public static function submit(int $id): void
    {
        $req = PurchaseRequisition::findOrFail($id);

        if ($req->status !== 'draft') {
            throw new \RuntimeException('Hanya requisition draft yang dapat diajukan.');
        }

        $req->update(['status' => 'submitted', 'submitted_by' => auth()->id(), 'submitted_at' => now()]);
        AuditLogger::logModel('submit', $req);
    }

    public static function approve(int $id): void
    {
        $req = PurchaseRequisition::findOrFail($id);

        if ($req->status !== 'submitted') {
            throw new \RuntimeException('Hanya requisition diajukan yang dapat disetujui.');
        }

        $req->update(['status' => 'approved', 'approved_by' => auth()->id(), 'approved_at' => now()]);
        AuditLogger::logModel('approve', $req);
    }

    public static function reject(int $id, string $reason): void
    {
        $req = PurchaseRequisition::findOrFail($id);

        if ($req->status !== 'submitted') {
            throw new \RuntimeException('Hanya requisition diajukan yang dapat ditolak.');
        }

        $req->update(['status' => 'rejected', 'rejected_by' => auth()->id(), 'rejected_at' => now(), 'rejection_reason' => $reason]);
        AuditLogger::logModel('reject', $req);
    }

    public static function convertToPurchaseOrder(int $id, ?int $supplierId = null, ?string $currencyCode = null): PurchaseOrder
    {
        return DB::transaction(function () use ($id, $supplierId, $currencyCode): PurchaseOrder {
            $req = PurchaseRequisition::with('items')->findOrFail($id);

            if ($req->status !== 'approved') {
                throw new \RuntimeException('Hanya requisition yang disetujui yang dapat dikonversi ke PO.');
            }

            if ($req->converted_purchase_order_id) {
                throw new \RuntimeException('Requisition ini sudah dikonversi ke PO.');
            }

            $order = PurchaseOrder::create([
                'number' => DocumentNumberService::generate('PO'),
                'order_date' => today(),
                'supplier_id' => $supplierId,
                'warehouse_id' => $req->warehouse_id,
                'currency_code' => $currencyCode ?? 'IDR',
                'exchange_rate' => app(CurrencyService::class)::rate($currencyCode ?? 'IDR'),
                'notes' => 'Dari requisition '.$req->number,
                'status' => 'draft',
                'created_by' => auth()->id(),
            ]);

            foreach ($req->items as $line) {
                $order->items()->create([
                    'item_id' => $line->item_id,
                    'quantity' => (int) $line->quantity,
                    'received_quantity' => 0,
                    'unit_id' => $line->unit_id,
                    'unit_price' => (float) $line->estimated_price,
                    'notes' => $line->notes,
                ]);
            }

            $req->update(['converted_purchase_order_id' => $order->id]);

            AuditLogger::logModel('create', $order, null, $order->fresh('items')->toArray());

            return $order;
        });
    }
}
