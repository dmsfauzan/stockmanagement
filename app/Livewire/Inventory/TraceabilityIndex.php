<?php

namespace App\Livewire\Inventory;

use App\Services\Inventory\TraceabilityService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Lacak Lot / Serial')]
class TraceabilityIndex extends Component
{
    public string $mode = 'batch';

    public string $query = '';

    public ?array $result = null;

    public function mount(): void
    {
        abort_unless(auth()->user()->hasPermission('stock.movement'), 403);

        if (request()->filled('serial')) {
            $this->mode = 'serial';
            $this->query = (string) request()->string('serial');
            $this->search();
        } elseif (request()->filled('batch')) {
            $this->mode = 'batch';
            $this->query = (string) request()->string('batch');
            $this->search();
        }
    }

    public function search(): void
    {
        $this->result = null;

        $value = trim($this->query);

        if ($value === '') {
            $this->dispatch('toast', type: 'error', message: __('Masukkan nomor batch / serial.'));

            return;
        }

        $this->result = $this->mode === 'serial'
            ? TraceabilityService::forSerial($value)
            : TraceabilityService::forBatch($value);

        if ($this->result['summary']['events'] === 0) {
            $this->dispatch('toast', type: 'warning', message: __('Tidak ada riwayat untuk ').$value);
        }
    }

    public function render()
    {
        return view('livewire.inventory.traceability-index');
    }
}
