<?php

namespace App\Livewire\Transactions;

use App\Enums\TrackingType;
use App\Livewire\Concerns\FiltersUnitsByConversion;
use App\Livewire\Concerns\GuardsStaleEdits;
use App\Models\GoodsReceipt;
use App\Models\Item;
use App\Models\Location;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Services\Support\AuditLogger;
use App\Services\Support\DocumentNumberService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Form Barang Masuk')]
class GoodsReceiptForm extends Component
{
    use FiltersUnitsByConversion, GuardsStaleEdits;

    public ?int $receiptId = null;

    public string $transaction_date = '';

    public string $supplier_id = '';

    public string $po_number = '';

    public string $delivery_note = '';

    public string $warehouse_id = '';

    public string $received_by = '';

    public string $freight_cost = '0';

    public string $other_cost = '0';

    public string $landed_cost_method = 'value';

    public bool $requires_inspection = false;

    public string $notes = '';

    public array $items = [];

    public string $barcodeInput = '';

    public ?int $purchaseOrderLink = null;

    public function mount($receipt = null): void
    {
        $this->transaction_date = now()->format('Y-m-d');

        $model = $receipt instanceof GoodsReceipt ? $receipt : ($receipt ? GoodsReceipt::findOrFail($receipt) : null);

        if ($model) {
            $this->authorize('update', $model);

            if ($model->status !== 'draft') {
                abort(403, __('Hanya transaksi draft yang dapat diubah.'));
            }

            $this->receiptId = $model->id;
            $this->captureUpdatedAt($model);
            $this->transaction_date = $model->transaction_date?->format('Y-m-d') ?? now()->format('Y-m-d');
            $this->supplier_id = (string) $model->supplier_id;
            $this->po_number = (string) ($model->po_number ?? '');
            $this->delivery_note = (string) ($model->delivery_note ?? '');
            $this->warehouse_id = (string) $model->warehouse_id;
            $this->received_by = (string) ($model->received_by ?? '');
            $this->freight_cost = (string) ($model->freight_cost ?? '0');
            $this->other_cost = (string) ($model->other_cost ?? '0');
            $this->landed_cost_method = (string) ($model->landed_cost_method ?? 'value');
            $this->requires_inspection = (bool) ($model->requires_inspection ?? false);
            $this->notes = (string) ($model->notes ?? '');
            $this->purchaseOrderLink = $model->purchase_order_id ? (int) $model->purchase_order_id : null;

            $this->items = $model->receiptItems->map(fn ($item) => [
                'item_id' => (string) $item->item_id,
                'quantity' => (int) $item->quantity,
                'unit_cost' => (float) $item->unit_cost,
                'unit_id' => (string) $item->unit_id,
                'location_id' => (string) $item->location_id,
                'batch_number' => (string) ($item->batch_number ?? ''),
                'serial_number' => (string) ($item->serial_number ?? ''),
                'expiry_date' => $item->expiry_date?->format('Y-m-d') ?? '',
                'notes' => (string) ($item->notes ?? ''),
            ])->values()->all();
        } else {
            $this->authorize('create', GoodsReceipt::class);
            $this->addRow();

            if (! empty(request()->query('scan'))) {
                $this->barcodeInput = (string) request()->query('scan');

                if ($this->barcodeInput !== '') {
                    $this->addByBarcode();
                }
            }

            if (! empty(request()->query('po'))) {
                $this->applyPurchaseOrderPrefill((string) request()->query('po'));
            }
        }
    }

    protected function applyPurchaseOrderPrefill(string $poId): void
    {
        $order = PurchaseOrder::with('items')->find($poId);

        if (! $order || ! in_array($order->status, ['approved', 'partial'], true)) {
            return;
        }

        if (! auth()->user()?->can('view', $order)) {
            return;
        }

        $this->purchaseOrderLink = $order->id;
        $this->supplier_id = (string) $order->supplier_id;
        $this->warehouse_id = (string) $order->warehouse_id;
        $this->po_number = (string) $order->number;

        $rows = $order->items
            ->filter(fn ($item) => ((int) $item->quantity - (int) $item->received_quantity) > 0)
            ->map(fn ($item) => [
                'item_id' => (string) $item->item_id,
                'quantity' => (int) $item->quantity - (int) $item->received_quantity,
                'unit_cost' => (float) $item->unit_price,
                'unit_id' => (string) $item->unit_id,
                'location_id' => '',
                'batch_number' => '',
                'serial_number' => '',
                'expiry_date' => '',
                'notes' => 'Dari '.$order->number,
            ])->values()->all();

        if ($rows !== []) {
            $this->items = $rows;
        }
    }

    protected function rules(): array
    {
        return [
            'transaction_date' => ['required', 'date'],
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'po_number' => ['nullable', 'string', 'max:60'],
            'delivery_note' => ['nullable', 'string', 'max:60'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'received_by' => ['nullable', 'string', 'max:100'],
            'freight_cost' => ['nullable', 'numeric', 'min:0'],
            'other_cost' => ['nullable', 'numeric', 'min:0'],
            'landed_cost_method' => ['required', 'in:value,quantity'],
            'requires_inspection' => ['boolean'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'exists:items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'items.*.unit_id' => ['required', 'exists:units,id'],
            'items.*.location_id' => ['required', 'exists:locations,id'],
            'items.*.batch_number' => ['nullable', 'string', 'max:60'],
            'items.*.serial_number' => ['nullable', 'string', 'max:80'],
            'items.*.expiry_date' => ['nullable', 'date'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function addRow(): void
    {
        $this->items[] = [
            'item_id' => '',
            'quantity' => 1,
            'unit_cost' => 0,
            'unit_id' => '',
            'location_id' => '',
            'batch_number' => '',
            'serial_number' => '',
            'expiry_date' => '',
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

        if ($itemId === '') {
            return;
        }

        $item = Item::find($itemId);

        if ($item) {
            $this->items[$index]['unit_id'] = (string) $item->unit_id;

            if (($this->items[$index]['unit_cost'] ?? 0) <= 0) {
                $this->items[$index]['unit_cost'] = (float) $item->cost;
            }
        }
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

        $this->items[] = [
            'item_id' => (string) $item->id,
            'quantity' => 1,
            'unit_cost' => (float) $item->cost,
            'unit_id' => (string) $item->unit_id,
            'location_id' => '',
            'batch_number' => '',
            'serial_number' => '',
            'expiry_date' => '',
            'notes' => '',
        ];

        $this->barcodeInput = '';
        $this->dispatch('toast', type: 'success', message: __('Barang ditambahkan: ').$item->name);
    }

    public function save()
    {
        if ($this->receiptId) {
            $existing = GoodsReceipt::findOrFail($this->receiptId);
            $this->authorize('update', $existing);

            if ($this->abortIfStale($existing)) {
                return;
            }
        } else {
            $this->authorize('create', GoodsReceipt::class);
        }

        $this->po_number = trim($this->po_number);
        $this->delivery_note = trim($this->delivery_note);
        $this->received_by = trim($this->received_by);
        $this->notes = trim($this->notes);

        $data = $this->validate();

        if ($this->warehouse_id !== '') {
            foreach ($this->items as $index => $row) {
                $belongs = Location::whereKey($row['location_id'])
                    ->whereHas('rack.zone', fn ($query) => $query->where('warehouse_id', $this->warehouse_id))
                    ->exists();

                if (! $belongs) {
                    $this->addError("items.{$index}.location_id", __('Lokasi tidak termasuk warehouse yang dipilih.'));

                    return;
                }
            }
        }

        foreach ($this->items as $index => $row) {
            $item = Item::find($row['item_id']);

            if (! $item) {
                continue;
            }

            if ($item->tracking_type === TrackingType::Batch && trim((string) ($row['batch_number'] ?? '')) === '') {
                $this->addError("items.{$index}.batch_number", __('Batch number wajib diisi untuk barang ini.'));

                return;
            }

            if ($item->tracking_type === TrackingType::Serial) {
                if (trim((string) ($row['serial_number'] ?? '')) === '') {
                    $this->addError("items.{$index}.serial_number", __('Serial number wajib diisi untuk barang ini.'));

                    return;
                }

                if ((int) $row['quantity'] !== 1) {
                    $this->addError("items.{$index}.serial_number", __('Barang serial harus berkuantitas 1.'));

                    return;
                }
            }
        }

        $header = [
            'transaction_date' => $data['transaction_date'],
            'supplier_id' => $data['supplier_id'],
            'po_number' => $data['po_number'] !== '' ? $data['po_number'] : null,
            'delivery_note' => $data['delivery_note'] !== '' ? $data['delivery_note'] : null,
            'freight_cost' => (float) ($data['freight_cost'] ?? 0),
            'other_cost' => (float) ($data['other_cost'] ?? 0),
            'landed_cost_method' => $data['landed_cost_method'] ?? 'value',
            'requires_inspection' => (bool) ($data['requires_inspection'] ?? false),
            'purchase_order_id' => $this->purchaseOrderLink,
            'warehouse_id' => $data['warehouse_id'],
            'received_by' => $data['received_by'] !== '' ? $data['received_by'] : null,
            'notes' => $data['notes'] !== '' ? $data['notes'] : null,
        ];

        $rows = array_map(fn ($row) => [
            'item_id' => $row['item_id'],
            'quantity' => $row['quantity'],
            'unit_cost' => $row['unit_cost'] ?? 0,
            'unit_id' => $row['unit_id'],
            'location_id' => $row['location_id'],
            'batch_number' => $row['batch_number'] !== '' ? $row['batch_number'] : null,
            'serial_number' => $row['serial_number'] !== '' ? $row['serial_number'] : null,
            'expiry_date' => $row['expiry_date'] !== '' ? $row['expiry_date'] : null,
            'notes' => $row['notes'] !== '' ? $row['notes'] : null,
        ], $this->items);

        $receipt = DB::transaction(function () use ($header, $rows): GoodsReceipt {
            if ($this->receiptId) {
                $receipt = GoodsReceipt::findOrFail($this->receiptId);
                $old = $receipt->load('receiptItems')->toArray();

                $header['updated_by'] = auth()->id();
                $receipt->update($header);
                $receipt->receiptItems()->delete();
                $receipt->receiptItems()->createMany($rows);

                AuditLogger::logModel('update', $receipt, $old, $receipt->fresh('receiptItems')->toArray());

                return $receipt;
            }

            $header['number'] = DocumentNumberService::generate('GR');
            $header['status'] = 'draft';
            $header['created_by'] = auth()->id();

            $receipt = GoodsReceipt::create($header);
            $receipt->receiptItems()->createMany($rows);

            AuditLogger::logModel('create', $receipt, null, $receipt->fresh('receiptItems')->toArray());

            return $receipt;
        });

        $this->dispatch('toast', type: 'success', message: __('Barang masuk tersimpan.'));

        return $this->redirect(route('goods-receipts.show', $receipt), navigate: true);
    }

    public function render()
    {
        $locations = Location::query()
            ->with('rack.zone.warehouse')
            ->when($this->warehouse_id !== '', fn ($query) => $query->whereHas('rack.zone', fn ($inner) => $inner->where('warehouse_id', $this->warehouse_id)))
            ->orderBy('code')
            ->get();

        return view('livewire.transactions.goods-receipt-form', [
            'suppliers' => Supplier::where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'warehouses' => Warehouse::orderBy('name')->get(['id', 'name']),
            'units' => Unit::orderBy('name')->get(['id', 'name', 'code']),
            'itemsList' => Item::where('status', 'active')->orderBy('name')->get(['id', 'sku', 'name']),
            'locations' => $locations,
            'unitMap' => $this->conversionUnitMap(),
        ]);
    }
}
