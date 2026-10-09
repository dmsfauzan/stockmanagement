<?php

namespace App\Livewire\Transactions;

use App\Models\SupplierReturn;
use App\Services\Workflow\DocumentWorkflow;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Detail Retur Pembelian')]
class SupplierReturnShow extends Component
{
    public int $returnId;

    public string $rejectionReason = '';

    public string $reversalReason = '';

    public function mount($supplierReturn): void
    {
        $model = $supplierReturn instanceof SupplierReturn ? $supplierReturn : SupplierReturn::findOrFail($supplierReturn);

        $this->authorize('view', $model);

        $this->returnId = $model->id;
    }

    public function submit(): void
    {
        $return = SupplierReturn::findOrFail($this->returnId);
        $this->authorize('submit', $return);
        try {
            DocumentWorkflow::submitSupplierReturn($this->returnId);
            $this->dispatch('toast', type: 'success', message: __('Berhasil diajukan.'));
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function approve(): void
    {
        $return = SupplierReturn::findOrFail($this->returnId);
        $this->authorize('approve', $return);
        try {
            DocumentWorkflow::approveSupplierReturn($this->returnId);
            $this->dispatch('toast', type: 'success', message: __('Berhasil disetujui.'));
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function reject(): void
    {
        $return = SupplierReturn::findOrFail($this->returnId);
        $this->authorize('approve', $return);
        $this->validate([
            'rejectionReason' => ['required', 'string', 'min:3', 'max:1000'],
        ]);
        try {
            DocumentWorkflow::rejectSupplierReturn($this->returnId, $this->rejectionReason);
            $this->rejectionReason = '';
            $this->dispatch('toast', type: 'success', message: __('Ditolak.'));
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function post(): void
    {
        $return = SupplierReturn::findOrFail($this->returnId);
        $this->authorize('post', $return);
        try {
            DocumentWorkflow::postSupplierReturn($this->returnId);
            $this->dispatch('toast', type: 'success', message: __('Posting berhasil.'));
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function reverse(): void
    {
        $return = SupplierReturn::findOrFail($this->returnId);
        $this->authorize('reverse', $return);
        $this->validate([
            'reversalReason' => ['required', 'string', 'min:3', 'max:1000'],
        ]);
        try {
            DocumentWorkflow::reverseSupplierReturn($this->returnId, $this->reversalReason);
            $this->reversalReason = '';
            $this->dispatch('toast', type: 'success', message: __('Reversal berhasil.'));
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function render()
    {
        $return = SupplierReturn::with(['supplier', 'goodsReceipt', 'warehouse', 'location', 'items.item', 'items.unit', 'creator', 'submitter', 'approver', 'poster', 'reverser', 'approvalHistories.user'])
            ->findOrFail($this->returnId);

        return view('livewire.transactions.supplier-return-show', [
            'return' => $return,
        ]);
    }
}
