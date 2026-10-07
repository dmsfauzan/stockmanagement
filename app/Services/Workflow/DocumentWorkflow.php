<?php

namespace App\Services\Workflow;

use App\Enums\OpnameStatus;
use App\Enums\PurchaseOrderStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransferStatus;
use App\Models\GoodsIssue;
use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\StockAdjustment;
use App\Models\StockOpname;
use App\Models\StockTransfer;
use App\Services\Inventory\InventoryService;
use App\Services\Inventory\ReservationService;
use App\Services\Support\AuditLogger;
use App\Services\Support\NotificationService;

class DocumentWorkflow
{
    protected static function ensure(bool $condition): void
    {
        if (! $condition) {
            throw new \RuntimeException('Status tidak dapat diubah.');
        }
    }

    protected static function ensureReason(string $reason): void
    {
        if (mb_strlen(trim($reason)) < 3) {
            throw new \RuntimeException('Rejection reason is required.');
        }
    }

    protected static function notifyApprovers(string $type, string $title, string $message, string $referenceType, int $referenceId): void
    {
        try {
            NotificationService::notifyApprovers($type, $title, $message, $referenceType, $referenceId);
        } catch (\Throwable $e) {
        }
    }

    protected static function notifyUser(?int $userId, string $type, string $title, string $message, string $referenceType, int $referenceId): void
    {
        try {
            NotificationService::notify($userId, $type, $title, $message, $referenceType, $referenceId);
        } catch (\Throwable $e) {
        }
    }

    public static function submitReceipt(int $id): void
    {
        $receipt = GoodsReceipt::findOrFail($id);

        static::ensure($receipt->statusEnum()->canTransitionTo(TransactionStatus::Submitted));

        $receipt->update([
            'status' => TransactionStatus::Submitted->value,
            'submitted_by' => auth()->id(),
            'submitted_at' => now(),
        ]);

        AuditLogger::log('SUBMIT', 'goods_receipt', $receipt);

        static::notifyApprovers('approval.request', 'Approval Barang Masuk', $receipt->number.' menunggu persetujuan', GoodsReceipt::class, $receipt->id);
    }

    public static function approveReceipt(int $id): void
    {
        $receipt = GoodsReceipt::findOrFail($id);

        static::ensure($receipt->statusEnum()->canTransitionTo(TransactionStatus::Approved));

        $receipt->update([
            'status' => TransactionStatus::Approved->value,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        AuditLogger::log('APPROVE', 'goods_receipt', $receipt);

        static::notifyUser($receipt->created_by, 'approval.result', 'Barang Masuk disetujui', $receipt->number.' telah disetujui', GoodsReceipt::class, $receipt->id);
    }

    public static function rejectReceipt(int $id, string $reason): void
    {
        $receipt = GoodsReceipt::findOrFail($id);

        static::ensure($receipt->statusEnum()->canTransitionTo(TransactionStatus::Rejected));
        static::ensureReason($reason);

        $receipt->update([
            'status' => TransactionStatus::Rejected->value,
            'rejected_by' => auth()->id(),
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ]);

        AuditLogger::log('REJECT', 'goods_receipt', $receipt);

        static::notifyUser($receipt->created_by, 'approval.result', 'Barang Masuk ditolak', $receipt->number.' ditolak: '.$reason, GoodsReceipt::class, $receipt->id);
    }

    public static function postReceipt(int $id): void
    {
        $receipt = GoodsReceipt::findOrFail($id);

        static::ensure($receipt->statusEnum()->canTransitionTo(TransactionStatus::Posted));

        InventoryService::postGoodsReceipt($receipt);
    }

    public static function submitIssue(int $id): void
    {
        $issue = GoodsIssue::findOrFail($id);

        static::ensure($issue->statusEnum()->canTransitionTo(TransactionStatus::Submitted));

        ReservationService::reserve(GoodsIssue::class, $issue->id, $issue->issueItems->map(fn ($item) => [
            'item_id' => $item->item_id,
            'warehouse_id' => $issue->warehouse_id,
            'location_id' => $item->location_id,
            'quantity' => $item->quantity,
        ])->all());

        $issue->update([
            'status' => TransactionStatus::Submitted->value,
            'submitted_by' => auth()->id(),
            'submitted_at' => now(),
        ]);

        AuditLogger::log('SUBMIT', 'goods_issue', $issue);

        static::notifyApprovers('approval.request', 'Approval Barang Keluar', $issue->number.' menunggu persetujuan', GoodsIssue::class, $issue->id);
    }

    public static function approveIssue(int $id): void
    {
        $issue = GoodsIssue::findOrFail($id);

        static::ensure($issue->statusEnum()->canTransitionTo(TransactionStatus::Approved));

        $issue->update([
            'status' => TransactionStatus::Approved->value,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        AuditLogger::log('APPROVE', 'goods_issue', $issue);

        static::notifyUser($issue->created_by, 'approval.result', 'Barang Keluar disetujui', $issue->number.' telah disetujui', GoodsIssue::class, $issue->id);
    }

    public static function rejectIssue(int $id, string $reason): void
    {
        $issue = GoodsIssue::findOrFail($id);

        static::ensure($issue->statusEnum()->canTransitionTo(TransactionStatus::Rejected));
        static::ensureReason($reason);

        $issue->update([
            'status' => TransactionStatus::Rejected->value,
            'rejected_by' => auth()->id(),
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ]);

        AuditLogger::log('REJECT', 'goods_issue', $issue);

        try {
            ReservationService::release(GoodsIssue::class, $issue->id);
        } catch (\Throwable $e) {
        }

        static::notifyUser($issue->created_by, 'approval.result', 'Barang Keluar ditolak', $issue->number.' ditolak: '.$reason, GoodsIssue::class, $issue->id);
    }

    public static function postIssue(int $id): void
    {
        $issue = GoodsIssue::findOrFail($id);

        static::ensure($issue->statusEnum()->canTransitionTo(TransactionStatus::Posted));

        InventoryService::postGoodsIssue($issue);
    }

    public static function submitAdjustment(int $id): void
    {
        $adjustment = StockAdjustment::findOrFail($id);

        static::ensure($adjustment->statusEnum()->canTransitionTo(TransactionStatus::Submitted));

        $adjustment->update([
            'status' => TransactionStatus::Submitted->value,
            'submitted_by' => auth()->id(),
            'submitted_at' => now(),
        ]);

        AuditLogger::log('SUBMIT', 'stock_adjustment', $adjustment);

        static::notifyApprovers('approval.request', 'Approval Adjustment', $adjustment->number.' menunggu persetujuan', StockAdjustment::class, $adjustment->id);
    }

    public static function approveAdjustment(int $id): void
    {
        $adjustment = StockAdjustment::findOrFail($id);

        static::ensure($adjustment->statusEnum()->canTransitionTo(TransactionStatus::Approved));

        $adjustment->update([
            'status' => TransactionStatus::Approved->value,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        AuditLogger::log('APPROVE', 'stock_adjustment', $adjustment);

        static::notifyUser($adjustment->created_by, 'approval.result', 'Adjustment disetujui', $adjustment->number.' telah disetujui', StockAdjustment::class, $adjustment->id);
    }

    public static function rejectAdjustment(int $id, string $reason): void
    {
        $adjustment = StockAdjustment::findOrFail($id);

        static::ensure($adjustment->statusEnum()->canTransitionTo(TransactionStatus::Rejected));
        static::ensureReason($reason);

        $adjustment->update([
            'status' => TransactionStatus::Rejected->value,
            'rejected_by' => auth()->id(),
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ]);

        AuditLogger::log('REJECT', 'stock_adjustment', $adjustment);

        static::notifyUser($adjustment->created_by, 'approval.result', 'Adjustment ditolak', $adjustment->number.' ditolak: '.$reason, StockAdjustment::class, $adjustment->id);
    }

    public static function postAdjustment(int $id): void
    {
        $adjustment = StockAdjustment::findOrFail($id);

        static::ensure($adjustment->statusEnum()->canTransitionTo(TransactionStatus::Posted));

        InventoryService::postStockAdjustment($adjustment);
    }

    public static function startCountingOpname(int $id): void
    {
        $opname = StockOpname::findOrFail($id);

        static::ensure($opname->statusEnum()->canTransitionTo(OpnameStatus::Counting));

        $opname->update(['status' => OpnameStatus::Counting->value]);
        AuditLogger::log('COUNTING', 'stock_opname', $opname);
    }

    public static function submitOpname(int $id): void
    {
        $opname = StockOpname::findOrFail($id);

        static::ensure($opname->statusEnum()->canTransitionTo(OpnameStatus::Submitted));

        if ($opname->items()->whereNull('physical_quantity')->exists()) {
            throw new \RuntimeException('Lengkapi physical qty');
        }

        $opname->update([
            'status' => OpnameStatus::Submitted->value,
            'submitted_by' => auth()->id(),
            'submitted_at' => now(),
        ]);
        AuditLogger::log('SUBMIT', 'stock_opname', $opname);

        static::notifyApprovers('approval.request', 'Approval Opname', $opname->number.' menunggu persetujuan', StockOpname::class, $opname->id);
    }

    public static function rejectOpname(int $id, string $reason): void
    {
        $opname = StockOpname::findOrFail($id);

        static::ensure($opname->statusEnum()->canTransitionTo(OpnameStatus::Rejected));
        static::ensureReason($reason);

        $opname->update([
            'status' => OpnameStatus::Rejected->value,
            'rejected_by' => auth()->id(),
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ]);
        AuditLogger::log('REJECT', 'stock_opname', $opname);

        static::notifyUser($opname->created_by, 'approval.result', 'Opname ditolak', $opname->number.' ditolak: '.$reason, StockOpname::class, $opname->id);
    }

    public static function approveAndCompleteOpname(int $id): void
    {
        $opname = StockOpname::findOrFail($id);

        static::ensure($opname->statusEnum()->canTransitionTo(OpnameStatus::Approved));

        if ($opname->items()->whereNull('physical_quantity')->exists()) {
            throw new \RuntimeException('Opname belum lengkap');
        }

        $opname->update([
            'status' => OpnameStatus::Approved->value,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);
        AuditLogger::log('APPROVE', 'stock_opname', $opname);

        InventoryService::completeStockOpname($opname->fresh('items'));

        static::notifyUser($opname->created_by, 'opname.completed', 'Opname selesai', $opname->number.' telah selesai', StockOpname::class, $opname->id);
    }

    public static function requestTransfer(int $id): void
    {
        $transfer = StockTransfer::findOrFail($id);

        static::ensure($transfer->statusEnum()->canTransitionTo(TransferStatus::Requested));

        ReservationService::reserve(StockTransfer::class, $transfer->id, $transfer->items->map(fn ($item) => [
            'item_id' => $item->item_id,
            'warehouse_id' => $transfer->from_warehouse_id,
            'location_id' => $transfer->from_location_id,
            'quantity' => $item->quantity,
        ])->all());

        $transfer->update([
            'status' => TransferStatus::Requested->value,
            'requested_by' => auth()->id(),
            'requested_at' => now(),
        ]);

        AuditLogger::log('REQUEST', 'stock_transfer', $transfer);

        static::notifyApprovers('approval.request', 'Approval Transfer', $transfer->number.' menunggu persetujuan', StockTransfer::class, $transfer->id);
    }

    public static function approveTransfer(int $id): void
    {
        $transfer = StockTransfer::findOrFail($id);

        static::ensure($transfer->statusEnum()->canTransitionTo(TransferStatus::Approved));

        $transfer->update([
            'status' => TransferStatus::Approved->value,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        AuditLogger::log('APPROVE', 'stock_transfer', $transfer);

        static::notifyUser($transfer->created_by, 'approval.result', 'Transfer disetujui', $transfer->number.' telah disetujui', StockTransfer::class, $transfer->id);
    }

    public static function rejectTransfer(int $id, string $reason): void
    {
        $transfer = StockTransfer::findOrFail($id);

        static::ensure($transfer->statusEnum()->canTransitionTo(TransferStatus::Rejected));
        static::ensureReason($reason);

        $transfer->update([
            'status' => TransferStatus::Rejected->value,
            'rejected_by' => auth()->id(),
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ]);

        AuditLogger::log('REJECT', 'stock_transfer', $transfer);

        try {
            ReservationService::release(StockTransfer::class, $transfer->id);
        } catch (\Throwable $e) {
        }

        static::notifyUser($transfer->created_by, 'approval.result', 'Transfer ditolak', $transfer->number.' ditolak: '.$reason, StockTransfer::class, $transfer->id);
    }

    public static function dispatchTransfer(int $id): void
    {
        $transfer = StockTransfer::findOrFail($id);

        static::ensure($transfer->statusEnum() === TransferStatus::Approved);

        InventoryService::dispatchStockTransfer($transfer);
    }

    public static function receiveTransfer(int $id): void
    {
        $transfer = StockTransfer::findOrFail($id);

        static::ensure($transfer->statusEnum() === TransferStatus::InTransit);

        InventoryService::receiveStockTransfer($transfer);
    }

    public static function completeTransfer(int $id): void
    {
        $transfer = StockTransfer::findOrFail($id);

        static::ensure($transfer->statusEnum()->canTransitionTo(TransferStatus::Completed));

        $transfer->update([
            'status' => TransferStatus::Completed->value,
            'completed_by' => auth()->id(),
            'completed_at' => now(),
        ]);

        AuditLogger::log('COMPLETE', 'stock_transfer', $transfer);
    }

    public static function submitPo(int $id): void
    {
        $order = PurchaseOrder::findOrFail($id);

        static::ensure($order->statusEnum()->canTransitionTo(PurchaseOrderStatus::Submitted));

        $order->update([
            'status' => PurchaseOrderStatus::Submitted->value,
            'submitted_by' => auth()->id(),
            'submitted_at' => now(),
        ]);

        AuditLogger::log('SUBMIT', 'purchase_order', $order);

        static::notifyApprovers('approval.request', 'Approval Purchase Order', $order->number.' menunggu persetujuan', PurchaseOrder::class, $order->id);
    }

    public static function approvePo(int $id): void
    {
        $order = PurchaseOrder::findOrFail($id);

        static::ensure($order->statusEnum()->canTransitionTo(PurchaseOrderStatus::Approved));

        $order->update([
            'status' => PurchaseOrderStatus::Approved->value,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        AuditLogger::log('APPROVE', 'purchase_order', $order);

        static::notifyUser($order->created_by, 'approval.result', 'Purchase Order disetujui', $order->number.' telah disetujui', PurchaseOrder::class, $order->id);
    }

    public static function rejectPo(int $id, string $reason): void
    {
        $order = PurchaseOrder::findOrFail($id);

        static::ensure($order->statusEnum()->canTransitionTo(PurchaseOrderStatus::Rejected));
        static::ensureReason($reason);

        $order->update([
            'status' => PurchaseOrderStatus::Rejected->value,
            'rejected_by' => auth()->id(),
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ]);

        AuditLogger::log('REJECT', 'purchase_order', $order);

        static::notifyUser($order->created_by, 'approval.result', 'Purchase Order ditolak', $order->number.' ditolak: '.$reason, PurchaseOrder::class, $order->id);
    }

    public static function closePo(int $id): void
    {
        $order = PurchaseOrder::findOrFail($id);

        static::ensure($order->statusEnum()->canTransitionTo(PurchaseOrderStatus::Closed));

        $order->update([
            'status' => PurchaseOrderStatus::Closed->value,
            'closed_by' => auth()->id(),
            'closed_at' => now(),
        ]);

        AuditLogger::log('CLOSE', 'purchase_order', $order);
    }
}
