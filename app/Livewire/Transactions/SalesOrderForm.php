<?php

namespace App\Livewire\Transactions;

use App\Models\Customer;
use App\Models\Item;
use App\Models\SalesOrder;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Services\Support\AuditLogger;
use App\Services\Support\DocumentNumberService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Form Sales Order')]
class SalesOrderForm extends Component
{
    public ?int $salesOrderId = null;

    public string $order_date = '';

    public string $expected_date = '';

    public string $customer_id = '';

    public string $warehouse_id = '';

    public string $notes = '';

    public array $items = [];

    public function mount($salesOrder = null): void
    {
        $this->order_date = now()->format('Y-m-d');

        $model = $salesOrder instanceof SalesOrder ? $salesOrder : ($salesOrder ? SalesOrder::findOrFail($salesOrder) : null);

        if ($model) {
            $this->authorize('update', $model);

            if ($model->status !== 'draft') {
                abort(403, 'Hanya SO draft yang dapat diubah.');
            }

            $this->salesOrderId = $model->id;
            $this->order_date = $model->order_date?->format('Y-m-d') ?? now()->format('Y-m-d');
            $this->expected_date = $model->expected_date?->format('Y-m-d') ?? '';
            $this->customer_id = (string) $model->customer_id;
            $this->warehouse_id = (string) $model->warehouse_id;
            $this->notes = (string) ($model->notes ?? '');

            $this->items = $model->items->map(fn ($row) => [
                'item_id' => (string) $row->item_id,
                'quantity' => (int) $row->quantity,
                'fulfilled_quantity' => (int) $row->fulfilled_quantity,
                'unit_id' => (string) $row->unit_id,
                'unit_price' => (string) $row->unit_price,
                'notes' => (string) ($row->notes ?? ''),
            ])->values()->all();
        } else {
            $this->authorize('create', SalesOrder::class);
            $this->addRow();
        }
    }

    protected function rules(): array
    {
        return [
            'order_date' => ['required', 'date'],
            'expected_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'customer_id' => ['required', 'exists:customers,id'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'exists:items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_id' => ['required', 'exists:units,id'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function addRow(): void
    {
        $this->items[] = [
            'item_id' => '',
            'quantity' => 1,
            'fulfilled_quantity' => 0,
            'unit_id' => '',
            'unit_price' => '0',
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

            if (($this->items[$index]['unit_price'] ?? 0) <= 0) {
                $this->items[$index]['unit_price'] = (string) ($item->cost ?? 0);
            }
        }
    }

    public function save()
    {
        if ($this->salesOrderId) {
            $this->authorize('update', SalesOrder::findOrFail($this->salesOrderId));
        } else {
            $this->authorize('create', SalesOrder::class);
        }

        $this->notes = trim($this->notes);
        $this->expected_date = trim($this->expected_date);

        $data = $this->validate();

        $header = [
            'order_date' => $data['order_date'],
            'expected_date' => $data['expected_date'] !== '' && $data['expected_date'] !== null ? $data['expected_date'] : null,
            'customer_id' => $data['customer_id'],
            'warehouse_id' => $data['warehouse_id'],
            'notes' => $data['notes'] !== '' && $data['notes'] !== null ? $data['notes'] : null,
        ];

        $rows = array_map(fn ($row) => [
            'item_id' => $row['item_id'],
            'quantity' => (int) $row['quantity'],
            'unit_id' => $row['unit_id'],
            'unit_price' => $row['unit_price'],
            'notes' => ($row['notes'] ?? '') !== '' ? $row['notes'] : null,
        ], $this->items);

        $order = DB::transaction(function () use ($header, $rows): SalesOrder {
            if ($this->salesOrderId) {
                $order = SalesOrder::findOrFail($this->salesOrderId);
                $old = $order->load('items')->toArray();

                $header['updated_by'] = auth()->id();
                $order->update($header);
                $order->items()->delete();

                foreach ($rows as $row) {
                    $order->items()->create($row + ['fulfilled_quantity' => 0]);
                }

                AuditLogger::logModel('update', $order, $old, $order->fresh('items')->toArray());

                return $order;
            }

            $header['number'] = DocumentNumberService::generate('SO');
            $header['status'] = 'draft';
            $header['created_by'] = auth()->id();

            $order = SalesOrder::create($header);

            foreach ($rows as $row) {
                $order->items()->create($row + ['fulfilled_quantity' => 0]);
            }

            AuditLogger::logModel('create', $order, null, $order->fresh('items')->toArray());

            return $order;
        });

        $this->dispatch('toast', type: 'success', message: 'Sales order tersimpan.');

        return $this->redirect(route('sales-orders.show', $order), navigate: true);
    }

    public function render()
    {
        return view('livewire.transactions.sales-order-form', [
            'customers' => Customer::where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'warehouses' => Warehouse::orderBy('name')->get(['id', 'name']),
            'units' => Unit::orderBy('name')->get(['id', 'name', 'code']),
            'itemsList' => Item::where('status', 'active')->orderBy('name')->get(['id', 'sku', 'name']),
        ]);
    }
}
