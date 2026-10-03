<?php

namespace App\Livewire\Transactions;

use App\Enums\OpnameStatus;
use App\Models\StockOpname;
use App\Services\Inventory\InventoryService;
use App\Services\Support\AuditLogger;
use App\Services\Support\NotificationService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Detail Stock Opname')]
class StockOpnameShow extends Component
{
    public int $opnameId;

    public string $rejectionReason = '';

    public function mount($opname): void
    {
        $model = $opname instanceof StockOpname ? $opname : StockOpname::findOrFail($opname);

        $this->authorize('view', $model);

        $this->opnameId = $model->id;
    }

    public function startCounting(): void
    {
        $opname = StockOpname::findOrFail($this->opnameId);

        $this->authorize('submit', $opname);

        if (! $opname->statusEnum()->canTransitionTo(OpnameStatus::Counting)) {
            $this->dispatch('toast', type: 'error', message: 'Status tidak dapat diubah.');

            return;
        }

        try {
            $opname->update(['status' => OpnameStatus::Counting->value]);
            AuditLogger::log('COUNTING', 'stock_opname', $opname);
            $this->dispatch('toast', type: 'success', message: 'Opname masuk tahap counting.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function submit(): void
    {
        $opname = StockOpname::findOrFail($this->opnameId);

        $this->authorize('submit', $opname);

        if (! $opname->statusEnum()->canTransitionTo(OpnameStatus::Submitted)) {
            $this->dispatch('toast', type: 'error', message: 'Status tidak dapat diubah.');

            return;
        }

        if ($opname->items()->whereNull('physical_quantity')->exists()) {
            $this->dispatch('toast', type: 'error', message: 'Lengkapi physical qty');

            return;
        }

        try {
            $opname->update([
                'status' => OpnameStatus::Submitted->value,
                'submitted_by' => auth()->id(),
                'submitted_at' => now(),
            ]);
            AuditLogger::log('SUBMIT', 'stock_opname', $opname);

            try {
                NotificationService::notifyApprovers('approval.request', 'Approval Opname', $opname->number.' menunggu persetujuan', StockOpname::class, $opname->id);
            } catch (\Throwable $e) {
            }

            $this->dispatch('toast', type: 'success', message: 'Berhasil diajukan.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function reject(): void
    {
        $opname = StockOpname::findOrFail($this->opnameId);

        $this->authorize('reject', $opname);

        if (! $opname->statusEnum()->canTransitionTo(OpnameStatus::Rejected)) {
            $this->dispatch('toast', type: 'error', message: 'Status tidak dapat diubah.');

            return;
        }

        $this->validate([
            'rejectionReason' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        try {
            $opname->update([
                'status' => OpnameStatus::Rejected->value,
                'rejected_by' => auth()->id(),
                'rejected_at' => now(),
                'rejection_reason' => $this->rejectionReason,
            ]);
            AuditLogger::log('REJECT', 'stock_opname', $opname);

            try {
                NotificationService::notify($opname->created_by, 'approval.result', 'Opname ditolak', $opname->number.' ditolak: '.$this->rejectionReason, StockOpname::class, $opname->id);
            } catch (\Throwable $e) {
            }

            $this->rejectionReason = '';
            $this->dispatch('toast', type: 'success', message: 'Ditolak.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function approveAndComplete(): void
    {
        $opname = StockOpname::findOrFail($this->opnameId);

        $this->authorize('approve', $opname);

        if (! $opname->statusEnum()->canTransitionTo(OpnameStatus::Approved)) {
            $this->dispatch('toast', type: 'error', message: 'Status tidak dapat diubah.');

            return;
        }

        if ($opname->items()->whereNull('physical_quantity')->exists()) {
            $this->dispatch('toast', type: 'error', message: 'Opname belum lengkap');

            return;
        }

        try {
            $opname->update([
                'status' => OpnameStatus::Approved->value,
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);
            AuditLogger::log('APPROVE', 'stock_opname', $opname);

            InventoryService::completeStockOpname($opname->fresh('items'));

            try {
                NotificationService::notify($opname->created_by, 'opname.completed', 'Opname selesai', $opname->number.' telah selesai', StockOpname::class, $opname->id);
            } catch (\Throwable $e) {
            }

            $this->dispatch('toast', type: 'success', message: 'Opname disetujui & adjustment diposting');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function delete(): void
    {
        $opname = StockOpname::findOrFail($this->opnameId);

        $this->authorize('view', $opname);

        if ($opname->status !== OpnameStatus::Draft->value) {
            $this->dispatch('toast', type: 'error', message: 'Hanya draft yang dapat dihapus.');

            return;
        }

        $opname->delete();
        AuditLogger::log('DELETE', 'stock_opname', $opname);
        $this->dispatch('toast', type: 'success', message: 'Opname dihapus.');
        $this->redirect(route('stock-opnames.index'), navigate: true);
    }

    public function render()
    {
        $opname = StockOpname::with(['warehouse', 'location.rack.zone.warehouse', 'items.item', 'creator', 'submitter', 'approver', 'rejecter', 'stockAdjustment'])
            ->findOrFail($this->opnameId);

        return view('livewire.transactions.stock-opname-show', [
            'opname' => $opname,
        ]);
    }
}
