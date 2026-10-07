<?php

namespace App\Livewire\Transactions;

use App\Enums\OpnameStatus;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Services\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Counting Stock Opname')]
class StockOpnameCount extends Component
{
    public int $opnameId;

    public array $items = [];

    public function mount($opname): void
    {
        $model = $opname instanceof StockOpname ? $opname : StockOpname::findOrFail($opname);

        $this->authorize('view', $model);

        $this->opnameId = $model->id;

        $this->items = $model->items()
            ->with('item')
            ->join('items', 'items.id', '=', 'stock_opname_items.item_id')
            ->orderBy('items.name')
            ->get([
                'stock_opname_items.id',
                'stock_opname_items.item_id',
                'items.sku as sku',
                'items.name as item_name',
                'stock_opname_items.system_quantity',
                'stock_opname_items.physical_quantity',
                'stock_opname_items.difference',
                'stock_opname_items.reason',
                'stock_opname_items.notes',
            ])
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'item_id' => (int) $row->item_id,
                'sku' => (string) $row->sku,
                'name' => (string) $row->item_name,
                'system_quantity' => (int) $row->system_quantity,
                'physical_quantity' => $row->physical_quantity === null ? null : (int) $row->physical_quantity,
                'difference' => (int) $row->difference,
                'reason' => (string) ($row->reason ?? ''),
                'notes' => (string) ($row->notes ?? ''),
            ])
            ->values()
            ->all();
    }

    public function updated($name, $value): void
    {
        if (preg_match('/^items\.(\d+)\.physical_quantity$/', $name, $matches)) {
            $index = (int) $matches[1];
            $this->recomputeDifference($index);
        }
    }

    protected function recomputeDifference(int $index): void
    {
        if (! isset($this->items[$index])) {
            return;
        }

        $physical = $this->items[$index]['physical_quantity'];
        $system = (int) ($this->items[$index]['system_quantity'] ?? 0);

        $this->items[$index]['difference'] = ($physical === null || $physical === '')
            ? 0
            : (int) $physical - $system;
    }

    public function fillEmpties(): void
    {
        foreach ($this->items as $index => $row) {
            if ($row['physical_quantity'] === null || $row['physical_quantity'] === '') {
                $this->items[$index]['physical_quantity'] = (int) $row['system_quantity'];
                $this->recomputeDifference($index);
            }
        }

        $this->dispatch('toast', type: 'success', message: 'Item kosong diisi dengan system qty.');
    }

    public function clearAll(): void
    {
        foreach ($this->items as $index => $row) {
            $this->items[$index]['physical_quantity'] = null;
            $this->items[$index]['difference'] = 0;
        }

        $this->dispatch('toast', type: 'info', message: 'Semua physical qty dikosongkan.');
    }

    public function submit()
    {
        $opname = StockOpname::findOrFail($this->opnameId);

        $this->authorize('submit', $opname);

        if (! $opname->statusEnum()->canTransitionTo(OpnameStatus::Submitted)) {
            $this->dispatch('toast', type: 'error', message: 'Status tidak dapat diubah.');

            return;
        }

        foreach ($this->items as $row) {
            if ($row['physical_quantity'] === null || $row['physical_quantity'] === '') {
                $this->dispatch('toast', type: 'error', message: 'Lengkapi physical qty');

                return;
            }
        }

        DB::transaction(function () use ($opname): void {
            foreach ($this->items as $row) {
                $system = (int) $row['system_quantity'];
                $physical = (int) $row['physical_quantity'];

                StockOpnameItem::whereKey($row['id'])->update([
                    'physical_quantity' => $physical,
                    'difference' => $physical - $system,
                    'reason' => $row['reason'] !== '' ? $row['reason'] : null,
                    'notes' => $row['notes'] !== '' ? $row['notes'] : null,
                ]);
            }

            $opname->update([
                'status' => OpnameStatus::Submitted->value,
                'submitted_by' => auth()->id(),
                'submitted_at' => now(),
            ]);

            AuditLogger::log('SUBMIT', 'stock_opname', $opname);
        });

        $this->dispatch('toast', type: 'success', message: 'Opname berhasil diajukan.');

        return $this->redirect(route('stock-opnames.show', $opname), navigate: true);
    }

    public function render()
    {
        $opname = StockOpname::with(['warehouse', 'location'])->findOrFail($this->opnameId);

        return view('livewire.transactions.stock-opname-count', [
            'opname' => $opname,
            'reasons' => ['Found', 'Damaged', 'Lost', 'Expired', 'Data Entry Error', 'Other'],
        ]);
    }
}
