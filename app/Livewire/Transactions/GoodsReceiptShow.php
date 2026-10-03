<?php

namespace App\Livewire\Transactions;

use App\Enums\TransactionStatus;
use App\Models\GoodsReceipt;
use App\Services\Inventory\InventoryService;
use App\Services\Support\AuditLogger;
use App\Services\Support\NotificationService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Detail Barang Masuk')]
class GoodsReceiptShow extends Component
{
    public int $receiptId;

    public string $rejectionReason = '';

    public string $reversalReason = '';

    public function mount($receipt): void
    {
        $model = $receipt instanceof GoodsReceipt ? $receipt : GoodsReceipt::findOrFail($receipt);

        $this->authorize('view', $model);

        $this->receiptId = $model->id;
    }

    public function submit(): void
    {
        $receipt = GoodsReceipt::findOrFail($this->receiptId);

        $this->authorize('submit', $receipt);

        if (! $receipt->statusEnum()->canTransitionTo(TransactionStatus::Submitted)) {
            $this->dispatch('toast', type: 'error', message: 'Status tidak dapat diubah.');

            return;
        }

        try {
            $receipt->update([
                'status' => TransactionStatus::Submitted->value,
                'submitted_by' => auth()->id(),
                'submitted_at' => now(),
            ]);

            AuditLogger::log('SUBMIT', 'goods_receipt', $receipt);

            try {
                NotificationService::notifyApprovers('approval.request', 'Approval Barang Masuk', $receipt->number.' menunggu persetujuan', GoodsReceipt::class, $receipt->id);
            } catch (\Throwable $e) {
            }

            $this->dispatch('toast', type: 'success', message: 'Berhasil diajukan.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function approve(): void
    {
        $receipt = GoodsReceipt::findOrFail($this->receiptId);

        $this->authorize('approve', $receipt);

        if (! $receipt->statusEnum()->canTransitionTo(TransactionStatus::Approved)) {
            $this->dispatch('toast', type: 'error', message: 'Status tidak dapat diubah.');

            return;
        }

        try {
            $receipt->update([
                'status' => TransactionStatus::Approved->value,
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

            AuditLogger::log('APPROVE', 'goods_receipt', $receipt);

            try {
                NotificationService::notify($receipt->created_by, 'approval.result', 'Barang Masuk disetujui', $receipt->number.' telah disetujui', GoodsReceipt::class, $receipt->id);
            } catch (\Throwable $e) {
            }

            $this->dispatch('toast', type: 'success', message: 'Berhasil disetujui.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function reject(): void
    {
        $receipt = GoodsReceipt::findOrFail($this->receiptId);

        $this->authorize('approve', $receipt);

        if (! $receipt->statusEnum()->canTransitionTo(TransactionStatus::Rejected)) {
            $this->dispatch('toast', type: 'error', message: 'Status tidak dapat diubah.');

            return;
        }

        $this->validate([
            'rejectionReason' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        try {
            $receipt->update([
                'status' => TransactionStatus::Rejected->value,
                'rejected_by' => auth()->id(),
                'rejected_at' => now(),
                'rejection_reason' => $this->rejectionReason,
            ]);

            AuditLogger::log('REJECT', 'goods_receipt', $receipt);

            try {
                NotificationService::notify($receipt->created_by, 'approval.result', 'Barang Masuk ditolak', $receipt->number.' ditolak: '.$this->rejectionReason, GoodsReceipt::class, $receipt->id);
            } catch (\Throwable $e) {
            }

            $this->rejectionReason = '';
            $this->dispatch('toast', type: 'success', message: 'Ditolak.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function post(): void
    {
        $receipt = GoodsReceipt::findOrFail($this->receiptId);

        $this->authorize('post', $receipt);

        if (! $receipt->statusEnum()->canTransitionTo(TransactionStatus::Posted)) {
            $this->dispatch('toast', type: 'error', message: 'Status tidak dapat diubah.');

            return;
        }

        try {
            InventoryService::postGoodsReceipt($receipt);
            $this->dispatch('toast', type: 'success', message: 'Posting berhasil.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function reverse(): void
    {
        $this->validate([
            'reversalReason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $receipt = GoodsReceipt::findOrFail($this->receiptId);

        $this->authorize('reverse', $receipt);

        try {
            InventoryService::reverseGoodsReceipt($receipt, $this->reversalReason);
            $this->dispatch('toast', type: 'success', message: 'Reversal berhasil.');
        } catch (\Throwable $e) {
            $message = $e->getMessage();

            if (str_contains(strtolower($message), 'insufficient stock')) {
                $message = 'Insufficient stock: '.$message;
            }

            $this->dispatch('toast', type: 'error', message: $message);
        }
    }

    public function render()
    {
        $receipt = GoodsReceipt::with(['supplier', 'warehouse', 'receiptItems.item', 'receiptItems.unit', 'receiptItems.location.rack.zone.warehouse', 'creator', 'submitter', 'approver', 'rejecter', 'poster', 'reverser'])
            ->findOrFail($this->receiptId);

        return view('livewire.transactions.goods-receipt-show', [
            'receipt' => $receipt,
        ]);
    }
}
