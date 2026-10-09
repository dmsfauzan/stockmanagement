<?php

namespace App\Services\Inventory;

use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Inventory analytics: aging, ABC classification, turnover and
 * slow/dead stock. All calculations are portable (no DB-specific date
 * functions) so they behave identically on MySQL and SQLite.
 */
class InventoryAnalyticsService
{
    public static function slowDays(): int
    {
        return (int) (Setting::get('inventory.slow_days', 60) ?? 60);
    }

    public static function deadDays(): int
    {
        return (int) (Setting::get('inventory.dead_days', 180) ?? 180);
    }

    /**
     * Stock aging buckets based on days since the last receipt.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function aging(?int $warehouseId = null, ?int $categoryId = null): Collection
    {
        $balances = DB::table('stock_balances')
            ->forAccessibleWarehouses('stock_balances.warehouse_id')
            ->join('items', 'stock_balances.item_id', '=', 'items.id')
            ->join('warehouses', 'stock_balances.warehouse_id', '=', 'warehouses.id')
            ->whereNull('items.deleted_at')
            ->where('stock_balances.quantity_on_hand', '>', 0)
            ->when($warehouseId !== null, fn ($q) => $q->where('stock_balances.warehouse_id', $warehouseId))
            ->when($categoryId !== null, fn ($q) => $q->where('items.category_id', $categoryId))
            ->groupBy('stock_balances.item_id', 'stock_balances.warehouse_id', 'items.sku', 'items.name', 'warehouses.name')
            ->select([
                'stock_balances.item_id',
                'stock_balances.warehouse_id',
                'items.sku',
                'items.name as item_name',
                'warehouses.name as warehouse_name',
                DB::raw('SUM(stock_balances.quantity_on_hand) as quantity'),
            ])
            ->get();

        $lastIn = DB::table('stock_movements')
            ->where('transaction_type', 'incoming')
            ->whereIn('item_id', $balances->pluck('item_id')->unique()->all() ?: [0])
            ->groupBy('item_id', 'warehouse_id')
            ->selectRaw('item_id, warehouse_id, MAX(created_at) as last_in')
            ->get()
            ->keyBy(fn ($row) => $row->item_id.'-'.$row->warehouse_id);

        $values = DB::table('inventory_valuations')
            ->selectRaw('item_id, warehouse_id, COALESCE(total_value,0) as total_value')
            ->get()
            ->keyBy(fn ($row) => $row->item_id.'-'.$row->warehouse_id);

        $today = Carbon::today();

        return $balances->map(function ($row) use ($lastIn, $values, $today) {
            $key = $row->item_id.'-'.$row->warehouse_id;
            $lastInAt = $lastIn[$key]->last_in ?? null;
            $age = $lastInAt ? (int) Carbon::parse($lastInAt)->diffInDays($today) : null;

            return [
                'item_id' => (int) $row->item_id,
                'warehouse_id' => (int) $row->warehouse_id,
                'sku' => $row->sku,
                'item_name' => $row->item_name,
                'warehouse_name' => $row->warehouse_name,
                'quantity' => (int) $row->quantity,
                'value' => (float) ($values[$key]->total_value ?? 0),
                'last_in' => $lastInAt,
                'age_days' => $age,
                'bucket' => static::ageBucket($age),
            ];
        })->sortByDesc('age_days')->values();
    }

    public static function ageBucket(?int $age): string
    {
        return match (true) {
            $age === null => 'no_receipt',
            $age <= 30 => '0_30',
            $age <= 60 => '31_60',
            $age <= 90 => '61_90',
            default => '90_plus',
        };
    }

    /**
     * ABC classification by usage value (COGS) over a period.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function abc(?int $warehouseId = null, ?string $from = null, ?string $to = null): Collection
    {
        $from = $from ?: Carbon::today()->subMonths(6)->toDateString();
        $to = $to ?: Carbon::today()->toDateString();

        $usage = DB::table('stock_movements')
            ->forAccessibleWarehouses('stock_movements.warehouse_id')
            ->join('items', 'stock_movements.item_id', '=', 'items.id')
            ->where('stock_movements.transaction_type', 'outgoing')
            ->whereDate('stock_movements.created_at', '>=', $from)
            ->whereDate('stock_movements.created_at', '<=', $to)
            ->when($warehouseId !== null, fn ($q) => $q->where('stock_movements.warehouse_id', $warehouseId))
            ->groupBy('items.id', 'items.sku', 'items.name')
            ->selectRaw('items.id as item_id, items.sku, items.name as item_name, COALESCE(SUM(stock_movements.total_cost),0) as usage_value, COALESCE(SUM(stock_movements.quantity_out),0) as usage_qty')
            ->orderByDesc('usage_value')
            ->get();

        $total = (float) $usage->sum('usage_value');
        $cumulative = 0.0;

        return $usage->map(function ($row) use (&$cumulative, $total) {
            $value = (float) $row->usage_value;
            $cumulative += $value;
            $share = $total > 0 ? ($value / $total) * 100 : 0;
            $cumShare = $total > 0 ? ($cumulative / $total) * 100 : 0;

            return [
                'item_id' => (int) $row->item_id,
                'sku' => $row->sku,
                'item_name' => $row->item_name,
                'usage_qty' => (int) $row->usage_qty,
                'usage_value' => $value,
                'share' => round($share, 2),
                'cumulative_share' => round($cumShare, 2),
                'class' => static::abcClass($cumShare),
            ];
        })->values();
    }

    public static function abcClass(float $cumulativeShare): string
    {
        return match (true) {
            $cumulativeShare <= 80 => 'A',
            $cumulativeShare <= 95 => 'B',
            default => 'C',
        };
    }

    /**
     * Inventory turnover for a period (COGS / average inventory value).
     *
     * @return array<string, mixed>
     */
    public static function turnover(?int $warehouseId = null, ?string $from = null, ?string $to = null): array
    {
        $from = $from ?: Carbon::today()->startOfMonth()->toDateString();
        $to = $to ?: Carbon::today()->toDateString();
        $days = max(1, Carbon::parse($from)->diffInDays(Carbon::parse($to)) + 1);

        $base = fn () => DB::table('stock_movements')
            ->forAccessibleWarehouses('stock_movements.warehouse_id')
            ->when($warehouseId !== null, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to);

        $cogs = (float) $base()->where('transaction_type', 'outgoing')->sum('total_cost');
        $incomingValue = (float) $base()->where('transaction_type', 'incoming')->sum('total_cost');

        $closing = (float) DB::table('inventory_valuations')
            ->forAccessibleWarehouses('inventory_valuations.warehouse_id')
            ->when($warehouseId !== null, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->sum('total_value');

        $opening = max(0.0, $closing - $incomingValue + $cogs);
        $avg = ($opening + $closing) / 2;

        $turnover = $avg > 0 ? $cogs / $avg : 0.0;
        $daysOfSupply = $cogs > 0 ? $closing / ($cogs / $days) : null;

        return [
            'from' => $from,
            'to' => $to,
            'days' => $days,
            'cogs' => $cogs,
            'incoming_value' => $incomingValue,
            'opening_value' => $opening,
            'closing_value' => $closing,
            'average_value' => $avg,
            'turnover' => round($turnover, 2),
            'days_of_supply' => $daysOfSupply !== null ? round($daysOfSupply, 1) : null,
        ];
    }

    /**
     * Non-moving stock: on-hand quantity but no outgoing for N days.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function slowMoving(?int $warehouseId = null, ?int $days = null): Collection
    {
        $days = $days ?? static::slowDays();

        return static::nonMoving($warehouseId, $days, 'slow');
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function deadStock(?int $warehouseId = null, ?int $days = null): Collection
    {
        $days = $days ?? static::deadDays();

        return static::nonMoving($warehouseId, $days, 'dead');
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private static function nonMoving(?int $warehouseId, int $days, string $type): Collection
    {
        $cutoff = Carbon::today()->subDays($days);

        $balances = DB::table('stock_balances')
            ->forAccessibleWarehouses('stock_balances.warehouse_id')
            ->join('items', 'stock_balances.item_id', '=', 'items.id')
            ->join('warehouses', 'stock_balances.warehouse_id', '=', 'warehouses.id')
            ->whereNull('items.deleted_at')
            ->where('stock_balances.quantity_on_hand', '>', 0)
            ->when($warehouseId !== null, fn ($q) => $q->where('stock_balances.warehouse_id', $warehouseId))
            ->groupBy('stock_balances.item_id', 'items.sku', 'items.name', 'warehouses.name')
            ->select([
                'stock_balances.item_id',
                'items.sku',
                'items.name as item_name',
                'warehouses.name as warehouse_name',
                DB::raw('SUM(stock_balances.quantity_on_hand) as quantity'),
            ])
            ->get();

        $lastOut = DB::table('stock_movements')
            ->where('transaction_type', 'outgoing')
            ->whereIn('item_id', $balances->pluck('item_id')->unique()->all() ?: [0])
            ->groupBy('item_id')
            ->selectRaw('item_id, MAX(created_at) as last_out')
            ->get()
            ->keyBy('item_id');

        return $balances->map(function ($row) use ($lastOut) {
            $lastOutAt = $lastOut[$row->item_id]->last_out ?? null;
            $idle = $lastOutAt ? (int) Carbon::parse($lastOutAt)->diffInDays(Carbon::today()) : null;

            return [
                'item_id' => (int) $row->item_id,
                'sku' => $row->sku,
                'item_name' => $row->item_name,
                'warehouse_name' => $row->warehouse_name,
                'quantity' => (int) $row->quantity,
                'last_out' => $lastOutAt,
                'idle_days' => $idle,
                'never_issued' => $lastOutAt === null,
            ];
        })->filter(function ($row) use ($cutoff) {
            if ($row['last_out'] === null) {
                return true;
            }

            return Carbon::parse($row['last_out'])->lt($cutoff);
        })->sortByDesc('idle_days')->values();
    }
}
