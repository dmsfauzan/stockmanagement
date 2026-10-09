<?php

namespace App\Http\Controllers\Api;

use App\Services\Inventory\QualityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QualityController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, (int) $request->integer('per_page', 15)));

        $query = DB::table('stock_balances')
            ->join('items', 'stock_balances.item_id', '=', 'items.id')
            ->join('warehouses', 'stock_balances.warehouse_id', '=', 'warehouses.id')
            ->join('locations', 'stock_balances.location_id', '=', 'locations.id')
            ->join('racks', 'locations.rack_id', '=', 'racks.id')
            ->join('zones', 'racks.zone_id', '=', 'zones.id')
            ->whereNull('items.deleted_at')
            ->where('stock_balances.quantity_quarantine', '>', 0)
            ->when($request->filled('warehouse_id'), fn ($q) => $q->where('warehouses.id', $request->integer('warehouse_id')))
            ->when($request->filled('item_id'), fn ($q) => $q->where('stock_balances.item_id', $request->integer('item_id')))
            ->when($request->filled('search'), function ($q) use ($request): void {
                $term = '%'.$request->string('search').'%';
                $q->where(function ($inner) use ($term): void {
                    $inner->where('items.sku', 'like', $term)->orWhere('items.name', 'like', $term);
                });
            })
            ->select([
                'stock_balances.id',
                'stock_balances.item_id',
                'stock_balances.warehouse_id',
                'stock_balances.location_id',
                'stock_balances.quantity_on_hand',
                'stock_balances.quantity_reserved',
                'stock_balances.quantity_quarantine',
                'stock_balances.quantity_available',
                'items.sku',
                'items.name as item_name',
                'warehouses.name as warehouse_name',
                DB::raw("CONCAT_WS(' / ', warehouses.name, zones.name, racks.name, locations.code) as location_path"),
            ])
            ->orderBy('items.sku');

        $paginator = $query->paginate($perPage)->withQueryString();

        return $this->paginated($paginator);
    }

    public function release(Request $request, int $id): JsonResponse
    {
        return $this->decide($request, $id, 'release');
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        return $this->decide($request, $id, 'reject');
    }

    protected function decide(Request $request, int $balanceId, string $action): JsonResponse
    {
        $balance = DB::table('stock_balances')->where('id', $balanceId)->first();

        if (! $balance) {
            return $this->error('Stock balance not found', 404);
        }

        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:200'],
        ]);

        try {
            if ($action === 'release') {
                QualityService::release((int) $balance->item_id, (int) $balance->warehouse_id, (int) $balance->location_id, (int) $data['quantity'], $data['reason'] ?? null);
            } else {
                QualityService::reject((int) $balance->item_id, (int) $balance->warehouse_id, (int) $balance->location_id, (int) $data['quantity'], $data['reason'] ?? null);
            }
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        $updated = DB::table('stock_balances')->where('id', $balanceId)->first();

        return $this->ok([
            'id' => (int) $balanceId,
            'action' => $action,
            'quantity_on_hand' => (int) $updated->quantity_on_hand,
            'quantity_quarantine' => (int) $updated->quantity_quarantine,
            'quantity_available' => (int) $updated->quantity_available,
        ], 'OK');
    }
}
