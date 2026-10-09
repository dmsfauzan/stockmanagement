<?php

namespace App\Services\Inventory;

use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Demand forecasting from the ledger: daily outgoing history per item,
 * simple moving-average forecast with a trend adjustment, plus safety
 * stock (service-level z-score) and reorder point. Pure-PHP math so it
 * behaves identically on MySQL and SQLite.
 */
class InventoryForecastService
{
    public static function serviceLevel(): float
    {
        return (float) (Setting::get('forecast.service_level', 0.95) ?? 0.95);
    }

    public static function horizonDays(): int
    {
        return (int) (Setting::get('forecast.horizon_days', 30) ?? 30);
    }

    public static function leadTimeDaysDefault(): int
    {
        return (int) (Setting::get('forecast.lead_time_days', 7) ?? 7);
    }

    public static function zScore(float $serviceLevel): float
    {
        return match (true) {
            $serviceLevel >= 0.99 => 2.33,
            $serviceLevel >= 0.975 => 1.96,
            $serviceLevel >= 0.95 => 1.65,
            $serviceLevel >= 0.90 => 1.28,
            default => 1.04,
        };
    }

    /**
     * Daily outgoing quantities for an item over the last N days.
     *
     * @return array<int, float> keyed by days-ago (0 = today)
     */
    public static function dailyDemand(int $itemId, int $days = 30, ?int $warehouseId = null): array
    {
        $since = Carbon::today()->subDays($days - 1)->startOfDay();

        $rows = DB::table('stock_movements')
            ->forAccessibleWarehouses('stock_movements.warehouse_id')
            ->where('item_id', $itemId)
            ->where('transaction_type', 'outgoing')
            ->where('created_at', '>=', $since)
            ->when($warehouseId !== null, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->selectRaw('DATE(created_at) as day, SUM(quantity_out) as qty')
            ->groupBy('day')
            ->pluck('qty', 'day')
            ->all();

        $history = [];

        for ($ago = 0; $ago < $days; $ago++) {
            $day = Carbon::today()->subDays($ago)->toDateString();
            $history[$ago] = (float) ($rows[$day] ?? 0);
        }

        return $history;
    }

    /**
     * @return array<string, mixed>
     */
    public static function forecast(int $itemId, int $days = 30, ?int $warehouseId = null, ?int $horizon = null, ?float $serviceLevel = null, ?int $leadTimeDays = null): array
    {
        $history = static::dailyDemand($itemId, $days, $warehouseId);
        $n = max(1, count($history));

        $mean = array_sum($history) / $n;

        $variance = 0.0;
        foreach ($history as $value) {
            $variance += ($value - $mean) * ($value - $mean);
        }
        $std = $n > 1 ? sqrt($variance / ($n - 1)) : 0.0;

        $half = (int) ceil($n / 2);
        $first = array_sum(array_slice($history, $half)) / max(1, count(array_slice($history, $half)));
        $second = array_sum(array_slice($history, 0, $half)) / max(1, count(array_slice($history, 0, $half)));
        $trend = ($first - $second) / max(1, $half);

        $horizon = $horizon ?? static::horizonDays();
        $serviceLevel = $serviceLevel ?? static::serviceLevel();
        $leadTimeDays = $leadTimeDays ?? static::leadTimeDaysDefault();

        $projected = [];
        for ($day = 1; $day <= $horizon; $day++) {
            $projected[$day] = max(0.0, round($mean + $trend * $day, 2));
        }

        $expectedTotal = array_sum($projected);
        $safety = static::zScore($serviceLevel) * $std * sqrt(max(1, $leadTimeDays));
        $reorderPoint = ($mean * $leadTimeDays) + $safety;

        return [
            'item_id' => $itemId,
            'history_days' => $days,
            'horizon_days' => $horizon,
            'daily_mean' => round($mean, 2),
            'daily_std' => round($std, 2),
            'daily_trend' => round($trend, 4),
            'projected' => $projected,
            'expected_total' => round($expectedTotal, 2),
            'safety_stock' => (int) ceil($safety),
            'reorder_point' => (int) ceil($reorderPoint),
            'service_level' => $serviceLevel,
            'lead_time_days' => $leadTimeDays,
        ];
    }

    /**
     * Top-N items by expected demand with on-hand cover assessment.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function topNeeds(int $limit = 20, ?int $warehouseId = null): Collection
    {
        $since = Carbon::today()->subDays(29)->startOfDay();

        $usage = DB::table('stock_movements')
            ->forAccessibleWarehouses('stock_movements.warehouse_id')
            ->join('items', 'stock_movements.item_id', '=', 'items.id')
            ->where('stock_movements.transaction_type', 'outgoing')
            ->where('stock_movements.created_at', '>=', $since)
            ->when($warehouseId !== null, fn ($q) => $q->where('stock_movements.warehouse_id', $warehouseId))
            ->groupBy('stock_movements.item_id', 'items.sku', 'items.name', 'items.minimum_stock')
            ->selectRaw('stock_movements.item_id, items.sku, items.name as item_name, items.minimum_stock, SUM(stock_movements.quantity_out) as usage_30')
            ->orderByDesc('usage_30')
            ->limit($limit * 2)
            ->get();

        $balances = DB::table('stock_balances')
            ->forAccessibleWarehouses('stock_balances.warehouse_id')
            ->whereIn('item_id', $usage->pluck('item_id')->all() ?: [0])
            ->groupBy('item_id')
            ->selectRaw('item_id, SUM(quantity_on_hand) as on_hand')
            ->pluck('on_hand', 'item_id');

        return $usage->map(function ($row) use ($balances) {
            $daily = (float) $row->usage_30 / 30;
            $onHand = (int) ($balances[$row->item_id] ?? 0);
            $daysCover = $daily > 0 ? $onHand / $daily : null;

            return [
                'item_id' => (int) $row->item_id,
                'sku' => $row->sku,
                'item_name' => $row->item_name,
                'on_hand' => $onHand,
                'usage_30' => (int) $row->usage_30,
                'daily_mean' => round($daily, 2),
                'days_cover' => $daysCover !== null ? round($daysCover, 1) : null,
                'below_min' => $onHand <= (int) $row->minimum_stock,
            ];
        })->sortBy(fn ($row) => $row['days_cover'] ?? 999999)->take($limit)->values();
    }
}
