<?php

namespace App\Livewire\Transactions;

use App\Models\Item;
use App\Models\PurchaseRequisition;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Services\Support\AuditLogger;
use App\Services\Support\DocumentNumberService;
use App\Services\Support\WarehouseAccess;
use App\Services\Workflow\RequisitionWorkflow;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Form Requisition')]
class PurchaseRequisitionForm extends Component
{
    public ?int $requisitionId = null;

    public string $request_date = '';

    public string $required_date = '';

    public string $warehouse_id = '';

    public string $requester_id = '';

    public string $notes = '';

    public array $items = [];

    public function mount($purchaseRequisition = null): void
    {
        abort_unless(auth()->user()->hasPermission('requisition.create'), 403);

        $this->request_date = now()->format('Y-m-d');
        $this->requester_id = (string) auth()->id();

        $model = $purchaseRequisition instanceof PurchaseRequisition ? $purchaseRequisition : ($purchaseRequisition ? PurchaseRequisition::findOrFail($purchaseRequisition) : null);

        if ($model) {
            abort_unless($model->isEditable(), 403, __('Requisition ini tidak dapat diubah.'));

            $this->requisitionId = $model->id;
            $this->request_date = $model->request_date?->format('Y-m-d') ?? now()->format('Y-m-d');
            $this->required_date = $model->required_date?->format('Y-m-d') ?? '';
            $this->warehouse_id = (string) ($model->warehouse_id ?? '');
            $this->requester_id = (string) ($model->requester_id ?? '');
            $this->notes = (string) ($model->notes ?? '');

            $this->items = $model->items->map(fn ($row) => [
                'item_id' => (string) $row->item_id,
                'quantity' => (int) $row->quantity,
                'unit_id' => (string) $row->unit_id,
                'estimated_price' => (string) $row->estimated_price,
                'notes' => (string) ($row->notes ?? ''),
            ])->values()->all();
        } else {
            $this->addRow();
        }
    }

    protected function rules(): array
    {
        return [
            'request_date' => ['required', 'date'],
            'required_date' => ['nullable', 'date', 'after_or_equal:request_date'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'requester_id' => ['nullable', 'exists:users,id'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'exists:items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_id' => ['required', 'exists:units,id'],
            'items.*.estimated_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function addRow(): void
    {
        $this->items[] = [
            'item_id' => '',
            'quantity' => 1,
            'unit_id' => '',
            'estimated_price' => '0',
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

            if ((float) ($this->items[$index]['estimated_price'] ?? 0) <= 0) {
                $this->items[$index]['estimated_price'] = (string) $item->cost;
            }
        }
    }

    public function save(bool $submit = false)
    {
        $data = $this->validate();

        abort_unless(auth()->user()?->canAccessWarehouse((int) $data['warehouse_id']), 403);

        $header = [
            'request_date' => $data['request_date'],
            'required_date' => $data['required_date'] !== '' ? $data['required_date'] : null,
            'warehouse_id' => $data['warehouse_id'],
            'requester_id' => $data['requester_id'] !== '' ? $data['requester_id'] : auth()->id(),
            'notes' => $data['notes'] !== '' ? $data['notes'] : null,
        ];

        $rows = array_map(fn ($row) => [
            'item_id' => $row['item_id'],
            'quantity' => (int) $row['quantity'],
            'unit_id' => $row['unit_id'],
            'estimated_price' => (float) ($row['estimated_price'] ?? 0),
            'notes' => ($row['notes'] ?? '') !== '' ? $row['notes'] : null,
        ], $this->items);

        $req = DB::transaction(function () use ($header, $rows): PurchaseRequisition {
            if ($this->requisitionId) {
                $req = PurchaseRequisition::findOrFail($this->requisitionId);
                $old = $req->load('items')->toArray();
                $req->update($header);
                $req->items()->delete();
                $req->items()->createMany($rows);
                AuditLogger::logModel('update', $req, $old, $req->fresh('items')->toArray());

                return $req;
            }

            $header['number'] = DocumentNumberService::generate('PR');
            $header['status'] = 'draft';
            $header['created_by'] = auth()->id();
            $req = PurchaseRequisition::create($header);
            $req->items()->createMany($rows);
            AuditLogger::logModel('create', $req, null, $req->fresh('items')->toArray());

            return $req;
        });

        if ($submit) {
            RequisitionWorkflow::submit($req->id);
        }

        $this->dispatch('toast', type: 'success', message: __('Requisition tersimpan.'));

        return $this->redirect(route('requisitions.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.transactions.purchase-requisition-form', [
            'itemsList' => Item::where('status', 'active')->orderBy('name')->get(['id', 'sku', 'name']),
            'units' => Unit::orderBy('name')->get(['id', 'name', 'code']),
            'warehouses' => Warehouse::whereIn('id', WarehouseAccess::ids())->orderBy('name')->get(['id', 'name']),
            'suppliers' => Supplier::where('status', 'active')->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
