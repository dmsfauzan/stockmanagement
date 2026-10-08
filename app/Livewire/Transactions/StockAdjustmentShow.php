<?php

namespace App\Livewire\Transactions;

use App\Models\StockAdjustment;
use App\Services\Inventory\InventoryService;
use App\Services\Workflow\DocumentWorkflow;
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
        try {
            DocumentWorkflow::submitAdjustment($this->adjustmentId);
            $this->dispatch('toast', type: 'success', message: __('Berhasil diajukan.'));
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function approve(): void
    {
        $adjustment = StockAdjustment::findOrFail($this->adjustmentId);
        $this->authorize('approve', $adjustment);
        try {
            DocumentWorkflow::approveAdjustment($this->adjustmentId);
            $this->dispatch('toast', type: 'success', message: __('Berhasil disetujui.'));
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function reject(): void
    {
        $adjustment = StockAdjustment::findOrFail($this->adjustmentId);
        $this->authorize('reject', $adjustment);
        $this->validate([
            'rejectionReason' => ['required', 'string', 'min:3', 'max:1000'],
        ]);
        try {
            DocumentWorkflow::rejectAdjustment($this->adjustmentId, $this->rejectionReason);
            $this->rejectionReason = '';
            $this->dispatch('toast', type: 'success', message: __('Ditolak.'));
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function post(): void
    {
        $adjustment = StockAdjustment::findOrFail($this->adjustmentId);
        $this->authorize('post', $adjustment);
        try {
            DocumentWorkflow::postAdjustment($this->adjustmentId);
            $this->adjustmentId = StockAdjustment::findOrFail($this->adjustmentId)->id;
            $this->dispatch('toast', type: 'success', message: __('Posting berhasil.'));
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
            $this->dispatch('toast', type: 'success', message: __('Reversal berhasil.'));
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
