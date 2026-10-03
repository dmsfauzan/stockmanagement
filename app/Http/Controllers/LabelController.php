<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Location;
use App\Services\Barcode\LabelService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class LabelController extends Controller
{
    public function item(Item $item, Request $request, LabelService $service): Response
    {
        Gate::authorize('view', $item);

        $format = $request->query('format', 'both');

        [$barcodeValue, $qrData, $qrSvg, $barcodeSvg] = $this->buildForItem($item, $service, $request);

        return response()->view('labels.item', [
            'item' => $item,
            'format' => $format,
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

        if (count($ids) === 0) {
            abort(404, 'No items selected.');
        }

        $items = Item::whereKey($ids)->get(['id', 'sku', 'barcode', 'name']);

        $format = $request->query('format', 'qr');

        $rows = $items->map(function (Item $item) use ($service, $request): array {
            [$barcodeValue, $qrData, $qrSvg, $barcodeSvg] = $this->buildForItem($item, $service, $request);

            return compact('item', 'barcodeValue', 'qrData', 'qrSvg', 'barcodeSvg');
        });

        return response()->view('labels.bulk', [
            'rows' => $rows,
            'format' => $format,
        ])->header('Content-Type', 'text/html');
    }

    /** @return array{0:string,1:string,2:string,3:string} */
    private function buildForItem(Item $item, LabelService $service, Request $request): array
    {
        $barcodeValue = $service->barcodeValue($item->barcode, $item->sku);
        $barcodeValue = preg_replace('/[^\x20-\x7E]/', '', $barcodeValue);
        $qrData = route('items.show', $item, false).'|'.$item->sku.'|'.$barcodeValue;
        $qrSvg = $service->qrSvg($qrData, 160);
        $barcodeSvg = $service->barcodeSvg($barcodeValue);

        return [$barcodeValue, $qrData, $qrSvg, $barcodeSvg];
    }
}
