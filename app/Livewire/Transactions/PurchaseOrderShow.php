<?php

namespace App\Livewire\Transactions;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Services\Support\AuditLogger;
use App\Services\Support\NotificationService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Detail Purchase Order')]
class PurchaseOrderShow extends Component
{
    public int $purchaseOrderId;

    public string $rejectionReason = '';

    public function mount($purchaseOrder): void
    {
        $model = $purchaseOrder instanceof PurchaseOrder ? $purchaseOrder : PurchaseOrder::findOrFail($purchaseOrder);

        $this->authorize('view', $model);

        $this->purchaseOrderId = $model->id;
    }

    public function submit(): void
    {
        $order = PurchaseOrder::findOrFail($this->purchaseOrderId);

        $this->authorize('submit', $order);

        if (! $order->statusEnum()->canTransitionTo(PurchaseOrderStatus::Submitted)) {
            $this->dispatch('toast', type: 'error', message: 'Status tidak dapat diubah.');

            return;
        }

        try {
            $order->update([
                'status' => PurchaseOrderStatus::Submitted->value,
                'submitted_by' => auth()->id(),
                'submitted_at' => now(),
            ]);

            AuditLogger::log('SUBMIT', 'purchase_order', $order);

            try {
                NotificationService::notifyApprovers('approval.request', 'Approval Purchase Order', $order->number.' menunggu persetujuan', PurchaseOrder::class, $order->id);
            } catch (\Throwable $e) {
            }

            $this->dispatch('toast', type: 'success', message: 'Berhasil diajukan.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function approve(): void
    {
        $order = PurchaseOrder::findOrFail($this->purchaseOrderId);

        $this->authorize('approve', $order);

        if (! $order->statusEnum()->canTransitionTo(PurchaseOrderStatus::Approved)) {
            $this->dispatch('toast', type: 'error', message: 'Status tidak dapat diubah.');

            return;
        }

        try {
            $order->update([
                'status' => PurchaseOrderStatus::Approved->value,
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

            AuditLogger::log('APPROVE', 'purchase_order', $order);

            try {
                NotificationService::notify($order->created_by, 'approval.result', 'Purchase Order disetujui', $order->number.' telah disetujui', PurchaseOrder::class, $order->id);
            } catch (\Throwable $e) {
            }

            $this->dispatch('toast', type: 'success', message: 'Berhasil disetujui.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function reject(): void
    {
        $order = PurchaseOrder::findOrFail($this->purchaseOrderId);

        $this->authorize('approve', $order);

        if (! $order->statusEnum()->canTransitionTo(PurchaseOrderStatus::Rejected)) {
            $this->dispatch('toast', type: 'error', message: 'Status tidak dapat diubah.');

            return;
        }

        $this->validate([
            'rejectionReason' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        try {
            $order->update([
                'status' => PurchaseOrderStatus::Rejected->value,
                'rejected_by' => auth()->id(),
                'rejected_at' => now(),
                'rejection_reason' => $this->rejectionReason,
            ]);

            AuditLogger::log('REJECT', 'purchase_order', $order);

            try {
                NotificationService::notify($order->created_by, 'approval.result', 'Purchase Order ditolak', $order->number.' ditolak: '.$this->rejectionReason, PurchaseOrder::class, $order->id);
            } catch (\Throwable $e) {
            }

            $this->rejectionReason = '';
            $this->dispatch('toast', type: 'success', message: 'Ditolak.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function close(): void
    {
        $order = PurchaseOrder::findOrFail($this->purchaseOrderId);

        $this->authorize('close', $order);

        if (! $order->statusEnum()->canTransitionTo(PurchaseOrderStatus::Closed)) {
            $this->dispatch('toast', type: 'error', message: 'Status tidak dapat diubah.');

            return;
        }

        try {
            $order->update([
                'status' => PurchaseOrderStatus::Closed->value,
                'closed_by' => auth()->id(),
                'closed_at' => now(),
            ]);

            AuditLogger::log('CLOSE', 'purchase_order', $order);
            $this->dispatch('toast', type: 'success', message: 'Purchase order ditutup.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function render()
    {
        $order = PurchaseOrder::with(['supplier', 'warehouse', 'items.item', 'items.unit', 'goodsReceipts', 'creator', 'submitter', 'approver', 'rejecter', 'closer'])
            ->findOrFail($this->purchaseOrderId);

        return view('livewire.transactions.purchase-order-show', [
            'order' => $order,
        ]);
    }
}
