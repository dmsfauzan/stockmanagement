<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Item;
use App\Models\Location;
use App\Models\Setting;
use App\Models\StockBalance;
use App\Models\Warehouse;
use App\Services\Barcode\LabelService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class LabelController extends Controller
{
    public static function labelOptions(): array
    {
        return [
            'size' => (string) (Setting::get('label.default_size', '85x54') ?? '85x54'),
            'show_brand' => (string) (Setting::get('label.show_brand', '1') ?? '1') === '1',
            'show_price' => (string) (Setting::get('label.show_price', '0') ?? '0') === '1',
            'company_text' => (string) (Setting::get('label.company_text', '') ?? ''),
        ];
    }

    public function item(Item $item, Request $request, LabelService $service): Response
    {
        Gate::authorize('view', $item);

        $format = $request->query('format', 'both');
        $size = $this->sizeParam($request->query('size') ?? self::labelOptions()['size']);

        [$barcodeValue, $qrData, $qrSvg, $barcodeSvg] = $this->buildForItem($item, $service, $format, $size);

        return response()->view('labels.item', [
            'item' => $item,
            'format' => $format,
            'size' => $size,
            'barcodeValue' => $barcodeValue,
            'qrData' => $qrData,
            'qrSvg' => $qrSvg,
            'barcodeSvg' => $barcodeSvg,
        ])->header('Content-Type', 'text/html');
    }

    public function location(Location $location, Request $request, LabelService $service): Response
    {
        Gate::authorize('view', $location);

        $qrData = 'LOC:'.($location->code ?? $location->id);
        $qrSvg = $service->qrSvg($qrData, 200);
        $format = $request->query('format', 'qr');

        return response()->view('labels.location', [
            'location' => $location,
            'format' => $format,
            'qrData' => $qrData,
            'qrSvg' => $qrSvg,
        ])->header('Content-Type', 'text/html');
    }

    public function bulk(Request $request, LabelService $service): Response
    {
        Gate::authorize('viewAny', Item::class);

        $ids = array_values(array_filter(array_map('intval', (array) $request->query('ids', []))));
        $format = $request->query('format', 'qr');
        $size = $this->sizeParam($request->query('size'));

        if (count($ids) > 0) {
            $items = Item::whereKey($ids)->where('status', 'active')->orderBy('name')->get(['id', 'sku', 'barcode', 'name', 'brand', 'category_id']);
        } else {
            $search = trim((string) $request->query('search', ''));
            $categoryId = (int) ($request->query('category_id') ?? 0);
            $warehouseId = (int) ($request->query('warehouse_id') ?? 0);

            $itemIds = [];

            if ($warehouseId > 0) {
                $itemIds = StockBalance::query()
                    ->where('warehouse_id', $warehouseId)
                    ->pluck('item_id')
                    ->all();
            }

            $items = Item::query()
                ->when($categoryId > 0, fn ($query) => $query->where('category_id', $categoryId))
                ->when($search !== '', function ($query) use ($search): void {
                    $term = '%'.$search.'%';
                    $query->where(fn ($inner) => $inner->where('sku', 'like', $term)->orWhere('barcode', 'like', $term)->orWhere('name', 'like', $term));
                })
                ->when($itemIds !== [], fn ($query) => $query->whereIn('id', $itemIds))
                ->where('status', 'active')
                ->orderBy('name')
                ->limit(60)
                ->get(['id', 'sku', 'barcode', 'name', 'brand', 'category_id']);
        }

        if ($items->isEmpty()) {
            abort(404, __('No items found.'));
        }

        $rows = $items->map(function (Item $item) use ($service, $format, $size): array {
            [$barcodeValue, $qrData, $qrSvg, $barcodeSvg] = $this->buildForItem($item, $service, $format, $size);

            return compact('item', 'barcodeValue', 'qrData', 'qrSvg', 'barcodeSvg');
        });

        return response()->view('labels.bulk', [
            'rows' => $rows,
            'format' => $format,
            'size' => $size,
            'sizes' => self::sizes(),
            'filter' => [
                'search' => (string) $request->query('search', ''),
                'category_id' => (string) $request->query('category_id', ''),
                'warehouse_id' => (string) $request->query('warehouse_id', ''),
            ],
            'filters' => [
                'categories' => Category::orderBy('name')->get(['id', 'name']),
                'warehouses' => Warehouse::orderBy('name')->get(['id', 'name']),
            ],
        ])->header('Content-Type', 'text/html');
    }

    public function print(Request $request, LabelService $service): Response
    {
        return $this->bulk($request, $service);
    }

    /** @return array<string, array{label:string, width:int, height:int}> */
    public static function sizes(): array
    {
        return [
            '50x30' => ['label' => '50 × 30 mm', 'width' => 50, 'height' => 30],
            '70x40' => ['label' => '70 × 40 mm', 'width' => 70, 'height' => 40],
            '85x54' => ['label' => '85 × 54 mm', 'width' => 85, 'height' => 54],
            '100x50' => ['label' => '100 × 50 mm', 'width' => 100, 'height' => 50],
        ];
    }

    private function sizeParam(mixed $size): string
    {
        $size = (string) $size;

        return array_key_exists($size, self::sizes()) ? $size : '85x54';
    }

    /** @return array{0:string,1:string,2:string,3:string} */
    private function buildForItem(Item $item, LabelService $service, string $format, string $size): array
    {
        $barcodeValue = $service->barcodeValue($item->barcode, $item->sku);
        $barcodeValue = preg_replace('/[^\x20-\x7E]/', '', (string) $barcodeValue);
        $qrData = route('items.show', $item, false).'|'.$item->sku.'|'.$barcodeValue;
        $qrSvg = $service->qrSvg((string) $qrData, 160);
        $barcodeSvg = $service->barcodeSvg((string) $barcodeValue);

        return [$barcodeValue, $qrData, $qrSvg, $barcodeSvg];
    }
}
