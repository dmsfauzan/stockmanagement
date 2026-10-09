<?php

namespace App\Services\Inventory;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Location/BIN capacity & put-away guidance. Capacity is optional; a
 * location with null/0 capacity is treated as unlimited.
 */
class CapacityService
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public static function utilization(?int $warehouseId = null, bool $onlyConstrained = false): Collection
    {
        $rows = DB::table('locations')
            ->join('racks', 'locations.rack_id', '=', 'racks.id')
            ->join('zones', 'racks.zone_id', '=', 'zones.id')
            ->join('warehouses', 'zones.warehouse_id', '=', 'warehouses.id')
            ->forAccessibleWarehouses('warehouses.id')
            ->whereNull('locations.deleted_at')
            ->when($warehouseId !== null, fn ($q) => $q->where('warehouses.id', $warehouseId))
            ->whereNotNull('locations.capacity')
            ->where('locations.capacity', '>', 0)
            ->select([
                'locations.id',
                'locations.code',
                'locations.name',
                'locations.capacity',
                'warehouses.name as warehouse_name',
                DB::raw("CONCAT_WS(' / ', warehouses.name, zones.name, racks.name, locations.code) as location_path"),
                DB::raw('COALESCE((SELECT SUM(sb.quantity_on_hand) FROM stock_balances sb WHERE sb.location_id = locations.id), 0) as used'),
            ])
            ->orderBy('locations.code')
            ->get();

        return $rows->map(function ($row) {
            $capacity = (int) $row->capacity;
            $used = (int) $row->used;
            $pct = $capacity > 0 ? min(999, round($used / $capacity * 100, 1)) : 0.0;

            return [
                'id' => (int) $row->id,
                'code' => $row->code,
                'name' => $row->name,
                'warehouse_name' => $row->warehouse_name,
                'location_path' => $row->location_path,
                'capacity' => $capacity,
                'used' => $used,
                'free' => max(0, $capacity - $used),
                'percent' => (float) $pct,
                'status' => match (true) {
                    $used >= $capacity => 'full',
                    (float) $pct >= 80 => 'high',
                    default => 'normal',
                },
            ];
        })->when($onlyConstrained, fn (Collection $c) => $c->filter(fn ($r) => $r['percent'] >= 80))->values();
    }

    /**
     * Suggest the best location to put away a quantity of an item:
     * prefer a bin that already holds the item with enough free space,
     * otherwise the emptiest bin in the warehouse.
     *
     * @return array<string, mixed>|null
     */
    public static function suggestLocation(int $warehouseId, int $itemId, int $quantity): ?array
    {
        $candidates = static::warehouseLocations($warehouseId);

        if ($candidates->isEmpty()) {
            return null;
        }

        $existing = DB::table('stock_balances')
            ->where('item_id', $itemId)
            ->where('warehouse_id', $warehouseId)
            ->where('quantity_on_hand', '>', 0)
            ->pluck('location_id')
            ->all();

        $ranked = $candidates->map(function ($row) use ($existing) {
            $row['already_stocked'] = in_array($row['id'], $existing, true);

            return $row;
        })->sortByDesc(fn ($row) => [
            $row['already_stocked'] ? 1 : 0,
            $row['free'],
        ]);

        // Prefer stocked bins with enough room, else any with enough room, else the emptiest.
        $fit = $ranked->first(fn ($row) => $row['free'] >= $quantity);

        return $fit ?? $ranked->sortByDesc('free')->first();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private static function warehouseLocations(int $warehouseId): Collection
    {
        return DB::table('locations')
            ->join('racks', 'locations.rack_id', '=', 'racks.id')
            ->join('zones', 'racks.zone_id', '=', 'zones.id')
            ->whereNull('locations.deleted_at')
            ->where('zones.warehouse_id', $warehouseId)
            ->select([
                'locations.id',
                'locations.code',
                'locations.capacity',
                DB::raw('COALESCE((SELECT SUM(sb.quantity_on_hand) FROM stock_balances sb WHERE sb.location_id = locations.id), 0) as used'),
            ])
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'code' => $row->code,
                'capacity' => (int) ($row->capacity ?? 0),
                'used' => (int) $row->used,
                'free' => (int) ($row->capacity ?? 0) > 0 ? max(0, (int) $row->capacity - (int) $row->used) : PHP_INT_MAX,
            ]);
    }
}
