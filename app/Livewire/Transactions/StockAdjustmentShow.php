<?php

namespace App\Livewire\Transactions;

use App\Enums\TransactionStatus;
use App\Models\StockAdjustment;
use App\Services\Inventory\InventoryService;
use App\Services\Support\AuditLogger;
use App\Services\Support\NotificationService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Detail Stock Adjustment')]
class StockAdjustmentShow extends Component
{
    public int $adjustmentId;

    public string $rejectionReason = '';

    public string $reversalReason = '';

    public function mount($adjustment): void
    {
        $model = $adjustment instanceof StockAdjustment ? $adjustment : StockAdjustment::findOrFail($adjustment);

        $this->authorize('view', $model);

        $this->adjustmentId = $model->id;
    }

    public function submit(): void
    {
        $adjustment = StockAdjustment::findOrFail($this->adjustmentId);

        $this->authorize('submit', $adjustment);

        if (! $adjustment->statusEnum()->canTransitionTo(TransactionStatus::Submitted)) {
            $this->dispatch('toast', type: 'error', message: 'Status tidak dapat diubah.');

            return;
        }

        try {
            $adjustment->update([
                'status' => TransactionStatus::Submitted->value,
                'submitted_by' => auth()->id(),
                'submitted_at' => now(),
            ]);

            AuditLogger::log('SUBMIT', 'stock_adjustment', $adjustment);

            try {
                NotificationService::notifyApprovers('approval.request', 'Approval Adjustment', $adjustment->number.' menunggu persetujuan', StockAdjustment::class, $adjustment->id);
            } catch (\Throwable $e) {
            }

            $this->dispatch('toast', type: 'success', message: 'Berhasil diajukan.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function approve(): void
    {
        $adjustment = StockAdjustment::findOrFail($this->adjustmentId);

        $this->authorize('approve', $adjustment);

        if (! $adjustment->statusEnum()->canTransitionTo(TransactionStatus::Approved)) {
            $this->dispatch('toast', type: 'error', message: 'Status tidak dapat diubah.');

            return;
        }

        try {
            $adjustment->update([
                'status' => TransactionStatus::Approved->value,
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

            AuditLogger::log('APPROVE', 'stock_adjustment', $adjustment);

            try {
                NotificationService::notify($adjustment->created_by, 'approval.result', 'Adjustment disetujui', $adjustment->number.' telah disetujui', StockAdjustment::class, $adjustment->id);
            } catch (\Throwable $e) {
            }

            $this->dispatch('toast', type: 'success', message: 'Berhasil disetujui.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function reject(): void
    {
        $adjustment = StockAdjustment::findOrFail($this->adjustmentId);

        $this->authorize('reject', $adjustment);

        if (! $adjustment->statusEnum()->canTransitionTo(TransactionStatus::Rejected)) {
            $this->dispatch('toast', type: 'error', message: 'Status tidak dapat diubah.');

            return;
        }

        $this->validate([
            'rejectionReason' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        try {
            $adjustment->update([
                'status' => TransactionStatus::Rejected->value,
                'rejected_by' => auth()->id(),
                'rejected_at' => now(),
                'rejection_reason' => $this->rejectionReason,
            ]);

            AuditLogger::log('REJECT', 'stock_adjustment', $adjustment);

            try {
                NotificationService::notify($adjustment->created_by, 'approval.result', 'Adjustment ditolak', $adjustment->number.' ditolak: '.$this->rejectionReason, StockAdjustment::class, $adjustment->id);
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
        $adjustment = StockAdjustment::findOrFail($this->adjustmentId);

        $this->authorize('post', $adjustment);

        if (! $adjustment->statusEnum()->canTransitionTo(TransactionStatus::Posted)) {
            $this->dispatch('toast', type: 'error', message: 'Status tidak dapat diubah.');

            return;
        }

        try {
            InventoryService::postStockAdjustment($adjustment);
            $this->adjustmentId = $adjustment->fresh()->id;
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

        $adjustment = StockAdjustment::findOrFail($this->adjustmentId);

        $this->authorize('reverse', $adjustment);

        try {
            InventoryService::reverseStockAdjustment($adjustment, $this->reversalReason);
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
        $adjustment = StockAdjustment::with(['warehouse', 'location.rack.zone.warehouse', 'items.item', 'creator', 'submitter', 'approver', 'rejecter', 'poster', 'reverser'])
            ->findOrFail($this->adjustmentId);

        return view('livewire.transactions.stock-adjustment-show', [
            'adjustment' => $adjustment,
        ]);
    }
}
