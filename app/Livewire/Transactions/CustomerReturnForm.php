<?php

namespace App\Livewire\Transactions;

use App\Livewire\Concerns\FiltersUnitsByConversion;
use App\Models\Customer;
use App\Models\CustomerReturn;
use App\Models\GoodsIssue;
use App\Models\Item;
use App\Models\Location;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Services\Inventory\ReturnService;
use App\Services\Support\AuditLogger;
use App\Services\Support\DocumentNumberService;
use App\Services\Support\WarehouseAccess;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Form Retur Penjualan')]
class CustomerReturnForm extends Component
{
    use FiltersUnitsByConversion;

    public ?int $returnId = null;

    public string $transaction_date = '';

    public string $customer_id = '';

    public string $goods_issue_id = '';

    public string $warehouse_id = '';

    public string $location_id = '';

    public string $reason = '';

    public string $notes = '';

    public array $items = [];

    public function mount($customerReturn = null): void
    {
        $this->transaction_date = now()->format('Y-m-d');

        $model = $customerReturn instanceof CustomerReturn ? $customerReturn : ($customerReturn ? CustomerReturn::findOrFail($customerReturn) : null);

        if ($model) {
            $this->authorize('update', $model);

            if ($model->status !== 'draft') {
                abort(403, __('Hanya retur draft yang dapat diubah.'));
            }

            $this->returnId = $model->id;
            $this->transaction_date = $model->transaction_date?->format('Y-m-d') ?? now()->format('Y-m-d');
            $this->customer_id = (string) ($model->customer_id ?? '');
            $this->goods_issue_id = (string) ($model->goods_issue_id ?? '');
            $this->warehouse_id = (string) $model->warehouse_id;
            $this->location_id = (string) $model->location_id;
            $this->reason = (string) ($model->reason ?? '');
            $this->notes = (string) ($model->notes ?? '');

            $this->items = $model->items->map(fn ($row) => [
                'item_id' => (string) $row->item_id,
                'quantity' => (int) $row->quantity,
                'unit_id' => (string) $row->unit_id,
                'location_id' => (string) $row->location_id,
                'unit_cost' => (string) $row->unit_cost,
                'batch_number' => (string) ($row->batch_number ?? ''),
                'serial_number' => (string) ($row->serial_number ?? ''),
                'notes' => (string) ($row->notes ?? ''),
            ])->values()->all();
        } else {
            $this->authorize('create', CustomerReturn::class);
            $this->addRow();
        }
    }

    protected function rules(): array
    {
        return [
            'transaction_date' => ['required', 'date'],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'goods_issue_id' => ['nullable', 'exists:goods_issues,id'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'location_id' => ['required', 'exists:locations,id'],
            'reason' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'exists:items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_id' => ['required', 'exists:units,id'],
            'items.*.location_id' => ['required', 'exists:locations,id'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'items.*.batch_number' => ['nullable', 'string', 'max:60'],
            'items.*.serial_number' => ['nullable', 'string', 'max:80'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function addRow(): void
    {
        $this->items[] = [
            'item_id' => '',
            'quantity' => 1,
            'unit_id' => '',
            'location_id' => $this->location_id,
            'unit_cost' => '0',
            'batch_number' => '',
            'serial_number' => '',
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
        $item = Item::find($this->items[$index]['item_id'] ?? null);

        if ($item) {
            $this->items[$index]['unit_id'] = (string) $item->unit_id;
            $this->items[$index]['unit_cost'] = (string) ($item->cost ?? 0);
        }
    }

    public function updatedGoodsIssueId(): void
    {
        if ($this->goods_issue_id === '') {
            return;
        }

        $issue = GoodsIssue::with('issueItems.item')->find($this->goods_issue_id);

        if (! $issue) {
            return;
        }

        $this->customer_id = (string) ($issue->customer_id ?? '');
        $this->warehouse_id = (string) $issue->warehouse_id;

        $remaining = ReturnService::remainingForIssue((int) $issue->id);
        $rows = [];

        foreach ($remaining as $line) {
            if ($line['remaining'] <= 0) {
                continue;
            }

            $rows[] = [
                'item_id' => (string) $line['item_id'],
                'quantity' => $line['remaining'],
                'unit_id' => (string) $line['unit_id'],
                'location_id' => (string) $line['location_id'],
                'unit_cost' => (string) $line['unit_cost'],
                'batch_number' => '',
                'serial_number' => '',
                'notes' => '',
            ];
        }

        if ($rows !== []) {
            $this->items = $rows;
            $this->location_id = (string) $rows[0]['location_id'];
        }
    }

    public function save()
    {
        if ($this->returnId) {
            $existing = CustomerReturn::findOrFail($this->returnId);
            $this->authorize('update', $existing);
        } else {
            $this->authorize('create', CustomerReturn::class);
        }

        $this->notes = trim($this->notes);
        $this->reason = trim($this->reason);

        $data = $this->validate();

        if ($this->goods_issue_id !== '') {
            $remaining = ReturnService::remainingForIssue((int) $this->goods_issue_id);

            foreach ($this->items as $index => $row) {
                if (ReturnService::exceedsRemaining($remaining, (int) $row['item_id'], (int) $row['location_id'], (int) $row['quantity'])) {
                    $this->addError("items.{$index}.quantity", __('Melebihi sisa yang dapat diretur dari dokumen asal.'));

                    return;
                }
            }
        }

        $header = [
            'transaction_date' => $data['transaction_date'],
            'customer_id' => $data['customer_id'] !== '' ? $data['customer_id'] : null,
            'goods_issue_id' => $data['goods_issue_id'] !== '' ? $data['goods_issue_id'] : null,
            'warehouse_id' => $data['warehouse_id'],
            'location_id' => $data['location_id'],
            'reason' => $data['reason'] !== '' ? $data['reason'] : null,
            'notes' => $data['notes'] !== '' && $data['notes'] !== null ? $data['notes'] : null,
        ];

        $rows = array_map(fn ($row) => [
            'item_id' => $row['item_id'],
            'quantity' => (int) $row['quantity'],
            'unit_id' => $row['unit_id'],
            'location_id' => $row['location_id'],
            'unit_cost' => (float) ($row['unit_cost'] ?? 0),
            'batch_number' => ($row['batch_number'] ?? '') !== '' ? $row['batch_number'] : null,
            'serial_number' => ($row['serial_number'] ?? '') !== '' ? $row['serial_number'] : null,
            'notes' => ($row['notes'] ?? '') !== '' ? $row['notes'] : null,
        ], $this->items);

        $return = DB::transaction(function () use ($header, $rows): CustomerReturn {
            if ($this->returnId) {
                $return = CustomerReturn::findOrFail($this->returnId);
                $old = $return->load('items')->toArray();
                $header['updated_by'] = auth()->id();
                $return->update($header);
                $return->items()->delete();
                $return->items()->createMany($rows);
                AuditLogger::logModel('update', $return, $old, $return->fresh('items')->toArray());

                return $return;
            }

            $header['number'] = DocumentNumberService::generate('CRT');
            $header['status'] = 'draft';
            $header['created_by'] = auth()->id();
            $return = CustomerReturn::create($header);
            $return->items()->createMany($rows);
            AuditLogger::logModel('create', $return, null, $return->fresh('items')->toArray());

            return $return;
        });

        $this->dispatch('toast', type: 'success', message: __('Retur penjualan tersimpan.'));

        return $this->redirect(route('customer-returns.show', $return), navigate: true);
    }

    public function render()
    {
        return view('livewire.transactions.customer-return-form', [
            'customers' => Customer::where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'issues' => GoodsIssue::where('status', 'posted')->orderByDesc('id')->limit(100)->get(['id', 'number']),
            'warehouses' => Warehouse::whereIn('id', WarehouseAccess::ids())->orderBy('name')->get(['id', 'name']),
            'locations' => Location::orderBy('code')->get(['id', 'code']),
            'units' => Unit::orderBy('name')->get(['id', 'name', 'code']),
            'itemsList' => Item::where('status', 'active')->orderBy('name')->get(['id', 'sku', 'name']),
            'unitMap' => $this->conversionUnitMap(),
        ]);
    }
}
