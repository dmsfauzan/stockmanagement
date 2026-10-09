<?php

namespace App\Livewire\Picking;

use App\Models\PickList;
use App\Services\Inventory\PickService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Detail Pick List')]
class PickListShow extends Component
{
    public int $pickListId;

    /** @var array<int, array{picked_quantity:int, batch_number:string, serial_number:string}> */
    public array $picks = [];

    public string $cancelReason = '';

    public function mount($pickList): void
    {
        $model = $pickList instanceof PickList ? $pickList : PickList::findOrFail($pickList);

        abort_unless(auth()->user()->hasPermission('picking.view'), 403);
        abort_unless(auth()->user()?->canAccessWarehouse((int) $model->warehouse_id), 403);

        $this->pickListId = $model->id;

        foreach ($model->items as $item) {
            $this->picks[$item->id] = [
                'picked_quantity' => (int) $item->picked_quantity,
                'batch_number' => (string) ($item->batch_number ?? ''),
                'serial_number' => (string) ($item->serial_number ?? ''),
            ];
        }
    }

    protected function guard(string $permission): void
    {
        abort_unless(auth()->user()->hasPermission($permission), 403);
    }

    public function start(): void
    {
        $this->guard('picking.pick');

        try {
            PickService::start($this->fresh());
            $this->dispatch('toast', type: 'success', message: __('Picking dimulai.'));
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function confirmItem(int $itemId): void
    {
        $this->guard('picking.pick');

        $row = $this->picks[$itemId] ?? null;

        if (! $row) {
            return;
        }

        try {
            PickService::confirmItem(
                $this->fresh(),
                $itemId,
                (int) $row['picked_quantity'],
                $row['batch_number'] !== '' ? $row['batch_number'] : null,
                $row['serial_number'] !== '' ? $row['serial_number'] : null,
            );
            $this->dispatch('toast', type: 'success', message: __('Baris dikonfirmasi.'));
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function complete(): void
    {
        $this->guard('picking.pick');

        try {
            PickService::complete($this->fresh());
            $this->dispatch('toast', type: 'success', message: __('Picking selesai.'));
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function pack(): void
    {
        $this->guard('picking.pack');

        try {
            PickService::pack($this->fresh());
            $this->dispatch('toast', type: 'success', message: __('Dikemas.'));
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function cancel(): void
    {
        $this->guard('picking.pack');

        try {
            PickService::cancel($this->fresh(), $this->cancelReason !== '' ? $this->cancelReason : null);
            $this->cancelReason = '';
            $this->dispatch('toast', type: 'success', message: __('Pick list dibatalkan.'));
        } catch (\RuntimeException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    private function fresh(): PickList
    {
        return PickList::with('items')->findOrFail($this->pickListId);
    }

    public function render()
    {
        return view('livewire.picking.pick-list-show', [
            'pickList' => PickList::with(['warehouse', 'goodsIssue', 'salesOrder', 'assignee', 'items.item', 'items.unit', 'items.location.rack.zone.warehouse'])->findOrFail($this->pickListId),
        ]);
    }
}
