<?php

namespace App\Livewire\MasterData;

use App\Enums\CustomerType;
use App\Models\Customer;
use App\Models\CustomerItemPrice;
use App\Models\Item;
use App\Models\SalesOrder;
use App\Services\Support\AuditLogger;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Detail Customer')]
class CustomerShow extends Component
{
    public int $customerId;

    public string $tab = 'prices';

    public string $priceItemId = '';

    public string $priceMinQty = '1';

    public string $pricePrice = '0';

    public string $priceNotes = '';

    public function mount($customer): void
    {
        abort_unless(auth()->user()->hasPermission('items.view'), 403);

        $model = $customer instanceof Customer ? $customer : Customer::findOrFail($customer);

        $this->customerId = $model->id;
    }

    public function setTab(string $tab): void
    {
        if (in_array($tab, ['prices', 'orders'], true)) {
            $this->tab = $tab;
        }
    }

    public function addPrice(): void
    {
        abort_unless(auth()->user()->hasPermission('items.update'), 403);

        $customer = Customer::findOrFail($this->customerId);

        $data = $this->validate([
            'priceItemId' => ['required', 'integer', 'exists:items,id'],
            'priceMinQty' => ['required', 'integer', 'min:1'],
            'pricePrice' => ['required', 'numeric', 'min:0'],
            'priceNotes' => ['nullable', 'string', 'max:255'],
        ]);

        $this->validate([
            'priceItemId' => [
                Rule::unique('customer_item_prices', 'item_id')
                    ->where('customer_id', $customer->id)
                    ->where('min_quantity', (int) $data['priceMinQty']),
            ],
        ], [], ['priceItemId' => 'item']);

        $price = CustomerItemPrice::create([
            'customer_id' => $customer->id,
            'item_id' => $data['priceItemId'],
            'min_quantity' => (int) $data['priceMinQty'],
            'price' => $data['pricePrice'],
            'notes' => $data['priceNotes'] !== '' ? $data['priceNotes'] : null,
        ]);

        AuditLogger::logModel('create', $price, null, $price->toArray());

        $this->reset(['priceItemId', 'priceMinQty', 'pricePrice', 'priceNotes']);
        $this->priceMinQty = '1';
        $this->pricePrice = '0';

        $this->dispatch('toast', type: 'success', message: __('Harga customer tersimpan.'));
    }

    public function deletePrice(int $id): void
    {
        abort_unless(auth()->user()->hasPermission('items.update'), 403);

        $price = CustomerItemPrice::where('customer_id', $this->customerId)->findOrFail($id);

        $old = $price->toArray();
        $price->delete();

        AuditLogger::logModel('delete', $price, $old);

        $this->dispatch('toast', type: 'success', message: __('Harga customer dihapus.'));
    }

    public function render()
    {
        $customer = Customer::findOrFail($this->customerId);

        return view('livewire.master-data.customer-show', [
            'customer' => $customer,
            'typeLabel' => CustomerType::tryFrom((string) $customer->type)?->label() ?? $customer->type,
            'prices' => CustomerItemPrice::where('customer_id', $customer->id)
                ->with('item')
                ->orderBy('item_id')
                ->orderBy('min_quantity')
                ->get(),
            'orders' => SalesOrder::where('customer_id', $customer->id)
                ->with(['warehouse', 'items.item'])
                ->orderByDesc('order_date')
                ->orderByDesc('id')
                ->limit(50)
                ->get(),
            'itemsList' => Item::where('status', 'active')->orderBy('name')->get(['id', 'sku', 'name']),
        ]);
    }
}
