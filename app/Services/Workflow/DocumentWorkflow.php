<?php

namespace App\Services\Workflow;

use App\Enums\OpnameStatus;
use App\Enums\PurchaseOrderStatus;
use App\Enums\SalesOrderStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransferStatus;
use App\Models\GoodsIssue;
use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\StockAdjustment;
use App\Models\StockOpname;
use App\Models\StockTransfer;
use App\Services\Inventory\InventoryService;
use App\Services\Inventory\ReservationService;
use App\Services\Support\AuditLogger;
use App\Services\Support\NotificationService;
use Illuminate\Database\Eloquent\Model;

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

    /** Notify the role responsible for the current pending approval level. */
    protected static function notifyApprovalLevel(Model $doc, string $titleBase): void
    {
        try {
            $role = DocumentApproval::nextRole($doc);
            $level = ((int) ($doc->current_level ?? 0)) + 1;
            NotificationService::notifyRole($role, 'approval.request', $titleBase, $doc->number.' menunggu persetujuan level '.$level, $doc::class, $doc->id);
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

        DocumentApproval::snapshot($receipt);

        AuditLogger::log('SUBMIT', 'goods_receipt', $receipt);

        static::notifyApprovalLevel($receipt, 'Approval Barang Masuk');
    }

    public static function approveReceipt(int $id): void
    {
        $receipt = GoodsReceipt::findOrFail($id);

        static::ensure($receipt->statusEnum()->canTransitionTo(TransactionStatus::Approved));

        if (DocumentApproval::approve($receipt)) {
            AuditLogger::log('APPROVE', 'goods_receipt', $receipt);
            static::notifyUser($receipt->created_by, 'approval.result', 'Barang Masuk disetujui', $receipt->number.' telah disetujui', GoodsReceipt::class, $receipt->id);
        } else {
            AuditLogger::log('APPROVE_PARTIAL', 'goods_receipt', $receipt);
            static::notifyApprovalLevel($receipt, 'Approval Barang Masuk');
        }
    }

    public static function rejectReceipt(int $id, string $reason): void
    {
        $receipt = GoodsReceipt::findOrFail($id);

        static::ensure($receipt->statusEnum()->canTransitionTo(TransactionStatus::Rejected));
        static::ensureReason($reason);
        DocumentApproval::reject($receipt, $reason);

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

        DocumentApproval::snapshot($issue);

        AuditLogger::log('SUBMIT', 'goods_issue', $issue);

        static::notifyApprovalLevel($issue, 'Approval Barang Keluar');
    }

    public static function approveIssue(int $id): void
    {
        $issue = GoodsIssue::findOrFail($id);

        static::ensure($issue->statusEnum()->canTransitionTo(TransactionStatus::Approved));

        if (DocumentApproval::approve($issue)) {
            AuditLogger::log('APPROVE', 'goods_issue', $issue);
            static::notifyUser($issue->created_by, 'approval.result', 'Barang Keluar disetujui', $issue->number.' telah disetujui', GoodsIssue::class, $issue->id);
        } else {
            AuditLogger::log('APPROVE_PARTIAL', 'goods_issue', $issue);
            static::notifyApprovalLevel($issue, 'Approval Barang Keluar');
        }
    }

    public static function rejectIssue(int $id, string $reason): void
    {
        $issue = GoodsIssue::findOrFail($id);

        static::ensure($issue->statusEnum()->canTransitionTo(TransactionStatus::Rejected));
        static::ensureReason($reason);
        DocumentApproval::reject($issue, $reason);

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

        DocumentApproval::snapshot($adjustment);

        AuditLogger::log('SUBMIT', 'stock_adjustment', $adjustment);

        static::notifyApprovalLevel($adjustment, 'Approval Adjustment');
    }

    public static function approveAdjustment(int $id): void
    {
        $adjustment = StockAdjustment::findOrFail($id);

        static::ensure($adjustment->statusEnum()->canTransitionTo(TransactionStatus::Approved));

        if (DocumentApproval::approve($adjustment)) {
            AuditLogger::log('APPROVE', 'stock_adjustment', $adjustment);
            static::notifyUser($adjustment->created_by, 'approval.result', 'Adjustment disetujui', $adjustment->number.' telah disetujui', StockAdjustment::class, $adjustment->id);
        } else {
            AuditLogger::log('APPROVE_PARTIAL', 'stock_adjustment', $adjustment);
            static::notifyApprovalLevel($adjustment, 'Approval Adjustment');
        }
    }

    public static function rejectAdjustment(int $id, string $reason): void
    {
        $adjustment = StockAdjustment::findOrFail($id);

        static::ensure($adjustment->statusEnum()->canTransitionTo(TransactionStatus::Rejected));
        static::ensureReason($reason);
        DocumentApproval::reject($adjustment, $reason);

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
        DocumentApproval::snapshot($opname);
        AuditLogger::log('SUBMIT', 'stock_opname', $opname);

        static::notifyApprovalLevel($opname, 'Approval Opname');
    }

    public static function rejectOpname(int $id, string $reason): void
    {
        $opname = StockOpname::findOrFail($id);

        static::ensure($opname->statusEnum()->canTransitionTo(OpnameStatus::Rejected));
        static::ensureReason($reason);
        DocumentApproval::reject($opname, $reason);

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

        if (DocumentApproval::approve($opname)) {
            AuditLogger::log('APPROVE', 'stock_opname', $opname);

            InventoryService::completeStockOpname($opname->fresh('items'));

            static::notifyUser($opname->created_by, 'opname.completed', 'Opname selesai', $opname->number.' telah selesai', StockOpname::class, $opname->id);
        } else {
            AuditLogger::log('APPROVE_PARTIAL', 'stock_opname', $opname);
            static::notifyApprovalLevel($opname, 'Approval Opname');
        }
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

        DocumentApproval::snapshot($transfer);

        AuditLogger::log('REQUEST', 'stock_transfer', $transfer);

        static::notifyApprovalLevel($transfer, 'Approval Transfer');
    }

    public static function approveTransfer(int $id): void
    {
        $transfer = StockTransfer::findOrFail($id);

        static::ensure($transfer->statusEnum()->canTransitionTo(TransferStatus::Approved));

        if (DocumentApproval::approve($transfer)) {
            AuditLogger::log('APPROVE', 'stock_transfer', $transfer);
            static::notifyUser($transfer->created_by, 'approval.result', 'Transfer disetujui', $transfer->number.' telah disetujui', StockTransfer::class, $transfer->id);
        } else {
            AuditLogger::log('APPROVE_PARTIAL', 'stock_transfer', $transfer);
            static::notifyApprovalLevel($transfer, 'Approval Transfer');
        }
    }

    public static function rejectTransfer(int $id, string $reason): void
    {
        $transfer = StockTransfer::findOrFail($id);

        static::ensure($transfer->statusEnum()->canTransitionTo(TransferStatus::Rejected));
        static::ensureReason($reason);
        DocumentApproval::reject($transfer, $reason);

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

        DocumentApproval::snapshot($order);

        AuditLogger::log('SUBMIT', 'purchase_order', $order);

        static::notifyApprovalLevel($order, 'Approval Purchase Order');
    }

    public static function approvePo(int $id): void
    {
        $order = PurchaseOrder::findOrFail($id)->load('items');

        static::ensure($order->statusEnum()->canTransitionTo(PurchaseOrderStatus::Approved));

        if (DocumentApproval::approve($order)) {
            AuditLogger::log('APPROVE', 'purchase_order', $order);
            static::notifyUser($order->created_by, 'approval.result', 'Purchase Order disetujui', $order->number.' telah disetujui', PurchaseOrder::class, $order->id);
        } else {
            AuditLogger::log('APPROVE_PARTIAL', 'purchase_order', $order);
            static::notifyApprovalLevel($order, 'Approval Purchase Order');
        }
    }

    public static function rejectPo(int $id, string $reason): void
    {
        $order = PurchaseOrder::findOrFail($id);

        static::ensure($order->statusEnum()->canTransitionTo(PurchaseOrderStatus::Rejected));
        static::ensureReason($reason);

        DocumentApproval::reject($order, $reason);

        $order->update([
            'rejected_at' => now(),
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

    public static function submitSo(int $id): void
    {
        $order = SalesOrder::findOrFail($id);

        static::ensure($order->statusEnum()->canTransitionTo(SalesOrderStatus::Submitted));

        $order->update([
            'status' => SalesOrderStatus::Submitted->value,
            'submitted_by' => auth()->id(),
            'submitted_at' => now(),
        ]);

        DocumentApproval::snapshot($order);

        AuditLogger::log('SUBMIT', 'sales_order', $order);

        static::notifyApprovalLevel($order, 'Approval Sales Order');
    }

    public static function approveSo(int $id): void
    {
        $order = SalesOrder::findOrFail($id)->load('items');

        static::ensure($order->statusEnum()->canTransitionTo(SalesOrderStatus::Approved));

        if (DocumentApproval::approve($order)) {
            AuditLogger::log('APPROVE', 'sales_order', $order);
            static::notifyUser($order->created_by, 'approval.result', 'Sales Order disetujui', $order->number.' telah disetujui. Silakan buat Barang Keluar dari SO ini.', SalesOrder::class, $order->id);
        } else {
            AuditLogger::log('APPROVE_PARTIAL', 'sales_order', $order);
            static::notifyApprovalLevel($order, 'Approval Sales Order');
        }
    }

    public static function rejectSo(int $id, string $reason): void
    {
        $order = SalesOrder::findOrFail($id);

        static::ensure($order->statusEnum()->canTransitionTo(SalesOrderStatus::Rejected));
        static::ensureReason($reason);

        DocumentApproval::reject($order, $reason);

        $order->update([
            'status' => SalesOrderStatus::Rejected->value,
            'rejected_at' => now(),
        ]);

        AuditLogger::log('REJECT', 'sales_order', $order);

        static::notifyUser($order->created_by, 'approval.result', 'Sales Order ditolak', $order->number.' ditolak: '.$reason, SalesOrder::class, $order->id);
    }

    public static function closeSo(int $id): void
    {
        $order = SalesOrder::findOrFail($id);

        static::ensure($order->statusEnum()->canTransitionTo(SalesOrderStatus::Closed));

        $order->update([
            'status' => SalesOrderStatus::Closed->value,
            'closed_by' => auth()->id(),
            'closed_at' => now(),
        ]);

        AuditLogger::log('CLOSE', 'sales_order', $order);
    }
}
