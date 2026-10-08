<?php

namespace App\Livewire\Transactions;

use App\Enums\OpnameStatus;
use App\Livewire\Concerns\GuardsStaleEdits;
use App\Models\Location;
use App\Models\Rack;
use App\Models\StockBalance;
use App\Models\StockOpname;
use App\Models\Warehouse;
use App\Models\Zone;
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
    use GuardsStaleEdits;

    public ?int $opnameId = null;

    public string $opname_date = '';

    public string $warehouse_id = '';

    public string $location_id = '';

    public string $type = 'full';

    public string $zone_id = '';

    public string $rack_id = '';

    public string $notes = '';

    public function mount($opname = null): void
    {
        $this->opname_date = now()->format('Y-m-d');

        $model = $opname instanceof StockOpname ? $opname : ($opname ? StockOpname::findOrFail($opname) : null);

        if ($model) {
            $this->authorize('update', $model);

            if ($model->status !== OpnameStatus::Draft->value) {
                abort(403, __('Hanya opname draft yang dapat diubah.'));
            }

            $this->opnameId = $model->id;
            $this->captureUpdatedAt($model);
            $this->opname_date = $model->opname_date?->format('Y-m-d') ?? now()->format('Y-m-d');
            $this->warehouse_id = (string) $model->warehouse_id;
            $this->location_id = (string) ($model->location_id ?? '');
            $this->type = (string) ($model->type ?? 'full');
            $this->zone_id = $model->zone_id ? (string) $model->zone_id : '';
            $this->rack_id = $model->rack_id ? (string) $model->rack_id : '';
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
            'type' => ['required', 'in:full,cycle'],
            'zone_id' => ['nullable', 'exists:zones,id'],
            'rack_id' => ['nullable', 'exists:racks,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function generateItems(): void
    {
        if (! $this->opnameId) {
            $this->dispatch('toast', type: 'error', message: __('Simpan opname terlebih dahulu.'));

            return;
        }

        $opname = StockOpname::findOrFail($this->opnameId);

        if ($opname->status !== OpnameStatus::Draft->value) {
            $this->dispatch('toast', type: 'error', message: __('Hanya draft yang dapat generate items.'));

            return;
        }

        $existingItemIds = $opname->items()->pluck('item_id')->all();

        $scopeLocationIds = null;

        if (! $opname->location_id && $opname->type === 'cycle') {
            if ($opname->rack_id) {
                $scopeLocationIds = Location::where('rack_id', $opname->rack_id)->pluck('id')->all();
            } elseif ($opname->zone_id) {
                $scopeLocationIds = Location::whereHas('rack', fn ($q) => $q->where('zone_id', $opname->zone_id))->pluck('id')->all();
            }
        }

        $totals = StockBalance::where('warehouse_id', $opname->warehouse_id)
            ->when($opname->location_id, fn ($q) => $q->where('location_id', $opname->location_id))
            ->when($scopeLocationIds !== null, fn ($q) => $q->whereIn('location_id', $scopeLocationIds))
            ->get(['item_id', 'quantity_on_hand'])
            ->groupBy('item_id')
            ->map(fn ($rows) => (int) $rows->sum('quantity_on_hand'));

        if ($totals->isEmpty()) {
            $this->dispatch('toast', type: 'warning', message: __('Tidak ada saldo stok untuk di-generate.'));

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
            $this->dispatch('toast', type: 'warning', message: __('Tidak ada item baru untuk di-generate.'));

            return;
        }

        $this->dispatch('toast', type: 'success', message: __(':inserted item berhasil di-generate.', ['inserted' => $inserted]));
    }

    public function save()
    {
        if ($this->opnameId) {
            $existing = StockOpname::findOrFail($this->opnameId);
            $this->authorize('update', $existing);

            if ($this->abortIfStale($existing)) {
                return;
            }
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
                $this->addError('location_id', __('Lokasi tidak termasuk warehouse yang dipilih.'));

                return;
            }
        }

        $isCycle = $data['type'] === 'cycle';

        $scope = [
            'type' => $data['type'],
            'zone_id' => $isCycle && $data['zone_id'] !== '' && $data['zone_id'] !== null ? $data['zone_id'] : null,
            'rack_id' => $isCycle && $data['rack_id'] !== '' && $data['rack_id'] !== null ? $data['rack_id'] : null,
        ];

        $opname = DB::transaction(function () use ($data, $scope): StockOpname {
            if ($this->opnameId) {
                $opname = StockOpname::findOrFail($this->opnameId);
                $old = $opname->toArray();
                $opname->update([
                    'opname_date' => $data['opname_date'],
                    'warehouse_id' => $data['warehouse_id'],
                    'location_id' => $data['location_id'] !== '' ? $data['location_id'] : null,
                    'type' => $scope['type'],
                    'zone_id' => $scope['zone_id'],
                    'rack_id' => $scope['rack_id'],
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
                'type' => $scope['type'],
                'zone_id' => $scope['zone_id'],
                'rack_id' => $scope['rack_id'],
                'notes' => $data['notes'] !== '' && $data['notes'] !== null ? $data['notes'] : null,
                'status' => OpnameStatus::Draft->value,
                'created_by' => auth()->id(),
            ]);

            AuditLogger::logModel('create', $opname, null, $opname->toArray());

            return $opname;
        });

        $this->dispatch('toast', type: 'success', message: __('Stock opname tersimpan.'));

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

        $zones = Zone::query()
            ->with('warehouse:id,name')
            ->when($this->warehouse_id !== '', fn ($query) => $query->where('warehouse_id', $this->warehouse_id))
            ->orderBy('name')
            ->get(['id', 'name', 'warehouse_id']);

        $racks = Rack::query()
            ->with('zone:id,name')
            ->when($this->warehouse_id !== '', fn ($query) => $query->whereHas('zone', fn ($inner) => $inner->where('warehouse_id', $this->warehouse_id)))
            ->when($this->zone_id !== '', fn ($query) => $query->where('zone_id', $this->zone_id))
            ->orderBy('name')
            ->get(['id', 'name', 'zone_id']);

        return view('livewire.transactions.stock-opname-form', [
            'warehouses' => Warehouse::orderBy('name')->get(['id', 'name']),
            'locations' => $locations,
            'zones' => $zones,
            'racks' => $racks,
            'opname' => $opname,
        ]);
    }
}
