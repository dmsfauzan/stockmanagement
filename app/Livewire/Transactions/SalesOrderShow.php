<?php

namespace App\Livewire\Transactions;

use App\Models\SalesOrder;
use App\Services\Workflow\DocumentWorkflow;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Detail Sales Order')]
class SalesOrderShow extends Component
{
    public int $salesOrderId;

    public string $rejectionReason = '';

    public function mount($salesOrder): void
    {
        $model = $salesOrder instanceof SalesOrder ? $salesOrder : SalesOrder::findOrFail($salesOrder);

        $this->authorize('view', $model);

        $this->salesOrderId = $model->id;
    }

    public function submit(): void
    {
        $order = SalesOrder::findOrFail($this->salesOrderId);
        $this->authorize('submit', $order);
        try {
            DocumentWorkflow::submitSo($this->salesOrderId);
            $this->dispatch('toast', type: 'success', message: 'Berhasil diajukan.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function approve(): void
    {
        $order = SalesOrder::findOrFail($this->salesOrderId);
        $this->authorize('approve', $order);
        try {
            DocumentWorkflow::approveSo($this->salesOrderId);
            $this->dispatch('toast', type: 'success', message: 'Berhasil disetujui.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function reject(): void
    {
        $order = SalesOrder::findOrFail($this->salesOrderId);
        $this->authorize('approve', $order);
        $this->validate([
            'rejectionReason' => ['required', 'string', 'min:3', 'max:1000'],
        ]);
        try {
            DocumentWorkflow::rejectSo($this->salesOrderId, $this->rejectionReason);
            $this->rejectionReason = '';
            $this->dispatch('toast', type: 'success', message: 'Ditolak.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function close(): void
    {
        $order = SalesOrder::findOrFail($this->salesOrderId);
        $this->authorize('close', $order);
        try {
            DocumentWorkflow::closeSo($this->salesOrderId);
            $this->dispatch('toast', type: 'success', message: 'Sales order ditutup.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function render()
    {
        $order = SalesOrder::with(['customer', 'warehouse', 'items.item', 'items.unit', 'goodsIssues', 'creator', 'submitter', 'approver', 'rejecter', 'closer'])
            ->findOrFail($this->salesOrderId);

        return view('livewire.transactions.sales-order-show', [
            'order' => $order,
        ]);
    }
}
