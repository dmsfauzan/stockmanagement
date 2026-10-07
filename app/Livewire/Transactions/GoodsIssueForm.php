<?php

namespace App\Livewire\Transactions;

use App\Models\Customer;
use App\Models\GoodsIssue;
use App\Models\Item;
use App\Models\Location;
use App\Models\SalesOrder;
use App\Models\StockBalance;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Services\Support\AuditLogger;
use App\Services\Support\DocumentNumberService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Form Barang Keluar')]
class GoodsIssueForm extends Component
{
    public ?int $issueId = null;

    public string $transaction_date = '';

    public string $customer_id = '';

    public string $destination = '';

    public string $sales_order_number = '';

    public string $warehouse_id = '';

    public string $issued_by = '';

    public string $notes = '';

    public array $items = [];

    public string $barcodeInput = '';

    public ?int $salesOrderLink = null;

    public function mount($issue = null): void
    {
        $this->transaction_date = now()->format('Y-m-d');

        $model = $issue instanceof GoodsIssue ? $issue : ($issue ? GoodsIssue::findOrFail($issue) : null);

        if ($model) {
            $this->authorize('update', $model);

            if ($model->status !== 'draft') {
                abort(403, 'Hanya transaksi draft yang dapat diubah.');
            }

            $this->issueId = $model->id;
            $this->transaction_date = $model->transaction_date?->format('Y-m-d') ?? now()->format('Y-m-d');
            $this->customer_id = $model->customer_id ? (string) $model->customer_id : '';
            $this->destination = (string) $model->destination;
            $this->sales_order_number = (string) ($model->sales_order_number ?? '');
            $this->salesOrderLink = $model->sales_order_id ? (int) $model->sales_order_id : null;
            $this->warehouse_id = (string) $model->warehouse_id;
            $this->issued_by = (string) ($model->issued_by ?? '');
            $this->notes = (string) ($model->notes ?? '');

            $this->items = $model->issueItems->map(fn ($item) => [
                'item_id' => (string) $item->item_id,
                'quantity' => (int) $item->quantity,
                'unit_id' => (string) $item->unit_id,
                'location_id' => (string) $item->location_id,
                'notes' => (string) ($item->notes ?? ''),
            ])->values()->all();
        } else {
            $this->authorize('create', GoodsIssue::class);
            $this->addRow();

            if (! empty(request()->query('scan'))) {
                $this->barcodeInput = (string) request()->query('scan');

                if ($this->barcodeInput !== '') {
                    $this->addByBarcode();
                }
            }

            if (! empty(request()->query('so'))) {
                $this->applySalesOrderPrefill((string) request()->query('so'));
            }
        }
    }

    protected function applySalesOrderPrefill(string $soId): void
    {
        $order = SalesOrder::with('items')->find($soId);

        if (! $order || ! in_array($order->status, ['approved', 'partial'], true)) {
            return;
        }

        if (! auth()->user()?->can('view', $order)) {
            return;
        }

        $this->salesOrderLink = $order->id;
        $this->customer_id = $order->customer_id ? (string) $order->customer_id : '';
        $this->warehouse_id = (string) $order->warehouse_id;
        $this->sales_order_number = (string) $order->number;

        $rows = $order->items
            ->filter(fn ($item) => ((int) $item->quantity - (int) $item->fulfilled_quantity) > 0)
            ->map(fn ($item) => [
                'item_id' => (string) $item->item_id,
                'quantity' => (int) $item->quantity - (int) $item->fulfilled_quantity,
                'unit_id' => (string) $item->unit_id,
                'location_id' => '',
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
            'customer_id' => ['nullable', 'exists:customers,id'],
            'destination' => ['required', 'string', 'max:150'],
            'sales_order_number' => ['nullable', 'string', 'max:60'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'issued_by' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'exists:items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_id' => ['required', 'exists:units,id'],
            'items.*.location_id' => ['required', 'exists:locations,id'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function addRow(): void
    {
        $this->items[] = [
            'item_id' => '',
            'quantity' => 1,
            'unit_id' => '',
            'location_id' => '',
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
            $this->dispatch('toast', type: 'error', message: 'Barang tidak ditemukan: '.$code);
            $this->barcodeInput = '';

            return;
        }

        $this->items[] = [
            'item_id' => (string) $item->id,
            'quantity' => 1,
            'unit_id' => (string) $item->unit_id,
            'location_id' => '',
            'notes' => '',
        ];

        $this->barcodeInput = '';
        $this->dispatch('toast', type: 'success', message: 'Barang ditambahkan: '.$item->name);
    }

    public function save()
    {
        if ($this->issueId) {
            $this->authorize('update', GoodsIssue::findOrFail($this->issueId));
        } else {
            $this->authorize('create', GoodsIssue::class);
        }

        $this->destination = trim($this->destination);
        $this->sales_order_number = trim($this->sales_order_number);
        $this->issued_by = trim($this->issued_by);
        $this->notes = trim($this->notes);

        $data = $this->validate();

        if ($this->warehouse_id !== '') {
            foreach ($this->items as $index => $row) {
                $belongs = Location::whereKey($row['location_id'])
                    ->whereHas('rack.zone', fn ($query) => $query->where('warehouse_id', $this->warehouse_id))
                    ->exists();

                if (! $belongs) {
                    $this->addError("items.{$index}.location_id", 'Lokasi tidak termasuk warehouse yang dipilih.');

                    return;
                }
            }
        }

        $header = [
            'transaction_date' => $data['transaction_date'],
            'customer_id' => $data['customer_id'] !== '' ? $data['customer_id'] : null,
            'destination' => $data['destination'],
            'sales_order_number' => $data['sales_order_number'] !== '' ? $data['sales_order_number'] : null,
            'sales_order_id' => $this->salesOrderLink,
            'warehouse_id' => $data['warehouse_id'],
            'issued_by' => $data['issued_by'] !== '' ? $data['issued_by'] : null,
            'notes' => $data['notes'] !== '' ? $data['notes'] : null,
        ];

        $rows = array_map(fn ($row) => [
            'item_id' => $row['item_id'],
            'quantity' => $row['quantity'],
            'unit_id' => $row['unit_id'],
            'location_id' => $row['location_id'],
            'notes' => $row['notes'] !== '' ? $row['notes'] : null,
        ], $this->items);

        $issue = DB::transaction(function () use ($header, $rows): GoodsIssue {
            if ($this->issueId) {
                $issue = GoodsIssue::findOrFail($this->issueId);
                $old = $issue->load('issueItems')->toArray();

                $header['updated_by'] = auth()->id();
                $issue->update($header);
                $issue->issueItems()->delete();
                $issue->issueItems()->createMany($rows);

                AuditLogger::logModel('update', $issue, $old, $issue->fresh('issueItems')->toArray());

                return $issue;
            }

            $header['number'] = DocumentNumberService::generate('GI');
            $header['status'] = 'draft';
            $header['created_by'] = auth()->id();

            $issue = GoodsIssue::create($header);
            $issue->issueItems()->createMany($rows);

            AuditLogger::logModel('create', $issue, null, $issue->fresh('issueItems')->toArray());

            return $issue;
        });

        $this->dispatch('toast', type: 'success', message: 'Barang keluar tersimpan.');

        return $this->redirect(route('goods-issues.show', $issue), navigate: true);
    }

    public function render()
    {
        $locations = Location::query()
            ->with('rack.zone.warehouse')
            ->when($this->warehouse_id !== '', fn ($query) => $query->whereHas('rack.zone', fn ($inner) => $inner->where('warehouse_id', $this->warehouse_id)))
            ->orderBy('code')
            ->get();

        $itemIds = collect($this->items)->pluck('item_id')->filter()->unique()->all();

        $stockMap = [];
        if ($this->warehouse_id !== '' && ! empty($itemIds)) {
            $balances = StockBalance::query()
                ->where('warehouse_id', $this->warehouse_id)
                ->whereIn('item_id', $itemIds)
                ->get(['item_id', 'location_id', 'quantity_on_hand', 'quantity_reserved']);

            foreach ($balances as $balance) {
                $stockMap[(int) $balance->item_id][(int) $balance->location_id] =
                    (int) $balance->quantity_on_hand - (int) $balance->quantity_reserved;
            }
        }

        return view('livewire.transactions.goods-issue-form', [
            'customers' => Customer::where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'warehouses' => Warehouse::orderBy('name')->get(['id', 'name']),
            'units' => Unit::orderBy('name')->get(['id', 'name', 'code']),
            'itemsList' => Item::where('status', 'active')->orderBy('name')->get(['id', 'sku', 'name']),
            'locations' => $locations,
            'stockMap' => $stockMap,
        ]);
    }
}
