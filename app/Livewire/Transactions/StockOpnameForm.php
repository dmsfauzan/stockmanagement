<?php

namespace App\Livewire\Transactions;

use App\Enums\OpnameStatus;
use App\Models\Location;
use App\Models\StockBalance;
use App\Models\StockOpname;
use App\Models\Warehouse;
use App\Services\Support\AuditLogger;
use App\Services\Support\DocumentNumberService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Form Stock Opname')]
class StockOpnameForm extends Component
{
    public ?int $opnameId = null;

    public string $opname_date = '';

    public string $warehouse_id = '';

    public string $location_id = '';

    public string $notes = '';

    public function mount($opname = null): void
    {
        $this->opname_date = now()->format('Y-m-d');

        $model = $opname instanceof StockOpname ? $opname : ($opname ? StockOpname::findOrFail($opname) : null);

        if ($model) {
            $this->authorize('update', $model);

            if ($model->status !== OpnameStatus::Draft->value) {
                abort(403, 'Hanya opname draft yang dapat diubah.');
            }

            $this->opnameId = $model->id;
            $this->opname_date = $model->opname_date?->format('Y-m-d') ?? now()->format('Y-m-d');
            $this->warehouse_id = (string) $model->warehouse_id;
            $this->location_id = (string) ($model->location_id ?? '');
            $this->notes = (string) ($model->notes ?? '');
        } else {
            $this->authorize('create', StockOpname::class);
        }
    }

    protected function rules(): array
    {
        return [
            'opname_date' => ['required', 'date'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'location_id' => ['nullable', 'exists:locations,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function generateItems(): void
    {
        if (! $this->opnameId) {
            $this->dispatch('toast', type: 'error', message: 'Simpan opname terlebih dahulu.');

            return;
        }

        $opname = StockOpname::findOrFail($this->opnameId);

        if ($opname->status !== OpnameStatus::Draft->value) {
            $this->dispatch('toast', type: 'error', message: 'Hanya draft yang dapat generate items.');

            return;
        }

        $existingItemIds = $opname->items()->pluck('item_id')->all();

        $totals = StockBalance::where('warehouse_id', $opname->warehouse_id)
            ->when($opname->location_id, fn ($q) => $q->where('location_id', $opname->location_id))
            ->get(['item_id', 'quantity_on_hand'])
            ->groupBy('item_id')
            ->map(fn ($rows) => (int) $rows->sum('quantity_on_hand'));

        if ($totals->isEmpty()) {
            $this->dispatch('toast', type: 'warning', message: 'Tidak ada saldo stok untuk di-generate.');

            return;
        }

        $inserted = 0;
        foreach ($totals as $itemId => $qty) {
            if (in_array($itemId, $existingItemIds, true)) {
                continue;
            }

            $opname->items()->create([
                'item_id' => $itemId,
                'system_quantity' => $qty,
                'physical_quantity' => null,
                'difference' => 0,
            ]);
            $existingItemIds[] = $itemId;
            $inserted++;
        }

        if ($inserted === 0) {
            $this->dispatch('toast', type: 'warning', message: 'Tidak ada item baru untuk di-generate.');

            return;
        }

        $this->dispatch('toast', type: 'success', message: "$inserted item berhasil di-generate.");
    }

    public function save()
    {
        if ($this->opnameId) {
            $this->authorize('update', StockOpname::findOrFail($this->opnameId));
        } else {
            $this->authorize('create', StockOpname::class);
        }

        $this->notes = trim($this->notes);

        $data = $this->validate();

        if ($data['location_id'] !== null && $data['location_id'] !== '') {
            $belongs = Location::whereKey($data['location_id'])
                ->whereHas('rack.zone', fn ($query) => $query->where('warehouse_id', $data['warehouse_id']))
                ->exists();

            if (! $belongs) {
                $this->addError('location_id', 'Lokasi tidak termasuk warehouse yang dipilih.');

                return;
            }
        }

        $opname = DB::transaction(function () use ($data): StockOpname {
            if ($this->opnameId) {
                $opname = StockOpname::findOrFail($this->opnameId);
                $old = $opname->toArray();
                $opname->update([
                    'opname_date' => $data['opname_date'],
                    'warehouse_id' => $data['warehouse_id'],
                    'location_id' => $data['location_id'] !== '' ? $data['location_id'] : null,
                    'notes' => $data['notes'] !== '' && $data['notes'] !== null ? $data['notes'] : null,
                ]);
                AuditLogger::logModel('update', $opname, $old, $opname->fresh()->toArray());

                return $opname;
            }

            $opname = StockOpname::create([
                'number' => DocumentNumberService::generate('OPN'),
                'opname_date' => $data['opname_date'],
                'warehouse_id' => $data['warehouse_id'],
                'location_id' => $data['location_id'] !== '' ? $data['location_id'] : null,
                'notes' => $data['notes'] !== '' && $data['notes'] !== null ? $data['notes'] : null,
                'status' => OpnameStatus::Draft->value,
                'created_by' => auth()->id(),
            ]);

            AuditLogger::logModel('create', $opname, null, $opname->toArray());

            return $opname;
        });

        $this->dispatch('toast', type: 'success', message: 'Stock opname tersimpan.');

        return $this->redirect(route('stock-opnames.show', $opname), navigate: true);
    }

    public function render()
    {
        $locations = Location::query()
            ->with('rack.zone.warehouse')
            ->when($this->warehouse_id !== '', fn ($query) => $query->whereHas('rack.zone', fn ($inner) => $inner->where('warehouse_id', $this->warehouse_id)))
            ->orderBy('code')
            ->get();

        $opname = $this->opnameId ? StockOpname::withCount('items')->find($this->opnameId) : null;

        return view('livewire.transactions.stock-opname-form', [
            'warehouses' => Warehouse::orderBy('name')->get(['id', 'name']),
            'locations' => $locations,
            'opname' => $opname,
        ]);
    }
}
