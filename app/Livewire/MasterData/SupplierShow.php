<?php

namespace App\Livewire\MasterData;

use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierItemPrice;
use App\Services\Support\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Detail Supplier')]
class SupplierShow extends Component
{
    public int $supplierId;

    public string $tab = 'prices';

    public string $priceItemId = '';

    public string $pricePrice = '0';

    public string $priceLeadTime = '7';

    public string $priceNotes = '';

    public function mount($supplier): void
    {
        abort_unless(auth()->user()->hasPermission('items.view'), 403);

        $model = $supplier instanceof Supplier ? $supplier : Supplier::findOrFail($supplier);

        $this->supplierId = $model->id;
    }

    public function setTab(string $tab): void
    {
        if (in_array($tab, ['prices', 'orders', 'performance'], true)) {
            $this->tab = $tab;
        }
    }

    public function addPrice(): void
    {
        abort_unless(auth()->user()->hasPermission('items.update'), 403);

        $supplier = Supplier::findOrFail($this->supplierId);

        $data = $this->validate([
            'priceItemId' => [
                'required', 'integer', 'exists:items,id',
                Rule::unique('supplier_item_prices', 'item_id')->where('supplier_id', $supplier->id),
            ],
            'pricePrice' => ['required', 'numeric', 'min:0'],
            'priceLeadTime' => ['required', 'integer', 'min:1', 'max:365'],
            'priceNotes' => ['nullable', 'string', 'max:255'],
        ], [], [
            'priceItemId' => 'item',
            'pricePrice' => 'price',
            'priceLeadTime' => 'lead time',
            'priceNotes' => 'notes',
        ]);

        $price = SupplierItemPrice::create([
            'supplier_id' => $supplier->id,
            'item_id' => $data['priceItemId'],
            'price' => $data['pricePrice'],
            'lead_time_days' => $data['priceLeadTime'],
            'notes' => $data['priceNotes'] !== '' ? $data['priceNotes'] : null,
        ]);

        AuditLogger::logModel('create', $price, null, $price->toArray());

        $this->reset(['priceItemId', 'pricePrice', 'priceLeadTime', 'priceNotes']);
        $this->pricePrice = '0';
        $this->priceLeadTime = '7';

        $this->dispatch('toast', type: 'success', message: 'Harga supplier tersimpan.');
    }

    public function deletePrice(int $id): void
    {
        abort_unless(auth()->user()->hasPermission('items.update'), 403);

        $price = SupplierItemPrice::where('supplier_id', $this->supplierId)->findOrFail($id);

        $old = $price->toArray();
        $price->delete();

        AuditLogger::logModel('delete', $price, $old);

        $this->dispatch('toast', type: 'success', message: 'Harga supplier dihapus.');
    }

    public function render()
    {
        $supplier = Supplier::withCount('primaryItems')->findOrFail($this->supplierId);

        $prices = SupplierItemPrice::where('supplier_id', $supplier->id)
            ->with('item')
            ->orderByDesc('id')
            ->get();

        $orders = PurchaseOrder::where('supplier_id', $supplier->id)
            ->with(['warehouse', 'items.item', 'goodsReceipts'])
            ->orderByDesc('order_date')
            ->orderByDesc('id')
            ->get();

        return view('livewire.master-data.supplier-show', [
            'supplier' => $supplier,
            'prices' => $prices,
            'orders' => $orders,
            'itemsList' => Item::where('status', 'active')->orderBy('name')->get(['id', 'sku', 'name']),
            'performance' => $this->computePerformance($supplier, $orders),
        ]);
    }

    private function computePerformance(Supplier $supplier, $orders): array
    {
        $total = $orders->count();

        $postedLike = ['approved', 'partial', 'received', 'closed'];
        $approvedCount = $orders->whereIn('status', $postedLike)->count();

        $receivedCount = $orders->filter(fn ($po) => $po->goodsReceipts->whereNotNull('posted_at')->isNotEmpty())->count();

        $onTime = 0;
        $leadTimes = [];

        foreach ($orders as $po) {
            $postedDates = $po->goodsReceipts
                ->whereNotNull('posted_at')
                ->pluck('posted_at')
                ->sort();

            $lastPosted = $postedDates->last();

            if (! $lastPosted) {
                $onTime++;

                continue;
            }

            if ($po->expected_date && $lastPosted->toDateString() <= $po->expected_date->toDateString()) {
                $onTime++;
            }

            if ($po->order_date) {
                $leadTimes[] = Carbon::parse($po->order_date)->startOfDay()->diffInDays(
                    Carbon::parse($lastPosted)->startOfDay(),
                    false
                );
            }
        }

        $onTimeRate = $total > 0 ? round(($onTime / $total) * 100, 1) : 0.0;

        $avgLeadTime = $leadTimes !== [] ? round(array_sum($leadTimes) / count($leadTimes), 1) : null;

        $variances = [];
        $poItems = $orders->flatMap(fn ($po) => $po->items)->whereNotNull('item_id');

        $catalog = SupplierItemPrice::where('supplier_id', $supplier->id)
            ->get()
            ->keyBy('item_id');

        foreach ($poItems as $row) {
            $catalogPrice = $catalog->get($row->item_id);

            if (! $catalogPrice || (float) $catalogPrice->price <= 0) {
                continue;
            }

            $variances[] = ((float) $row->unit_price - (float) $catalogPrice->price) / (float) $catalogPrice->price;
        }

        $priceVariance = $variances !== [] ? round((array_sum($variances) / count($variances)) * 100, 1) : null;

        if ($total === 0) {
            $score = 0.0;
        } elseif ($priceVariance === null) {
            $score = $onTimeRate;
        } else {
            $priceAccuracy = max(0.0, 100 - min(100, abs($priceVariance)));
            $score = round(($onTimeRate * 0.7) + ($priceAccuracy * 0.3), 1);
        }

        return [
            'total_pos' => $total,
            'approved_pos' => $approvedCount,
            'received_pos' => $receivedCount,
            'on_time_rate' => $onTimeRate,
            'average_lead_time_actual' => $avgLeadTime,
            'price_variance' => $priceVariance,
            'score' => $score,
        ];
    }
}
