<?php

namespace App\Livewire\Transactions;

use App\Enums\OpnameStatus;
use App\Models\StockOpname;
use App\Services\Support\AuditLogger;
use App\Services\Workflow\DocumentWorkflow;
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
        try {
            DocumentWorkflow::startCountingOpname($this->opnameId);
            $this->dispatch('toast', type: 'success', message: __('Opname masuk tahap counting.'));
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function submit(): void
    {
        $opname = StockOpname::findOrFail($this->opnameId);
        $this->authorize('submit', $opname);
        try {
            DocumentWorkflow::submitOpname($this->opnameId);
            $this->dispatch('toast', type: 'success', message: __('Berhasil diajukan.'));
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function reject(): void
    {
        $opname = StockOpname::findOrFail($this->opnameId);
        $this->authorize('reject', $opname);
        $this->validate([
            'rejectionReason' => ['required', 'string', 'min:3', 'max:1000'],
        ]);
        try {
            DocumentWorkflow::rejectOpname($this->opnameId, $this->rejectionReason);
            $this->rejectionReason = '';
            $this->dispatch('toast', type: 'success', message: __('Ditolak.'));
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function approveAndComplete(): void
    {
        $opname = StockOpname::findOrFail($this->opnameId);
        $this->authorize('approve', $opname);
        try {
            DocumentWorkflow::approveAndCompleteOpname($this->opnameId);
            $this->dispatch('toast', type: 'success', message: __('Opname disetujui & adjustment diposting'));
        } catch (\Throwable $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function delete(): void
    {
        $opname = StockOpname::findOrFail($this->opnameId);

        $this->authorize('view', $opname);

        if ($opname->status !== OpnameStatus::Draft->value) {
            $this->dispatch('toast', type: 'error', message: __('Hanya draft yang dapat dihapus.'));

            return;
        }

        $opname->delete();
        AuditLogger::log('DELETE', 'stock_opname', $opname);
        $this->dispatch('toast', type: 'success', message: __('Opname dihapus.'));
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
