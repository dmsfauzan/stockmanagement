<?php

namespace App\Http\Controllers\Api;

use App\Enums\StockStatus;
use App\Http\Resources\Api\StockBalanceResource;
use App\Http\Resources\Api\StockMovementResource;
use App\Services\Inventory\TraceabilityService;
use App\Services\Support\WarehouseAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, (int) $request->integer('per_page', 15)));

        $paginator = $this->balanceQuery($request)->paginate($perPage)->withQueryString();
        $paginator->getCollection()->transform(function ($row) {
            $row->stock_status = StockStatus::evaluate((int) $row->quantity_on_hand, (int) $row->min_stock, (int) $row->max_stock)->value;

            return $row;
        });

        return $this->paginated(StockBalanceResource::collection($paginator));
    }

    public function low(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, (int) $request->integer('per_page', 15)));

        $paginator = $this->balanceQuery($request)
            ->whereColumn('stock_balances.quantity_on_hand', '<=', 'items.minimum_stock')
            ->paginate($perPage)->withQueryString();
        $paginator->getCollection()->transform(function ($row) {
            $row->stock_status = StockStatus::evaluate((int) $row->quantity_on_hand, (int) $row->min_stock, (int) $row->max_stock)->value;

            return $row;
        });

        return $this->paginated(StockBalanceResource::collection($paginator));
    }

    public function movements(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, (int) $request->integer('per_page', 15)));

        $query = DB::table('stock_movements')
            ->leftJoin('items', 'stock_movements.item_id', '=', 'items.id')
            ->leftJoin('warehouses', 'stock_movements.warehouse_id', '=', 'warehouses.id')
            ->leftJoin('locations', 'stock_movements.location_id', '=', 'locations.id')
            ->leftJoin('users', 'stock_movements.created_by', '=', 'users.id')
            ->whereIn('stock_movements.warehouse_id', WarehouseAccess::ids())
            ->select([
                'stock_movements.id',
                'stock_movements.created_at',
                'stock_movements.reference_type',
                'stock_movements.reference_id',
                'stock_movements.transaction_type',
                'stock_movements.quantity_in',
                'stock_movements.quantity_out',
                'stock_movements.balance_after',
                'stock_movements.unit_cost',
                'stock_movements.total_cost',
                'items.sku',
                'items.name as item_name',
                'warehouses.name as warehouse_name',
                'locations.code as location_code',
                'users.name as user_name',
            ])
            ->when($request->filled('search'), function ($q) use ($request): void {
                $term = '%'.$request->string('search').'%';
                $q->where(function ($inner) use ($term): void {
                    $inner->where('items.sku', 'like', $term)->orWhere('items.name', 'like', $term);
                });
            })
            ->when($request->filled('item_id'), fn ($q) => $q->where('stock_movements.item_id', $request->integer('item_id')))
            ->when($request->filled('warehouse_id'), fn ($q) => $q->where('stock_movements.warehouse_id', $request->integer('warehouse_id')))
            ->when($request->filled('transaction_type'), fn ($q) => $q->where('stock_movements.transaction_type', $request->string('transaction_type')))
            ->when($request->filled('date_from') || $request->filled('dateFrom'), fn ($q) => $q->whereDate('stock_movements.created_at', '>=', $request->string('date_from') ?: $request->string('dateFrom')))
            ->when($request->filled('date_to') || $request->filled('dateTo'), fn ($q) => $q->whereDate('stock_movements.created_at', '<=', $request->string('date_to') ?: $request->string('dateTo')))
            ->orderByDesc('stock_movements.created_at')
            ->orderByDesc('stock_movements.id');

        $paginator = $query->paginate($perPage)->withQueryString();

        return $this->paginated(StockMovementResource::collection($paginator));
    }

    public function traceability(string $type, string $value): JsonResponse
    {
        $result = match ($type) {
            'serial' => TraceabilityService::forSerial($value),
            'batch', 'lot' => TraceabilityService::forBatch($value),
            default => null,
        };

        if ($result === null) {
            return $this->error('Unknown traceability type. Use batch or serial.', 422);
        }

        return $this->ok($result, 'OK');
    }

    protected function balanceQuery(Request $request)
    {
        $q = DB::table('stock_balances')
            ->join('items', 'stock_balances.item_id', '=', 'items.id')
            ->join('warehouses', 'stock_balances.warehouse_id', '=', 'warehouses.id')
            ->join('locations', 'stock_balances.location_id', '=', 'locations.id')
            ->join('racks', 'locations.rack_id', '=', 'racks.id')
            ->join('zones', 'racks.zone_id', '=', 'zones.id')
            ->join('categories', 'items.category_id', '=', 'categories.id')
            ->whereNull('items.deleted_at')
            ->whereIn('warehouses.id', WarehouseAccess::ids())
            ->select([
                'stock_balances.id',
                'stock_balances.quantity_on_hand',
                'stock_balances.quantity_reserved',
                'stock_balances.quantity_quarantine',
                'stock_balances.quantity_available',
                'stock_balances.last_movement_at',
                'items.sku',
                'items.name as item_name',
                'items.minimum_stock as min_stock',
                'items.maximum_stock as max_stock',
                'categories.name as category_name',
                'warehouses.id as warehouse_id',
                'warehouses.name as warehouse_name',
                'locations.id as location_id',
                'locations.code as location_code',
                DB::raw("CONCAT_WS(' / ', warehouses.name, zones.name, racks.name, locations.code) as location_path"),
            ])
            ->when($request->filled('search'), function ($query) use ($request): void {
                $term = '%'.$request->string('search').'%';
                $query->where(function ($inner) use ($term): void {
                    $inner->where('items.sku', 'like', $term)
                        ->orWhere('items.barcode', 'like', $term)
                        ->orWhere('items.name', 'like', $term);
                });
            })
            ->when($request->filled('warehouse_id'), fn ($query) => $query->where('warehouses.id', $request->integer('warehouse_id')));

        $status = (string) $request->string('status');
        if ($status !== '') {
            match ($status) {
                'out' => $q->where('stock_balances.quantity_on_hand', '<=', 0),
                'low' => $q->where('stock_balances.quantity_on_hand', '>', 0)->whereColumn('stock_balances.quantity_on_hand', '<=', 'items.minimum_stock'),
                'over' => $q->where('items.maximum_stock', '>', 0)->whereColumn('stock_balances.quantity_on_hand', '>', 'items.maximum_stock'),
                'normal' => $q->where('stock_balances.quantity_on_hand', '>', 0)
                    ->whereColumn('stock_balances.quantity_on_hand', '>', 'items.minimum_stock')
                    ->where(function ($inner): void {
                        $inner->where('items.maximum_stock', '=', 0)->orWhereColumn('stock_balances.quantity_on_hand', '<=', 'items.maximum_stock');
                    }),
                default => null,
            };
        }

        return $q->orderBy('items.sku');
    }
}
