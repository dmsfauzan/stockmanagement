<?php

namespace App\Livewire\Transactions;

use App\Livewire\Concerns\GuardsStaleEdits;
use App\Models\Item;
use App\Models\Location;
use App\Models\StockAdjustment;
use App\Models\StockBalance;
use App\Models\Warehouse;
use App\Services\Support\AuditLogger;
use App\Services\Support\DocumentNumberService;
use App\Services\Support\WarehouseAccess;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
#[Title('Form Stock Adjustment')]
class StockAdjustmentForm extends Component
{
    use GuardsStaleEdits, WithFileUploads;

    public ?int $adjustmentId = null;

    public string $transaction_date = '';

    public string $warehouse_id = '';

    public string $location_id = '';

    public string $reason = '';

    public string $notes = '';

    public $attachment = null;

    public ?string $existingAttachment = null;

    public array $items = [];

    public string $barcodeInput = '';

    public function mount($adjustment = null): void
    {
        $this->transaction_date = now()->format('Y-m-d');

        $model = $adjustment instanceof StockAdjustment ? $adjustment : ($adjustment ? StockAdjustment::findOrFail($adjustment) : null);

        if ($model) {
            $this->authorize('update', $model);

            if ($model->status !== 'draft') {
                abort(403, __('Hanya transaksi draft yang dapat diubah.'));
            }

            $this->adjustmentId = $model->id;
            $this->captureUpdatedAt($model);
            $this->transaction_date = $model->transaction_date?->format('Y-m-d') ?? now()->format('Y-m-d');
            $this->warehouse_id = (string) $model->warehouse_id;
            $this->location_id = (string) $model->location_id;
            $this->reason = (string) $model->reason;
            $this->notes = (string) ($model->notes ?? '');
            $this->existingAttachment = $model->attachment;

            $this->items = $model->items->map(fn ($row) => [
                'item_id' => (string) $row->item_id,
                'system_quantity' => (int) $row->system_quantity,
                'actual_quantity' => (int) $row->actual_quantity,
                'difference' => (int) $row->difference,
                'notes' => (string) ($row->notes ?? ''),
            ])->values()->all();
        } else {
            $this->authorize('create', StockAdjustment::class);
            $this->addRow();
        }
    }

    protected function rules(): array
    {
        return [
            'transaction_date' => ['required', 'date'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'location_id' => ['required', 'exists:locations,id'],
            'reason' => ['required', 'string', 'max:150'],
            'notes' => ['nullable', 'string'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:4096'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'exists:items,id'],
            'items.*.system_quantity' => ['required', 'integer'],
            'items.*.actual_quantity' => ['required', 'integer', 'min:0'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function addRow(): void
    {
        $this->items[] = [
            'item_id' => '',
            'system_quantity' => 0,
            'actual_quantity' => 0,
            'difference' => 0,
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
        $this->refreshSystemQuantity($index);
    }

    public function updatedWarehouseId(): void
    {
        $this->location_id = '';
        foreach (array_keys($this->items) as $i) {
            $this->refreshSystemQuantity($i);
        }
    }

    public function updatedLocationId(): void
    {
        foreach (array_keys($this->items) as $i) {
            $this->refreshSystemQuantity($i);
        }
    }

    public function updatedItems($value, $key): void
    {
        $parts = explode('.', $key);
        if (count($parts) >= 2) {
            $index = (int) $parts[0];
            $field = $parts[1] ?? '';

            if ($field === 'item_id') {
                $this->refreshSystemQuantity($index);
            }

            if (in_array($field, ['actual_quantity', 'system_quantity'], true)) {
                $this->recomputeDifference($index);
            }
        }
    }

    protected function refreshSystemQuantity(int $index): void
    {
        $itemId = $this->items[$index]['item_id'] ?? '';

        if ($itemId === '' || $this->warehouse_id === '' || $this->location_id === '') {
            $this->items[$index]['system_quantity'] = 0;
            $this->recomputeDifference($index);

            return;
        }

        $qty = StockBalance::where('item_id', $itemId)
            ->where('warehouse_id', $this->warehouse_id)
            ->where('location_id', $this->location_id)
            ->value('quantity_on_hand');

        $this->items[$index]['system_quantity'] = (int) ($qty ?? 0);
        $this->recomputeDifference($index);
    }

    protected function recomputeDifference(int $index): void
    {
        $system = (int) ($this->items[$index]['system_quantity'] ?? 0);
        $actual = (int) ($this->items[$index]['actual_quantity'] ?? 0);
        $this->items[$index]['difference'] = $actual - $system;
    }

    public function addByBarcode(): void
    {
        $code = trim($this->barcodeInput);

        if ($code === '') {
            return;
        }

        $item = Item::where('barcode', $code)->orWhere('sku', $code)->first();

        if (! $item) {
            $this->dispatch('toast', type: 'error', message: __('Barang tidak ditemukan: ').$code);
            $this->barcodeInput = '';

            return;
        }

        $systemQty = 0;
        if ($this->warehouse_id !== '' && $this->location_id !== '') {
            $systemQty = (int) (StockBalance::where('item_id', $item->id)
                ->where('warehouse_id', $this->warehouse_id)
                ->where('location_id', $this->location_id)
                ->value('quantity_on_hand') ?? 0);
        }

        $this->items[] = [
            'item_id' => (string) $item->id,
            'system_quantity' => $systemQty,
            'actual_quantity' => $systemQty,
            'difference' => 0,
            'notes' => '',
        ];

        $this->barcodeInput = '';
        $this->dispatch('toast', type: 'success', message: __('Barang ditambahkan: ').$item->name);
    }

    public function save()
    {
        if ($this->adjustmentId) {
            $existing = StockAdjustment::findOrFail($this->adjustmentId);
            $this->authorize('update', $existing);

            if ($this->abortIfStale($existing)) {
                return;
            }
        } else {
            $this->authorize('create', StockAdjustment::class);
        }

        $this->notes = trim($this->notes);
        $this->reason = trim($this->reason);

        foreach ($this->items as $i => $row) {
            $this->items[$i]['notes'] = trim($row['notes'] ?? '');
            $this->recomputeDifference($i);
        }

        $data = $this->validate();

        if ($this->warehouse_id !== '' && $this->location_id !== '') {
            $belongs = Location::whereKey($this->location_id)
                ->whereHas('rack.zone', fn ($query) => $query->where('warehouse_id', $this->warehouse_id))
                ->exists();

            if (! $belongs) {
                $this->addError('location_id', __('Lokasi tidak termasuk warehouse yang dipilih.'));

                return;
            }
        }

        foreach ($this->items as $index => $row) {
            $qty = StockBalance::where('item_id', $row['item_id'])
                ->where('warehouse_id', $this->warehouse_id)
                ->where('location_id', $this->location_id)
                ->value('quantity_on_hand');

            $this->items[$index]['system_quantity'] = (int) ($qty ?? 0);
            $this->items[$index]['difference'] = (int) $row['actual_quantity'] - (int) ($qty ?? 0);
        }

        $attachmentPath = $this->existingAttachment;

        if ($this->attachment) {
            $extension = strtolower($this->attachment->extension() ?: '');
            $extension = in_array($extension, ['pdf', 'jpg', 'jpeg', 'png', 'webp'], true) ? $extension : 'pdf';
            $filename = uniqid('adj_', true).'.'.$extension;
            $attachmentPath = $this->attachment->storeAs('adjustments', $filename, 'public');
        }

        $header = [
            'transaction_date' => $data['transaction_date'],
            'warehouse_id' => $data['warehouse_id'],
            'location_id' => $data['location_id'],
            'reason' => $data['reason'],
            'notes' => $data['notes'] !== '' && $data['notes'] !== null ? $data['notes'] : null,
            'attachment' => $attachmentPath,
        ];

        $rows = array_map(fn ($row) => [
            'item_id' => $row['item_id'],
            'system_quantity' => (int) $row['system_quantity'],
            'actual_quantity' => (int) $row['actual_quantity'],
            'difference' => (int) $row['actual_quantity'] - (int) $row['system_quantity'],
            'notes' => $row['notes'] !== '' ? $row['notes'] : null,
        ], $this->items);

        $adjustment = DB::transaction(function () use ($header, $rows): StockAdjustment {
            if ($this->adjustmentId) {
                $adjustment = StockAdjustment::findOrFail($this->adjustmentId);
                $old = $adjustment->load('items')->toArray();

                $header['updated_by'] = auth()->id();
                $adjustment->update($header);
                $adjustment->items()->delete();
                $adjustment->items()->createMany($rows);

                AuditLogger::logModel('update', $adjustment, $old, $adjustment->fresh('items')->toArray());

                return $adjustment;
            }

            $header['number'] = DocumentNumberService::generate('ADJ');
            $header['status'] = 'draft';
            $header['created_by'] = auth()->id();

            $adjustment = StockAdjustment::create($header);
            $adjustment->items()->createMany($rows);

            AuditLogger::logModel('create', $adjustment, null, $adjustment->fresh('items')->toArray());

            return $adjustment;
        });

        $this->dispatch('toast', type: 'success', message: __('Stock adjustment tersimpan.'));

        return $this->redirect(route('stock-adjustments.show', $adjustment), navigate: true);
    }

    public function render()
    {
        $locations = Location::query()
            ->with('rack.zone.warehouse')
            ->when($this->warehouse_id !== '', fn ($query) => $query->whereHas('rack.zone', fn ($inner) => $inner->where('warehouse_id', $this->warehouse_id)))
            ->orderBy('code')
            ->get();

        return view('livewire.transactions.stock-adjustment-form', [
            'warehouses' => Warehouse::whereIn('id', WarehouseAccess::ids())->orderBy('name')->get(['id', 'name']),
            'locations' => $locations,
            'itemsList' => Item::where('status', 'active')->orderBy('name')->get(['id', 'sku', 'name']),
            'reasons' => ['Stock Count Error', 'Damage', 'Expired', 'Theft / Loss', 'Found', 'Other'],
        ]);
    }
}
