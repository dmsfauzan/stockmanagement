<?php

namespace App\Livewire\Layout;

use App\Models\GoodsIssue;
use App\Models\GoodsReceipt;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\StockAdjustment;
use App\Models\StockTransfer;
use App\Models\Supplier;
use Livewire\Component;

class GlobalSearch extends Component
{
    public string $query = '';

    public bool $open = false;

    public array $results = [];

    public function updatedQuery(): void
    {
        $q = trim($this->query);

        if (mb_strlen($q) < 2) {
            $this->results = [];
            $this->open = false;

            return;
        }

        $this->search();
        $this->open = true;
    }

    public function search(): void
    {
        $q = trim($this->query);

        if (mb_strlen($q) < 2) {
            $this->results = [];
            $this->open = false;

            return;
        }

        $like = '%'.$q.'%';
        $user = auth()->user();
        $grouped = [];

        if ($user && $user->hasPermission('items.view')) {
            $items = Item::with('category')->where(function ($w) use ($like) {
                $w->where('sku', 'like', $like)->orWhere('barcode', 'like', $like)->orWhere('name', 'like', $like);
            })->limit(5)->get();

            if ($items->isNotEmpty()) {
                $grouped[] = [
                    'group' => 'Barang',
                    'items' => $items->map(fn ($it) => [
                        'type' => 'Barang',
                        'title' => $it->sku.' — '.$it->name,
                        'url' => route('items.show', $it->id),
                        'sub' => $it->category?->name ?? '',
                    ])->all(),
                ];
            }

            $suppliers = Supplier::where(function ($w) use ($like) {
                $w->where('name', 'like', $like)->orWhere('code', 'like', $like);
            })->limit(3)->get();

            if ($suppliers->isNotEmpty()) {
                $grouped[] = [
                    'group' => 'Supplier',
                    'items' => $suppliers->map(fn ($s) => [
                        'type' => 'Supplier',
                        'title' => $s->code.' — '.$s->name,
                        'url' => route('suppliers.index'),
                        'sub' => '',
                    ])->all(),
                ];
            }
        }

        if ($user && $user->hasPermission('purchase_order.view')) {
            $pos = PurchaseOrder::where('number', 'like', $like)->limit(3)->get();
            if ($pos->isNotEmpty()) {
                $grouped[] = [
                    'group' => 'Purchase Order',
                    'items' => $pos->map(fn ($po) => [
                        'type' => 'PO',
                        'title' => $po->number,
                        'url' => route('purchase-orders.show', $po->id),
                        'sub' => '',
                    ])->all(),
                ];
            }
        }

        if ($user && $user->hasPermission('goods_receipt.view')) {
            $grs = GoodsReceipt::where('number', 'like', $like)->limit(3)->get();
            if ($grs->isNotEmpty()) {
                $grouped[] = [
                    'group' => 'Barang Masuk',
                    'items' => $grs->map(fn ($gr) => [
                        'type' => 'GR',
                        'title' => $gr->number,
                        'url' => route('goods-receipts.show', $gr->id),
                        'sub' => '',
                    ])->all(),
                ];
            }
        }

        if ($user && $user->hasPermission('goods_issue.view')) {
            $gis = GoodsIssue::where('number', 'like', $like)->limit(3)->get();
            if ($gis->isNotEmpty()) {
                $grouped[] = [
                    'group' => 'Barang Keluar',
                    'items' => $gis->map(fn ($gi) => [
                        'type' => 'GI',
                        'title' => $gi->number,
                        'url' => route('goods-issues.show', $gi->id),
                        'sub' => '',
                    ])->all(),
                ];
            }
        }

        if ($user && $user->hasPermission('transfer.view')) {
            $trs = StockTransfer::where('number', 'like', $like)->limit(3)->get();
            if ($trs->isNotEmpty()) {
                $grouped[] = [
                    'group' => 'Transfer',
                    'items' => $trs->map(fn ($tr) => [
                        'type' => 'TR',
                        'title' => $tr->number,
                        'url' => route('stock-transfers.show', $tr->id),
                        'sub' => '',
                    ])->all(),
                ];
            }
        }

        if ($user && $user->hasPermission('stock.adjustment')) {
            $adjs = StockAdjustment::where('number', 'like', $like)->limit(3)->get();
            if ($adjs->isNotEmpty()) {
                $grouped[] = [
                    'group' => 'Adjustment',
                    'items' => $adjs->map(fn ($adj) => [
                        'type' => 'ADJ',
                        'title' => $adj->number,
                        'url' => route('stock-adjustments.show', $adj->id),
                        'sub' => '',
                    ])->all(),
                ];
            }
        }

        $total = 0;
        $capped = [];
        foreach ($grouped as $g) {
            if ($total >= 15) {
                break;
            }
            $remaining = 15 - $total;
            $items = array_slice($g['items'], 0, $remaining);
            $capped[] = ['group' => $g['group'], 'items' => $items];
            $total += count($items);
        }

        $this->results = $capped;
        $this->open = true;
    }

    public function clear(): void
    {
        $this->query = '';
        $this->results = [];
        $this->open = false;
    }

    public function render()
    {
        return view('livewire.layout.global-search');
    }
}
