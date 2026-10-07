<?php

namespace App\Livewire\Transactions;

use App\Models\GoodsReceipt;
use App\Services\Inventory\InventoryService;
use App\Services\Workflow\DocumentWorkflow;
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
        try {
            DocumentWorkflow::submitReceipt($this->receiptId);
            $this->dispatch('toast', type: 'success', message: 'Berhasil diajukan.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function approve(): void
    {
        $receipt = GoodsReceipt::findOrFail($this->receiptId);
        $this->authorize('approve', $receipt);
        try {
            DocumentWorkflow::approveReceipt($this->receiptId);
            $this->dispatch('toast', type: 'success', message: 'Berhasil disetujui.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function reject(): void
    {
        $receipt = GoodsReceipt::findOrFail($this->receiptId);
        $this->authorize('approve', $receipt);
        $this->validate([
            'rejectionReason' => ['required', 'string', 'min:3', 'max:1000'],
        ]);
        try {
            DocumentWorkflow::rejectReceipt($this->receiptId, $this->rejectionReason);
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
        try {
            DocumentWorkflow::postReceipt($this->receiptId);
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
