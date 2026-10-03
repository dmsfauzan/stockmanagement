<?php

namespace App\Livewire\Transactions;

use App\Enums\TransactionStatus;
use App\Models\GoodsIssue;
use App\Services\Inventory\InventoryService;
use App\Services\Inventory\ReservationService;
use App\Services\Support\AuditLogger;
use App\Services\Support\NotificationService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Detail Barang Keluar')]
class GoodsIssueShow extends Component
{
    public int $issueId;

    public string $rejectionReason = '';

    public string $reversalReason = '';

    public function mount($issue): void
    {
        $model = $issue instanceof GoodsIssue ? $issue : GoodsIssue::findOrFail($issue);

        $this->authorize('view', $model);

        $this->issueId = $model->id;
    }

    public function submit(): void
    {
        $issue = GoodsIssue::findOrFail($this->issueId);

        $this->authorize('submit', $issue);

        if (! $issue->statusEnum()->canTransitionTo(TransactionStatus::Submitted)) {
            $this->dispatch('toast', type: 'error', message: 'Status tidak dapat diubah.');

            return;
        }

        try {
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

            try {
                NotificationService::notifyApprovers('approval.request', 'Approval Barang Keluar', $issue->number.' menunggu persetujuan', GoodsIssue::class, $issue->id);
            } catch (\Throwable $e) {
            }

            $this->dispatch('toast', type: 'success', message: 'Berhasil diajukan.');
        } catch (\Throwable $e) {
            $message = str_contains($e->getMessage(), 'reserve')
                ? 'Stok tersedia tidak mencukupi untuk direservasi.'
                : $e->getMessage();

            $this->dispatch('toast', type: 'error', message: $message);
        }
    }

    public function approve(): void
    {
        $issue = GoodsIssue::findOrFail($this->issueId);

        $this->authorize('approve', $issue);

        if (! $issue->statusEnum()->canTransitionTo(TransactionStatus::Approved)) {
            $this->dispatch('toast', type: 'error', message: 'Status tidak dapat diubah.');

            return;
        }

        try {
            $issue->update([
                'status' => TransactionStatus::Approved->value,
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

            AuditLogger::log('APPROVE', 'goods_issue', $issue);

            try {
                NotificationService::notify($issue->created_by, 'approval.result', 'Barang Keluar disetujui', $issue->number.' telah disetujui', GoodsIssue::class, $issue->id);
            } catch (\Throwable $e) {
            }

            $this->dispatch('toast', type: 'success', message: 'Berhasil disetujui.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function reject(): void
    {
        $issue = GoodsIssue::findOrFail($this->issueId);

        $this->authorize('approve', $issue);

        if (! $issue->statusEnum()->canTransitionTo(TransactionStatus::Rejected)) {
            $this->dispatch('toast', type: 'error', message: 'Status tidak dapat diubah.');

            return;
        }

        $this->validate([
            'rejectionReason' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        try {
            $issue->update([
                'status' => TransactionStatus::Rejected->value,
                'rejected_by' => auth()->id(),
                'rejected_at' => now(),
                'rejection_reason' => $this->rejectionReason,
            ]);

            AuditLogger::log('REJECT', 'goods_issue', $issue);

            try {
                ReservationService::release(GoodsIssue::class, $issue->id);
            } catch (\Throwable $e) {
            }

            try {
                NotificationService::notify($issue->created_by, 'approval.result', 'Barang Keluar ditolak', $issue->number.' ditolak: '.$this->rejectionReason, GoodsIssue::class, $issue->id);
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
        $issue = GoodsIssue::findOrFail($this->issueId);

        $this->authorize('post', $issue);

        if (! $issue->statusEnum()->canTransitionTo(TransactionStatus::Posted)) {
            $this->dispatch('toast', type: 'error', message: 'Status tidak dapat diubah.');

            return;
        }

        try {
            InventoryService::postGoodsIssue($issue);
            $this->dispatch('toast', type: 'success', message: 'Posting berhasil.');
        } catch (\Throwable $e) {
            $message = $e->getMessage();

            if (str_contains(strtolower($message), 'insufficient stock')) {
                $message = 'Insufficient stock: '.$message;
            }

            $this->dispatch('toast', type: 'error', message: $message);
        }
    }

    public function reverse(): void
    {
        $this->validate([
            'reversalReason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $issue = GoodsIssue::findOrFail($this->issueId);

        $this->authorize('reverse', $issue);

        try {
            InventoryService::reverseGoodsIssue($issue, $this->reversalReason);
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
        $issue = GoodsIssue::with(['customer', 'warehouse', 'issueItems.item', 'issueItems.unit', 'issueItems.location.rack.zone.warehouse', 'creator', 'submitter', 'approver', 'rejecter', 'poster', 'reverser'])
            ->findOrFail($this->issueId);

        return view('livewire.transactions.goods-issue-show', [
            'issue' => $issue,
        ]);
    }
}
