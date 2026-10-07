<?php

namespace App\Livewire\Transactions;

use App\Models\PurchaseOrder;
use App\Services\Workflow\DocumentWorkflow;
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
        try {
            DocumentWorkflow::submitPo($this->purchaseOrderId);
            $this->dispatch('toast', type: 'success', message: 'Berhasil diajukan.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function approve(): void
    {
        $order = PurchaseOrder::findOrFail($this->purchaseOrderId);
        $this->authorize('approve', $order);
        try {
            DocumentWorkflow::approvePo($this->purchaseOrderId);
            $this->dispatch('toast', type: 'success', message: 'Berhasil disetujui.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function reject(): void
    {
        $order = PurchaseOrder::findOrFail($this->purchaseOrderId);
        $this->authorize('approve', $order);
        $this->validate([
            'rejectionReason' => ['required', 'string', 'min:3', 'max:1000'],
        ]);
        try {
            DocumentWorkflow::rejectPo($this->purchaseOrderId, $this->rejectionReason);
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
        try {
            DocumentWorkflow::closePo($this->purchaseOrderId);
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
