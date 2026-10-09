<?php

namespace App\Livewire\Transactions;

use App\Models\CustomerReturn;
use App\Services\Workflow\DocumentWorkflow;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Detail Retur Penjualan')]
class CustomerReturnShow extends Component
{
    public int $returnId;

    public string $rejectionReason = '';

    public string $reversalReason = '';

    public function mount($customerReturn): void
    {
        $model = $customerReturn instanceof CustomerReturn ? $customerReturn : CustomerReturn::findOrFail($customerReturn);

        $this->authorize('view', $model);

        $this->returnId = $model->id;
    }

    public function submit(): void
    {
        $return = CustomerReturn::findOrFail($this->returnId);
        $this->authorize('submit', $return);
        try {
            DocumentWorkflow::submitCustomerReturn($this->returnId);
            $this->dispatch('toast', type: 'success', message: __('Berhasil diajukan.'));
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function approve(): void
    {
        $return = CustomerReturn::findOrFail($this->returnId);
        $this->authorize('approve', $return);
        try {
            DocumentWorkflow::approveCustomerReturn($this->returnId);
            $this->dispatch('toast', type: 'success', message: __('Berhasil disetujui.'));
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function reject(): void
    {
        $return = CustomerReturn::findOrFail($this->returnId);
        $this->authorize('approve', $return);
        $this->validate([
            'rejectionReason' => ['required', 'string', 'min:3', 'max:1000'],
        ]);
        try {
            DocumentWorkflow::rejectCustomerReturn($this->returnId, $this->rejectionReason);
            $this->rejectionReason = '';
            $this->dispatch('toast', type: 'success', message: __('Ditolak.'));
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function post(): void
    {
        $return = CustomerReturn::findOrFail($this->returnId);
        $this->authorize('post', $return);
        try {
            DocumentWorkflow::postCustomerReturn($this->returnId);
            $this->dispatch('toast', type: 'success', message: __('Posting berhasil.'));
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function reverse(): void
    {
        $return = CustomerReturn::findOrFail($this->returnId);
        $this->authorize('reverse', $return);
        $this->validate([
            'reversalReason' => ['required', 'string', 'min:3', 'max:1000'],
        ]);
        try {
            DocumentWorkflow::reverseCustomerReturn($this->returnId, $this->reversalReason);
            $this->reversalReason = '';
            $this->dispatch('toast', type: 'success', message: __('Reversal berhasil.'));
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function render()
    {
        $return = CustomerReturn::with(['customer', 'goodsIssue', 'warehouse', 'location', 'items.item', 'items.unit', 'creator', 'submitter', 'approver', 'poster', 'reverser', 'approvalHistories.user'])
            ->findOrFail($this->returnId);

        return view('livewire.transactions.customer-return-show', [
            'return' => $return,
        ]);
    }
}
