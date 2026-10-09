<?php

namespace App\Livewire\Inventory;

use App\Models\BatchRecall;
use App\Services\Inventory\RecallService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Tarik Batch')]
class BatchRecallIndex extends Component
{
    use WithPagination;

    public string $mode = 'batch';

    public string $query = '';

    public string $reason = '';

    public ?array $impact = null;

    public string $recalledFilter = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->hasPermission('stock.movement'), 403);

        if (request()->filled('batch')) {
            $this->mode = 'batch';
            $this->query = (string) request()->string('batch');
            $this->search();
        } elseif (request()->filled('serial')) {
            $this->mode = 'serial';
            $this->query = (string) request()->string('serial');
            $this->search();
        }
    }

    public function search(): void
    {
        $value = trim($this->query);

        if ($value === '') {
            $this->dispatch('toast', type: 'error', message: __('Masukkan nomor batch / serial.'));

            return;
        }

        $impact = RecallService::impact($this->mode, $value);

        $this->impact = [
            'type' => $impact['type'],
            'value' => $impact['value'],
            'on_hand' => $impact['on_hand'],
            'stock' => $impact['stock']->toArray(),
            'documents' => $impact['documents']->toArray(),
            'active_recall' => $impact['active_recall']?->toArray(),
        ];

        if ($impact['documents']->isEmpty()) {
            $this->dispatch('toast', type: 'warning', message: __('Tidak ada riwayat untuk ').$value);
        }
    }

    public function recall(): void
    {
        $value = trim($this->query);
        $reason = trim($this->reason);

        if ($value === '') {
            $this->dispatch('toast', type: 'error', message: __('Masukkan nomor batch / serial.'));

            return;
        }

        if ($reason === '') {
            $this->addError('reason', __('Alasan wajib diisi.'));

            return;
        }

        RecallService::recall($this->mode, $value, $reason);

        $this->reason = '';
        $this->search();
        $this->dispatch('toast', type: 'success', message: __('Batch ditandai sebagai ditarik.'));
    }

    public function lift(int $recallId): void
    {
        $recall = BatchRecall::findOrFail($recallId);
        RecallService::lift($recall);

        $this->search();
        $this->dispatch('toast', type: 'success', message: __('Penarikan dicabut.'));
    }

    protected function recallQuery()
    {
        return BatchRecall::query()
            ->orderByDesc('recalled_at')
            ->when($this->recalledFilter === 'active', fn ($q) => $q->where('status', 'active'))
            ->when($this->recalledFilter === 'lifted', fn ($q) => $q->where('status', 'lifted'));
    }

    public function render()
    {
        return view('livewire.inventory.batch-recall-index', [
            'recalls' => $this->recallQuery()->paginate(10, pageName: 'recallsPage'),
        ]);
    }
}
