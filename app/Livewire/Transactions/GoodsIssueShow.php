<?php

namespace App\Livewire\Transactions;

use App\Models\GoodsIssue;
use App\Services\Inventory\InventoryService;
use App\Services\Workflow\DocumentWorkflow;
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
        try {
            DocumentWorkflow::submitIssue($this->issueId);
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
        try {
            DocumentWorkflow::approveIssue($this->issueId);
            $this->dispatch('toast', type: 'success', message: 'Berhasil disetujui.');
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function reject(): void
    {
        $issue = GoodsIssue::findOrFail($this->issueId);
        $this->authorize('approve', $issue);
        $this->validate([
            'rejectionReason' => ['required', 'string', 'min:3', 'max:1000'],
        ]);
        try {
            DocumentWorkflow::rejectIssue($this->issueId, $this->rejectionReason);
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
        try {
            DocumentWorkflow::postIssue($this->issueId);
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
