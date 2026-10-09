<?php

namespace App\Services\Inventory;

use App\Models\Setting;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ExpiryService
{
    public static function warnDays(): int
    {
        return (int) (Setting::get('expiry.warn_days', 30) ?? 30);
    }

    public static function criticalDays(): int
    {
        return (int) (Setting::get('expiry.critical_days', 7) ?? 7);
    }

    public static function baseQuery(): Builder
    {
        $driver = DB::connection()->getDriverName();
        $daysExpr = $driver === 'sqlite'
            ? "CAST(julianday(stock_movements.expiry_date) - julianday('now') AS INTEGER) as days_left"
            : 'DATEDIFF(stock_movements.expiry_date, CURDATE()) as days_left';

        return DB::table('stock_movements')
            ->join('items', 'stock_movements.item_id', '=', 'items.id')
            ->join('warehouses', 'stock_movements.warehouse_id', '=', 'warehouses.id')
            ->join('locations', 'stock_movements.location_id', '=', 'locations.id')
            ->forAccessibleWarehouses('stock_movements.warehouse_id')
            ->where('stock_movements.transaction_type', 'incoming')
            ->whereNotNull('stock_movements.expiry_date')
            ->select([
                'stock_movements.id',
                'stock_movements.batch_number',
                'stock_movements.expiry_date',
                'stock_movements.quantity_in',
                'stock_movements.reference_type',
                'stock_movements.reference_id',
                'items.sku',
                'items.name as item_name',
                'warehouses.name as warehouse_name',
                'locations.code as location_code',
                DB::raw($daysExpr),
            ]);
    }

    public static function rows(string $status = 'all', ?string $search = null, ?int $warehouseId = null): Builder
    {
        $q = static::baseQuery();
        $today = Carbon::today();
        $warn = static::warnDays();

        if ($status === 'expired') {
            $q->whereDate('stock_movements.expiry_date', '<', $today->toDateString());
        } elseif ($status === 'expiring_30') {
            $q->whereDate('stock_movements.expiry_date', '>=', $today->toDateString())
                ->whereDate('stock_movements.expiry_date', '<=', $today->copy()->addDays($warn)->toDateString());
        } elseif ($status === 'expiring_90') {
            $q->whereDate('stock_movements.expiry_date', '>', $today->copy()->addDays($warn)->toDateString())
                ->whereDate('stock_movements.expiry_date', '<=', $today->copy()->addDays(90)->toDateString());
        } elseif ($status === 'valid') {
            $q->whereDate('stock_movements.expiry_date', '>', $today->copy()->addDays(90)->toDateString());
        }

        if ($warehouseId !== null) {
            $q->where('stock_movements.warehouse_id', $warehouseId);
        }

        if ($search !== null && $search !== '') {
            $term = '%'.$search.'%';
            $q->where(function (Builder $inner) use ($term): void {
                $inner->where('items.sku', 'like', $term)
                    ->orWhere('items.name', 'like', $term)
                    ->orWhere('stock_movements.batch_number', 'like', $term)
                    ->orWhere('warehouses.name', 'like', $term)
                    ->orWhere('locations.code', 'like', $term);
            });
        }

        return $q->orderBy('stock_movements.expiry_date', 'asc')->orderBy('stock_movements.id', 'asc');
    }

    public static function counts(): array
    {
        $today = Carbon::today();
        $warn = static::warnDays();
        $warnDate = $today->copy()->addDays($warn)->toDateString();
        $day90 = $today->copy()->addDays(90)->toDateString();
        $todayStr = $today->toDateString();

        $row = DB::table('stock_movements')
            ->forAccessibleWarehouses('warehouse_id')
            ->where('transaction_type', 'incoming')
            ->whereNotNull('expiry_date')
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN expiry_date < ? THEN 1 ELSE 0 END),0) as expired, COALESCE(SUM(CASE WHEN expiry_date >= ? AND expiry_date <= ? THEN 1 ELSE 0 END),0) as expiring_30, COALESCE(SUM(CASE WHEN expiry_date > ? AND expiry_date <= ? THEN 1 ELSE 0 END),0) as expiring_90, COALESCE(SUM(CASE WHEN expiry_date > ? THEN 1 ELSE 0 END),0) as valid, COUNT(*) as total',
                [$todayStr, $todayStr, $warnDate, $warnDate, $day90, $day90]
            )
            ->first();

        return [
            'expired' => (int) ($row->expired ?? 0),
            'expiring_30' => (int) ($row->expiring_30 ?? 0),
            'expiring_90' => (int) ($row->expiring_90 ?? 0),
            'valid' => (int) ($row->valid ?? 0),
            'total' => (int) ($row->total ?? 0),
        ];
    }
}
