<?php

namespace App\Http\Controllers\Api;

use App\Enums\StockStatus;
use App\Services\Accounting\JournalService;
use App\Services\Inventory\ExpiryService;
use App\Services\Inventory\InventoryAnalyticsService;
use App\Services\Inventory\InventoryForecastService;
use App\Services\Inventory\ReplenishmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReportController extends ApiController
{
    protected function perPage(Request $request): int
    {
        return min(200, max(1, (int) $request->integer('per_page', 15)));
    }

    protected function dateFilter($query, Request $request, string $column, string $fromKey = 'date_from', string $toKey = 'date_to')
    {
        return $query
            ->when($request->filled($fromKey), fn ($q) => $q->whereDate($column, '>=', $request->string($fromKey)))
            ->when($request->filled($toKey), fn ($q) => $q->whereDate($column, '<=', $request->string($toKey)));
    }

    public function stock(Request $request): JsonResponse
    {
        $q = DB::table('stock_balances')
            ->join('items', 'stock_balances.item_id', '=', 'items.id')
            ->join('warehouses', 'stock_balances.warehouse_id', '=', 'warehouses.id')
            ->join('locations', 'stock_balances.location_id', '=', 'locations.id')
            ->join('racks', 'locations.rack_id', '=', 'racks.id')
            ->join('zones', 'racks.zone_id', '=', 'zones.id')
            ->join('categories', 'items.category_id', '=', 'categories.id')
            ->whereNull('items.deleted_at')
            ->select([
                'stock_balances.quantity_on_hand',
                'stock_balances.quantity_reserved',
                'stock_balances.quantity_available',
                'items.sku',
                'items.name as item_name',
                'items.minimum_stock as min_stock',
                'items.maximum_stock as max_stock',
                'categories.name as category_name',
                'warehouses.name as warehouse_name',
                DB::raw("CONCAT_WS(' / ', warehouses.name, zones.name, racks.name, locations.code) as location_path"),
            ])
            ->when($request->filled('search'), function ($query) use ($request): void {
                $term = '%'.$request->string('search').'%';
                $query->where(function ($inner) use ($term): void {
                    $inner->where('items.sku', 'like', $term)->orWhere('items.barcode', 'like', $term)->orWhere('items.name', 'like', $term);
                });
            })
            ->when($request->filled('warehouse_id'), fn ($query) => $query->where('warehouses.id', $request->integer('warehouse_id')))
            ->when($request->filled('category_id'), fn ($query) => $query->where('categories.id', $request->integer('category_id')))
            ->when($request->filled('location_id'), fn ($query) => $query->where('locations.id', $request->integer('location_id')));

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

        $paginator = $q->orderBy('items.sku')->paginate($this->perPage($request))->withQueryString();
        $paginator->getCollection()->transform(fn ($r) => [
            'sku' => $r->sku,
            'item_name' => $r->item_name,
            'category_name' => $r->category_name,
            'warehouse_name' => $r->warehouse_name,
            'location_path' => $r->location_path,
            'quantity_on_hand' => (int) $r->quantity_on_hand,
            'quantity_reserved' => (int) $r->quantity_reserved,
            'quantity_available' => (int) $r->quantity_available,
            'min_stock' => (int) $r->min_stock,
            'max_stock' => (int) $r->max_stock,
            'status' => StockStatus::evaluate((int) $r->quantity_on_hand, (int) $r->min_stock, (int) $r->max_stock)->value,
        ]);

        return $this->paginated($paginator);
    }

    public function incoming(Request $request): JsonResponse
    {
        $q = DB::table('goods_receipt_items')
            ->join('goods_receipts', 'goods_receipt_items.goods_receipt_id', '=', 'goods_receipts.id')
            ->join('items', 'goods_receipt_items.item_id', '=', 'items.id')
            ->leftJoin('suppliers', 'goods_receipts.supplier_id', '=', 'suppliers.id')
            ->join('warehouses', 'goods_receipts.warehouse_id', '=', 'warehouses.id')
            ->where('goods_receipts.status', 'posted')
            ->select([
                'goods_receipts.number as receipt_number',
                'goods_receipts.transaction_date',
                'suppliers.name as supplier_name',
                'warehouses.name as warehouse_name',
                'items.sku',
                'items.name as item_name',
                'goods_receipt_items.quantity as qty',
                'goods_receipt_items.batch_number',
                'goods_receipt_items.expiry_date',
                'goods_receipts.posted_at',
            ])
            ->when($request->filled('search'), function ($query) use ($request): void {
                $term = '%'.$request->string('search').'%';
                $query->where(function ($inner) use ($term): void {
                    $inner->where('goods_receipts.number', 'like', $term)->orWhere('items.sku', 'like', $term)->orWhere('items.name', 'like', $term);
                });
            })
            ->when($request->filled('supplier_id'), fn ($query) => $query->where('goods_receipts.supplier_id', $request->integer('supplier_id')))
            ->when($request->filled('warehouse_id'), fn ($query) => $query->where('goods_receipts.warehouse_id', $request->integer('warehouse_id')));

        $q = $this->dateFilter($q, $request, 'goods_receipts.transaction_date');

        $paginator = $q->orderByDesc('goods_receipts.transaction_date')->orderByDesc('goods_receipt_items.id')->paginate($this->perPage($request))->withQueryString();
        $paginator->getCollection()->transform(fn ($r) => [
            'receipt_number' => $r->receipt_number,
            'transaction_date' => $r->transaction_date,
            'supplier_name' => $r->supplier_name,
            'warehouse_name' => $r->warehouse_name,
            'sku' => $r->sku,
            'item_name' => $r->item_name,
            'qty' => (int) $r->qty,
            'batch_number' => $r->batch_number,
            'expiry_date' => $r->expiry_date,
            'posted_at' => $r->posted_at,
        ]);

        return $this->paginated($paginator);
    }

    public function outgoing(Request $request): JsonResponse
    {
        $q = DB::table('goods_issue_items')
            ->join('goods_issues', 'goods_issue_items.goods_issue_id', '=', 'goods_issues.id')
            ->join('items', 'goods_issue_items.item_id', '=', 'items.id')
            ->leftJoin('customers', 'goods_issues.customer_id', '=', 'customers.id')
            ->join('warehouses', 'goods_issues.warehouse_id', '=', 'warehouses.id')
            ->where('goods_issues.status', 'posted')
            ->select([
                'goods_issues.number as issue_number',
                'goods_issues.transaction_date',
                'customers.name as customer_name',
                'goods_issues.destination',
                'warehouses.name as warehouse_name',
                'items.sku',
                'items.name as item_name',
                'goods_issue_items.quantity as qty',
                'goods_issues.posted_at',
            ])
            ->when($request->filled('search'), function ($query) use ($request): void {
                $term = '%'.$request->string('search').'%';
                $query->where(function ($inner) use ($term): void {
                    $inner->where('goods_issues.number', 'like', $term)->orWhere('items.sku', 'like', $term)->orWhere('items.name', 'like', $term);
                });
            })
            ->when($request->filled('customer_id'), fn ($query) => $query->where('goods_issues.customer_id', $request->integer('customer_id')))
            ->when($request->filled('warehouse_id'), fn ($query) => $query->where('goods_issues.warehouse_id', $request->integer('warehouse_id')));

        $q = $this->dateFilter($q, $request, 'goods_issues.transaction_date');

        $paginator = $q->orderByDesc('goods_issues.transaction_date')->orderByDesc('goods_issue_items.id')->paginate($this->perPage($request))->withQueryString();
        $paginator->getCollection()->transform(fn ($r) => [
            'issue_number' => $r->issue_number,
            'transaction_date' => $r->transaction_date,
            'customer_name' => $r->customer_name,
            'destination' => $r->destination,
            'warehouse_name' => $r->warehouse_name,
            'sku' => $r->sku,
            'item_name' => $r->item_name,
            'qty' => (int) $r->qty,
            'posted_at' => $r->posted_at,
        ]);

        return $this->paginated($paginator);
    }

    public function movement(Request $request): JsonResponse
    {
        $q = DB::table('stock_movements')
            ->leftJoin('items', 'stock_movements.item_id', '=', 'items.id')
            ->leftJoin('warehouses', 'stock_movements.warehouse_id', '=', 'warehouses.id')
            ->leftJoin('locations', 'stock_movements.location_id', '=', 'locations.id')
            ->leftJoin('users', 'stock_movements.created_by', '=', 'users.id')
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
            ->when($request->filled('search'), function ($query) use ($request): void {
                $term = '%'.$request->string('search').'%';
                $query->where(function ($inner) use ($term): void {
                    $inner->where('items.sku', 'like', $term)->orWhere('items.name', 'like', $term);
                });
            })
            ->when($request->filled('item_id'), fn ($query) => $query->where('stock_movements.item_id', $request->integer('item_id')))
            ->when($request->filled('warehouse_id'), fn ($query) => $query->where('stock_movements.warehouse_id', $request->integer('warehouse_id')))
            ->when($request->filled('transaction_type'), fn ($query) => $query->where('stock_movements.transaction_type', $request->string('transaction_type')))
            ->when($request->filled('user_id'), fn ($query) => $query->where('stock_movements.created_by', $request->integer('user_id')));

        $q = $this->dateFilter($q, $request, 'stock_movements.created_at');

        $paginator = $q->orderByDesc('stock_movements.created_at')->orderByDesc('stock_movements.id')->paginate($this->perPage($request))->withQueryString();
        $paginator->getCollection()->transform(fn ($r) => [
            'created_at' => $r->created_at,
            'sku' => $r->sku,
            'item_name' => $r->item_name,
            'warehouse_name' => $r->warehouse_name,
            'location_code' => $r->location_code,
            'transaction_type' => $r->transaction_type,
            'quantity_in' => (int) $r->quantity_in,
            'quantity_out' => (int) $r->quantity_out,
            'balance_after' => (int) $r->balance_after,
            'unit_cost' => (float) $r->unit_cost,
            'total_cost' => (float) $r->total_cost,
            'user_name' => $r->user_name,
        ]);

        return $this->paginated($paginator);
    }

    public function expiry(Request $request): JsonResponse
    {
        $q = ExpiryService::rows(
            $request->filled('status') ? (string) $request->string('status') : 'all',
            $request->filled('search') ? (string) $request->string('search') : null,
            $request->filled('warehouse_id') ? $request->integer('warehouse_id') : null,
        );

        $q->when($request->filled('location_id'), fn ($query) => $query->where('stock_movements.location_id', $request->integer('location_id')))
            ->when($request->filled('batch'), fn ($query) => $query->where('stock_movements.batch_number', 'like', '%'.$request->string('batch').'%'));

        $q = $this->dateFilter($q, $request, 'stock_movements.expiry_date');

        $paginator = $q->paginate($this->perPage($request))->withQueryString();
        $paginator->getCollection()->transform(fn ($r) => [
            'sku' => $r->sku,
            'item_name' => $r->item_name,
            'warehouse_name' => $r->warehouse_name,
            'location_code' => $r->location_code,
            'batch_number' => $r->batch_number,
            'expiry_date' => $r->expiry_date,
            'days_left' => (int) ($r->days_left ?? 0),
            'quantity_in' => (int) $r->quantity_in,
            'reference' => trim(($r->reference_type ?? '').' #'.($r->reference_id ?? '-')),
        ]);

        return $this->paginated($paginator);
    }

    public function opname(Request $request): JsonResponse
    {
        $q = DB::table('stock_opnames')
            ->join('stock_opname_items', 'stock_opname_items.stock_opname_id', '=', 'stock_opnames.id')
            ->join('items', 'items.id', '=', 'stock_opname_items.item_id')
            ->join('warehouses', 'warehouses.id', '=', 'stock_opnames.warehouse_id')
            ->leftJoin('locations', 'locations.id', '=', 'stock_opnames.location_id')
            ->select([
                'stock_opnames.number as opname_number',
                'stock_opnames.opname_date',
                'stock_opnames.status',
                'warehouses.name as warehouse_name',
                'locations.code as location_code',
                'items.sku',
                'items.name as item_name',
                'stock_opname_items.system_quantity',
                'stock_opname_items.physical_quantity',
                'stock_opname_items.difference',
                'stock_opname_items.reason',
            ])
            ->when($request->filled('search'), function ($query) use ($request): void {
                $term = '%'.$request->string('search').'%';
                $query->where(function ($inner) use ($term): void {
                    $inner->where('stock_opnames.number', 'like', $term)->orWhere('items.sku', 'like', $term)->orWhere('items.name', 'like', $term);
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('stock_opnames.status', $request->string('status')))
            ->when($request->filled('warehouse_id'), fn ($query) => $query->where('stock_opnames.warehouse_id', $request->integer('warehouse_id')));

        $q = $this->dateFilter($q, $request, 'stock_opnames.opname_date');

        $paginator = $q->orderByDesc('stock_opnames.opname_date')->orderByDesc('stock_opnames.number')->paginate($this->perPage($request))->withQueryString();
        $paginator->getCollection()->transform(fn ($r) => [
            'opname_number' => $r->opname_number,
            'opname_date' => $r->opname_date,
            'warehouse_name' => $r->warehouse_name,
            'location_code' => $r->location_code,
            'sku' => $r->sku,
            'item_name' => $r->item_name,
            'system_quantity' => (int) $r->system_quantity,
            'physical_quantity' => $r->physical_quantity !== null ? (int) $r->physical_quantity : null,
            'difference' => (int) $r->difference,
            'reason' => $r->reason,
            'status' => $r->status,
        ]);

        return $this->paginated($paginator);
    }

    public function adjustment(Request $request): JsonResponse
    {
        $q = DB::table('stock_adjustments')
            ->join('stock_adjustment_items', 'stock_adjustment_items.stock_adjustment_id', '=', 'stock_adjustments.id')
            ->join('items', 'items.id', '=', 'stock_adjustment_items.item_id')
            ->join('warehouses', 'warehouses.id', '=', 'stock_adjustments.warehouse_id')
            ->leftJoin('locations', 'locations.id', '=', 'stock_adjustments.location_id')
            ->select([
                'stock_adjustments.number as adj_number',
                'stock_adjustments.transaction_date',
                'stock_adjustments.status',
                'warehouses.name as warehouse_name',
                'locations.code as location_code',
                'items.sku',
                'items.name as item_name',
                'stock_adjustment_items.system_quantity',
                'stock_adjustment_items.actual_quantity',
                'stock_adjustment_items.difference',
                'stock_adjustments.reason',
            ])
            ->when($request->filled('search'), function ($query) use ($request): void {
                $term = '%'.$request->string('search').'%';
                $query->where(function ($inner) use ($term): void {
                    $inner->where('stock_adjustments.number', 'like', $term)
                        ->orWhere('stock_adjustments.reason', 'like', $term)
                        ->orWhere('items.sku', 'like', $term)
                        ->orWhere('items.name', 'like', $term);
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('stock_adjustments.status', $request->string('status')))
            ->when($request->filled('warehouse_id'), fn ($query) => $query->where('stock_adjustments.warehouse_id', $request->integer('warehouse_id')));

        $q = $this->dateFilter($q, $request, 'stock_adjustments.transaction_date');

        $paginator = $q->orderByDesc('stock_adjustments.transaction_date')->orderByDesc('stock_adjustments.number')->paginate($this->perPage($request))->withQueryString();
        $paginator->getCollection()->transform(fn ($r) => [
            'adj_number' => $r->adj_number,
            'transaction_date' => $r->transaction_date,
            'warehouse_name' => $r->warehouse_name,
            'location_code' => $r->location_code,
            'sku' => $r->sku,
            'item_name' => $r->item_name,
            'system_quantity' => (int) $r->system_quantity,
            'actual_quantity' => (int) $r->actual_quantity,
            'difference' => (int) $r->difference,
            'status' => $r->status,
        ]);

        return $this->paginated($paginator);
    }

    public function transfer(Request $request): JsonResponse
    {
        $q = DB::table('stock_transfers')
            ->join('stock_transfer_items', 'stock_transfer_items.stock_transfer_id', '=', 'stock_transfers.id')
            ->join('items', 'items.id', '=', 'stock_transfer_items.item_id')
            ->join('warehouses as fw', 'fw.id', '=', 'stock_transfers.from_warehouse_id')
            ->join('warehouses as tw', 'tw.id', '=', 'stock_transfers.to_warehouse_id')
            ->leftJoin('locations as fl', 'fl.id', '=', 'stock_transfers.from_location_id')
            ->leftJoin('locations as tl', 'tl.id', '=', 'stock_transfers.to_location_id')
            ->select([
                'stock_transfers.number as tr_number',
                'stock_transfers.transfer_date',
                'stock_transfers.status',
                'fw.name as from_warehouse',
                'fl.code as from_location',
                'tw.name as to_warehouse',
                'tl.code as to_location',
                'items.sku',
                'items.name as item_name',
                'stock_transfer_items.quantity',
            ])
            ->when($request->filled('search'), function ($query) use ($request): void {
                $term = '%'.$request->string('search').'%';
                $query->where(function ($inner) use ($term): void {
                    $inner->where('stock_transfers.number', 'like', $term)->orWhere('items.sku', 'like', $term)->orWhere('items.name', 'like', $term);
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('stock_transfers.status', $request->string('status')))
            ->when($request->filled('from_warehouse_id'), fn ($query) => $query->where('stock_transfers.from_warehouse_id', $request->integer('from_warehouse_id')))
            ->when($request->filled('to_warehouse_id'), fn ($query) => $query->where('stock_transfers.to_warehouse_id', $request->integer('to_warehouse_id')));

        $q = $this->dateFilter($q, $request, 'stock_transfers.transfer_date');

        $paginator = $q->orderByDesc('stock_transfers.transfer_date')->orderByDesc('stock_transfers.number')->paginate($this->perPage($request))->withQueryString();
        $paginator->getCollection()->transform(fn ($r) => [
            'tr_number' => $r->tr_number,
            'transfer_date' => $r->transfer_date,
            'from_warehouse' => $r->from_warehouse,
            'from_location' => $r->from_location,
            'to_warehouse' => $r->to_warehouse,
            'to_location' => $r->to_location,
            'sku' => $r->sku,
            'item_name' => $r->item_name,
            'quantity' => (int) $r->quantity,
            'status' => $r->status,
        ]);

        return $this->paginated($paginator);
    }

    public function warehouseComparison(Request $request): JsonResponse
    {
        $today = now()->toDateString();
        $warn = now()->addDays(ExpiryService::warnDays())->toDateString();

        $expired = "SELECT COUNT(*) FROM stock_movements sm WHERE sm.warehouse_id = warehouses.id AND sm.transaction_type = 'incoming' AND sm.expiry_date IS NOT NULL AND sm.expiry_date < '{$today}'";
        $soon = "SELECT COUNT(*) FROM stock_movements sm WHERE sm.warehouse_id = warehouses.id AND sm.transaction_type = 'incoming' AND sm.expiry_date IS NOT NULL AND sm.expiry_date >= '{$today}' AND sm.expiry_date <= '{$warn}'";

        $q = DB::table('warehouses')
            ->leftJoin('stock_balances', 'stock_balances.warehouse_id', '=', 'warehouses.id')
            ->leftJoin('items', function ($join): void {
                $join->on('items.id', '=', 'stock_balances.item_id')->whereNull('items.deleted_at');
            })
            ->when($request->filled('search'), fn ($query) => $query->where(function ($inner) use ($request): void {
                $term = '%'.$request->string('search').'%';
                $inner->where('warehouses.name', 'like', $term)->orWhere('warehouses.code', 'like', $term);
            }))
            ->groupBy('warehouses.id', 'warehouses.code', 'warehouses.name')
            ->select([
                'warehouses.code',
                'warehouses.name',
                DB::raw('COUNT(DISTINCT CASE WHEN items.id IS NOT NULL THEN stock_balances.item_id END) as total_items'),
                DB::raw('COALESCE(SUM(stock_balances.quantity_on_hand),0) as total_on_hand'),
                DB::raw('COALESCE(SUM(stock_balances.quantity_available),0) as total_available'),
                DB::raw('COALESCE(SUM(CASE WHEN stock_balances.quantity_on_hand > 0 AND stock_balances.quantity_on_hand <= items.minimum_stock THEN 1 ELSE 0 END),0) as low_count'),
                DB::raw('COALESCE(SUM(CASE WHEN stock_balances.quantity_on_hand <= 0 THEN 1 ELSE 0 END),0) as out_count'),
                DB::raw("({$expired}) as expired_count"),
                DB::raw("({$soon}) as soon_count"),
            ])
            ->orderBy('warehouses.name');

        $paginator = $q->paginate($this->perPage($request))->withQueryString();
        $paginator->getCollection()->transform(fn ($r) => [
            'code' => $r->code,
            'name' => $r->name,
            'total_items' => (int) $r->total_items,
            'total_on_hand' => (int) $r->total_on_hand,
            'total_available' => (int) $r->total_available,
            'low_count' => (int) $r->low_count,
            'out_count' => (int) $r->out_count,
            'expired_count' => (int) $r->expired_count,
            'soon_count' => (int) $r->soon_count,
        ]);

        return $this->paginated($paginator);
    }

    public function valuation(Request $request): JsonResponse
    {
        $q = DB::table('inventory_valuations')
            ->join('items', 'inventory_valuations.item_id', '=', 'items.id')
            ->join('warehouses', 'inventory_valuations.warehouse_id', '=', 'warehouses.id')
            ->join('categories', 'items.category_id', '=', 'categories.id')
            ->whereNull('items.deleted_at')
            ->select([
                'items.sku',
                'items.name as item_name',
                'categories.name as category_name',
                'warehouses.name as warehouse_name',
                'inventory_valuations.quantity',
                'inventory_valuations.average_cost',
                'inventory_valuations.total_value',
            ])
            ->when($request->filled('search'), function ($query) use ($request): void {
                $term = '%'.$request->string('search').'%';
                $query->where(function ($inner) use ($term): void {
                    $inner->where('items.sku', 'like', $term)->orWhere('items.barcode', 'like', $term)->orWhere('items.name', 'like', $term);
                });
            })
            ->when($request->filled('warehouse_id'), fn ($query) => $query->where('warehouses.id', $request->integer('warehouse_id')))
            ->when($request->filled('category_id'), fn ($query) => $query->where('categories.id', $request->integer('category_id')));

        $paginator = $q->orderBy('items.sku')->paginate($this->perPage($request))->withQueryString();
        $paginator->getCollection()->transform(fn ($r) => [
            'sku' => $r->sku,
            'item_name' => $r->item_name,
            'category_name' => $r->category_name,
            'warehouse_name' => $r->warehouse_name,
            'quantity' => (int) $r->quantity,
            'average_cost' => (float) $r->average_cost,
            'total_value' => (float) $r->total_value,
        ]);

        return $this->paginated($paginator);
    }

    public function cogs(Request $request): JsonResponse
    {
        $q = DB::table('stock_movements')
            ->join('items', 'stock_movements.item_id', '=', 'items.id')
            ->join('warehouses', 'stock_movements.warehouse_id', '=', 'warehouses.id')
            ->whereIn('stock_movements.transaction_type', ['outgoing', 'adjustment_out', 'transfer_out'])
            ->where('stock_movements.total_cost', '>', 0)
            ->whereNull('items.deleted_at')
            ->select([
                'stock_movements.created_at',
                'stock_movements.reference_type',
                'stock_movements.reference_id',
                'items.sku',
                'items.name as item_name',
                'warehouses.name as warehouse_name',
                'stock_movements.quantity_out',
                'stock_movements.unit_cost',
                'stock_movements.total_cost',
            ])
            ->when($request->filled('search'), function ($query) use ($request): void {
                $term = '%'.$request->string('search').'%';
                $query->where(function ($inner) use ($term): void {
                    $inner->where('items.sku', 'like', $term)->orWhere('items.barcode', 'like', $term)->orWhere('items.name', 'like', $term);
                });
            })
            ->when($request->filled('warehouse_id'), fn ($query) => $query->where('warehouses.id', $request->integer('warehouse_id')));

        $q = $this->dateFilter($q, $request, 'stock_movements.created_at');

        $paginator = $q->orderByDesc('stock_movements.created_at')->orderByDesc('stock_movements.id')->paginate($this->perPage($request))->withQueryString();
        $paginator->getCollection()->transform(fn ($r) => [
            'created_at' => $r->created_at,
            'reference' => $r->reference_type.' #'.$r->reference_id,
            'sku' => $r->sku,
            'item_name' => $r->item_name,
            'warehouse_name' => $r->warehouse_name,
            'quantity_out' => (int) $r->quantity_out,
            'unit_cost' => (float) $r->unit_cost,
            'total_cost' => (float) $r->total_cost,
        ]);

        return $this->paginated($paginator);
    }

    public function journal(Request $request): JsonResponse
    {
        $q = DB::table('stock_movements')
            ->join('items', 'stock_movements.item_id', '=', 'items.id')
            ->join('warehouses', 'stock_movements.warehouse_id', '=', 'warehouses.id')
            ->whereNull('items.deleted_at')
            ->select([
                'stock_movements.created_at',
                'stock_movements.reference_type',
                'stock_movements.reference_id',
                'stock_movements.transaction_type',
                'stock_movements.quantity_in',
                'stock_movements.quantity_out',
                'stock_movements.unit_cost',
                'stock_movements.total_cost',
                'stock_movements.notes',
                'items.sku',
                'items.name as item_name',
                'warehouses.name as warehouse_name',
            ])
            ->when($request->filled('search'), function ($query) use ($request): void {
                $term = '%'.$request->string('search').'%';
                $query->where(function ($inner) use ($term): void {
                    $inner->where('items.sku', 'like', $term)
                        ->orWhere('items.name', 'like', $term)
                        ->orWhere('stock_movements.reference_type', 'like', $term)
                        ->orWhere('stock_movements.notes', 'like', $term);
                });
            })
            ->when($request->filled('warehouse_id'), fn ($query) => $query->where('warehouses.id', $request->integer('warehouse_id')))
            ->when($request->filled('transaction_type'), fn ($query) => $query->where('stock_movements.transaction_type', $request->string('transaction_type')));

        $q = $this->dateFilter($q, $request, 'stock_movements.created_at');

        $paginator = $q->orderByDesc('stock_movements.created_at')->orderByDesc('stock_movements.id')->paginate($this->perPage($request))->withQueryString();
        $paginator->getCollection()->transform(function ($r): array {
            [$debit, $credit] = JournalService::mapping((string) $r->transaction_type);
            $qty = (int) $r->quantity_in > 0 ? (int) $r->quantity_in : (int) $r->quantity_out;

            return [
                'created_at' => $r->created_at,
                'reference' => $r->reference_type.' #'.$r->reference_id,
                'transaction_type' => $r->transaction_type,
                'sku' => $r->sku,
                'item_name' => $r->item_name,
                'warehouse_name' => $r->warehouse_name,
                'quantity' => $qty,
                'unit_cost' => (float) $r->unit_cost,
                'total_cost' => (float) $r->total_cost,
                'debit_account' => $debit,
                'credit_account' => $credit,
                'description' => $r->notes ?? '',
            ];
        });

        return $this->paginated($paginator);
    }

    public function accountingSummary(Request $request): JsonResponse
    {
        $from = $request->filled('date_from') ? Carbon::parse((string) $request->input('date_from'))->startOfDay() : null;
        $to = $request->filled('date_to') ? Carbon::parse((string) $request->input('date_to'))->endOfDay() : null;
        $warehouseId = $request->filled('warehouse_id') ? $request->integer('warehouse_id') : null;

        $rows = JournalService::journalRows($from, $to, $warehouseId);
        $totals = JournalService::totals($rows);

        return $this->ok([
            'period' => ['from' => $from?->toDateString(), 'to' => $to?->toDateString()],
            'totals' => $totals,
            'accounts' => JournalService::accounts(),
        ], 'OK');
    }

    public function aging(Request $request): JsonResponse
    {
        $rows = InventoryAnalyticsService::aging(
            $request->filled('warehouse_id') ? $request->integer('warehouse_id') : null,
            $request->filled('category_id') ? $request->integer('category_id') : null,
        )->values();

        $perPage = $this->perPage($request);
        $page = max(1, (int) $request->integer('page', 1));

        return $this->paginated(new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        ));
    }

    public function abc(Request $request): JsonResponse
    {
        $rows = InventoryAnalyticsService::abc(
            $request->filled('warehouse_id') ? $request->integer('warehouse_id') : null,
            $request->filled('date_from') ? (string) $request->string('date_from') : null,
            $request->filled('date_to') ? (string) $request->string('date_to') : null,
        )->values();

        $perPage = $this->perPage($request);
        $page = max(1, (int) $request->integer('page', 1));

        return $this->paginated(new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        ));
    }

    public function turnover(Request $request): JsonResponse
    {
        return $this->ok(InventoryAnalyticsService::turnover(
            $request->filled('warehouse_id') ? $request->integer('warehouse_id') : null,
            $request->filled('date_from') ? (string) $request->string('date_from') : null,
            $request->filled('date_to') ? (string) $request->string('date_to') : null,
        ), 'OK');
    }

    public function forecast(Request $request): JsonResponse
    {
        $data = InventoryForecastService::topNeeds(
            min(100, max(1, (int) $request->integer('limit', 20))),
            $request->filled('warehouse_id') ? $request->integer('warehouse_id') : null,
        )->values();

        $perPage = $this->perPage($request);
        $page = max(1, (int) $request->integer('page', 1));

        return $this->paginated(new LengthAwarePaginator(
            $data->forPage($page, $perPage)->values(),
            $data->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        ));
    }

    public function forecastItem(Request $request, int $itemId): JsonResponse
    {
        return $this->ok(InventoryForecastService::forecast(
            $itemId,
            30,
            $request->filled('warehouse_id') ? $request->integer('warehouse_id') : null,
        ), 'OK');
    }

    public function slowMoving(Request $request): JsonResponse
    {
        $rows = InventoryAnalyticsService::slowMoving(
            $request->filled('warehouse_id') ? $request->integer('warehouse_id') : null,
            $request->filled('days') ? $request->integer('days') : null,
        )->values();

        $perPage = $this->perPage($request);
        $page = max(1, (int) $request->integer('page', 1));

        return $this->paginated(new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        ));
    }

    public function replenishment(Request $request): JsonResponse
    {
        $rows = ReplenishmentService::suggestions(
            $request->filled('warehouse_id') ? $request->integer('warehouse_id') : null,
            $request->filled('search') ? (string) $request->string('search') : null,
            $request->filled('category_id') ? $request->integer('category_id') : null,
        )->values();

        $perPage = $this->perPage($request);
        $page = max(1, (int) $request->integer('page', 1));

        $paginator = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return $this->paginated($paginator);
    }
}
