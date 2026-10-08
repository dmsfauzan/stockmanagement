<?php

namespace App\Livewire\Transactions;

use App\Livewire\Concerns\GuardsStaleEdits;
use App\Models\Item;
use App\Models\Location;
use App\Models\StockBalance;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use App\Services\Support\AuditLogger;
use App\Services\Support\DocumentNumberService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Form Transfer Barang')]
class StockTransferForm extends Component
{
    use GuardsStaleEdits;

    public ?int $transferId = null;

    public string $transfer_date = '';

    public string $from_warehouse_id = '';

    public string $from_location_id = '';

    public string $to_warehouse_id = '';

    public string $to_location_id = '';

    public string $notes = '';

    public array $items = [];

    public function mount($transfer = null): void
    {
        $this->transfer_date = now()->format('Y-m-d');

        $model = $transfer instanceof StockTransfer ? $transfer : ($transfer ? StockTransfer::findOrFail($transfer) : null);

        if ($model) {
            $this->authorize('update', $model);

            if ($model->status !== 'draft') {
                abort(403, 'Hanya transaksi draft yang dapat diubah.');
            }

            $this->transferId = $model->id;
            $this->captureUpdatedAt($model);
            $this->transfer_date = $model->transfer_date?->format('Y-m-d') ?? now()->format('Y-m-d');
            $this->from_warehouse_id = (string) $model->from_warehouse_id;
            $this->from_location_id = (string) $model->from_location_id;
            $this->to_warehouse_id = (string) $model->to_warehouse_id;
            $this->to_location_id = (string) $model->to_location_id;
            $this->notes = (string) ($model->notes ?? '');

            $this->items = $model->items->map(fn ($row) => [
                'item_id' => (string) $row->item_id,
                'quantity' => (int) $row->quantity,
                'unit_id' => (string) $row->unit_id,
                'notes' => (string) ($row->notes ?? ''),
            ])->values()->all();

            foreach (array_keys($this->items) as $index) {
                $this->refreshAvailable($index);
            }
        } else {
            $this->authorize('create', StockTransfer::class);
            $this->addRow();
        }
    }

    protected function rules(): array
    {
        return [
            'transfer_date' => ['required', 'date'],
            'from_warehouse_id' => ['required', 'exists:warehouses,id'],
            'from_location_id' => ['required', 'exists:locations,id'],
            'to_warehouse_id' => ['required', 'exists:warehouses,id'],
            'to_location_id' => ['required', 'exists:locations,id'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'exists:items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_id' => ['required', 'exists:units,id'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function addRow(): void
    {
        $this->items[] = [
            'item_id' => '',
            'quantity' => 1,
            'unit_id' => '',
            'notes' => '',
        ];
    }

    public function removeRow(int $index): void
    {
        if (count($this->items) <= 1) {
            return;
        }

        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function selectItem(int $index): void
    {
        $itemId = $this->items[$index]['item_id'] ?? '';

        if ($itemId !== '') {
            $item = Item::find($itemId);
            $this->items[$index]['unit_id'] = $item ? (string) $item->unit_id : '';
        } else {
            $this->items[$index]['unit_id'] = '';
        }

        $this->refreshAvailable($index);
    }

    public function updatedFromWarehouseId(): void
    {
        $this->from_location_id = '';
        $this->refreshAllAvailable();
    }

    public function updatedFromLocationId(): void
    {
        $this->refreshAllAvailable();
    }

    public function updatedToWarehouseId(): void
    {
        $this->to_location_id = '';
    }

    protected function refreshAllAvailable(): void
    {
        foreach (array_keys($this->items) as $index) {
            $this->refreshAvailable($index);
        }
    }

    protected function refreshAvailable(int $index): void
    {
        $itemId = $this->items[$index]['item_id'] ?? '';

        if ($itemId === '' || $this->from_warehouse_id === '' || $this->from_location_id === '') {
            $this->items[$index]['available'] = 0;

            return;
        }

        $qty = StockBalance::where('item_id', $itemId)
            ->where('warehouse_id', $this->from_warehouse_id)
            ->where('location_id', $this->from_location_id)
            ->value('quantity_on_hand');

        $this->items[$index]['available'] = (int) ($qty ?? 0);
    }

    public function save()
    {
        if ($this->transferId) {
            $existing = StockTransfer::findOrFail($this->transferId);
            $this->authorize('update', $existing);

            if ($this->abortIfStale($existing)) {
                return;
            }
        } else {
            $this->authorize('create', StockTransfer::class);
        }

        $this->notes = trim($this->notes);

        foreach ($this->items as $i => $row) {
            $this->items[$i]['notes'] = trim($row['notes'] ?? '');
        }

        $data = $this->validate();

        if ($this->from_warehouse_id === $this->to_warehouse_id && $this->from_location_id === $this->to_location_id) {
            $this->addError('to_location_id', 'Lokasi asal dan tujuan tidak boleh sama.');

            return;
        }

        foreach ([['id' => $this->from_warehouse_id, 'location' => $this->from_location_id, 'field' => 'from_location_id'], ['id' => $this->to_warehouse_id, 'location' => $this->to_location_id, 'field' => 'to_location_id']] as $pair) {
            $belongs = Location::whereKey($pair['location'])
                ->whereHas('rack.zone', fn ($query) => $query->where('warehouse_id', $pair['id']))
                ->exists();

            if (! $belongs) {
                $this->addError($pair['field'], 'Lokasi tidak termasuk warehouse yang dipilih.');

                return;
            }
        }

        $hasStockError = false;

        foreach ($this->items as $index => $row) {
            $available = (int) (StockBalance::where('item_id', $row['item_id'])
                ->where('warehouse_id', $this->from_warehouse_id)
                ->where('location_id', $this->from_location_id)
                ->value('quantity_on_hand') ?? 0);

            $this->items[$index]['available'] = $available;

            if ((int) $row['quantity'] > $available) {
                $this->addError("items.{$index}.quantity", "Stok tersedia hanya {$available}.");
                $hasStockError = true;
            }
        }

        if ($hasStockError) {
            return;
        }

        $header = [
            'transfer_date' => $data['transfer_date'],
            'from_warehouse_id' => $data['from_warehouse_id'],
            'from_location_id' => $data['from_location_id'],
            'to_warehouse_id' => $data['to_warehouse_id'],
            'to_location_id' => $data['to_location_id'],
            'notes' => $data['notes'] !== '' && $data['notes'] !== null ? $data['notes'] : null,
        ];

        $rows = array_map(fn ($row) => [
            'item_id' => $row['item_id'],
            'quantity' => (int) $row['quantity'],
            'unit_id' => $row['unit_id'],
            'notes' => $row['notes'] !== '' ? $row['notes'] : null,
        ], $this->items);

        $transfer = DB::transaction(function () use ($header, $rows): StockTransfer {
            if ($this->transferId) {
                $transfer = StockTransfer::findOrFail($this->transferId);
                $old = $transfer->load('items')->toArray();

                $header['updated_by'] = auth()->id();
                $transfer->update($header);
                $transfer->items()->delete();
                $transfer->items()->createMany($rows);

                AuditLogger::logModel('update', $transfer, $old, $transfer->fresh('items')->toArray());

                return $transfer;
            }

            $header['number'] = DocumentNumberService::generate('TR');
            $header['status'] = 'draft';
            $header['created_by'] = auth()->id();

            $transfer = StockTransfer::create($header);
            $transfer->items()->createMany($rows);

            AuditLogger::logModel('create', $transfer, null, $transfer->fresh('items')->toArray());

            return $transfer;
        });

        $this->dispatch('toast', type: 'success', message: 'Transfer barang tersimpan.');

        return $this->redirect(route('stock-transfers.show', $transfer), navigate: true);
    }

    public function render()
    {
        $fromLocations = Location::query()
            ->with('rack.zone.warehouse')
            ->when($this->from_warehouse_id !== '', fn ($query) => $query->whereHas('rack.zone', fn ($inner) => $inner->where('warehouse_id', $this->from_warehouse_id)))
            ->orderBy('code')
            ->get();

        $toLocations = Location::query()
            ->with('rack.zone.warehouse')
            ->when($this->to_warehouse_id !== '', fn ($query) => $query->whereHas('rack.zone', fn ($inner) => $inner->where('warehouse_id', $this->to_warehouse_id)))
            ->orderBy('code')
            ->get();

        return view('livewire.transactions.stock-transfer-form', [
            'warehouses' => Warehouse::orderBy('name')->get(['id', 'name']),
            'fromLocations' => $fromLocations,
            'toLocations' => $toLocations,
            'itemsList' => Item::where('status', 'active')->orderBy('name')->get(['id', 'sku', 'name', 'unit_id']),
        ]);
    }
}
