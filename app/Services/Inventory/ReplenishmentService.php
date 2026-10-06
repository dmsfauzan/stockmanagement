<?php

namespace App\Services\Inventory;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReplenishmentService
{
    public static function suggestions(?int $warehouseId = null, ?string $search = null, ?int $categoryId = null): Collection
    {
        $q = DB::table('stock_balances')
            ->join('items', 'stock_balances.item_id', '=', 'items.id')
            ->join('categories', 'items.category_id', '=', 'categories.id')
            ->join('warehouses', 'stock_balances.warehouse_id', '=', 'warehouses.id')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'items.primary_supplier_id')
            ->leftJoin('supplier_item_prices as sip', function ($join): void {
                $join->on('sip.supplier_id', '=', 'items.primary_supplier_id')
                    ->on('sip.item_id', '=', 'items.id');
            })
            ->whereNull('items.deleted_at')
            ->where('items.status', 'active')
            ->when($warehouseId !== null, fn ($query) => $query->where('stock_balances.warehouse_id', $warehouseId))
            ->when($categoryId !== null, fn ($query) => $query->where('categories.id', $categoryId))
            ->when($search !== null && $search !== '', function ($query) use ($search): void {
                $term = '%'.$search.'%';
                $query->where(function ($inner) use ($term): void {
                    $inner->where('items.sku', 'like', $term)
                        ->orWhere('items.barcode', 'like', $term)
                        ->orWhere('items.name', 'like', $term);
                });
            })
            ->groupBy(
                'items.id',
                'items.sku',
                'items.name',
                'items.minimum_stock',
                'items.maximum_stock',
                'items.cost',
                'items.unit_id',
                'items.primary_supplier_id',
                'categories.id',
                'categories.name',
                'warehouses.id',
                'warehouses.name',
                'suppliers.id',
                'suppliers.name',
                'sip.price'
            )
            ->select([
                'items.id as item_id',
                'items.sku',
                'items.name as item_name',
                'items.minimum_stock as min_stock',
                'items.maximum_stock as max_stock',
                'items.cost',
                'items.unit_id',
                'items.primary_supplier_id',
                'categories.id as category_id',
                'categories.name as category_name',
                'warehouses.id as warehouse_id',
                'warehouses.name as warehouse_name',
                'suppliers.name as primary_supplier_name',
                DB::raw('COALESCE(sip.price, items.cost, 0) as best_price'),
                DB::raw('SUM(stock_balances.quantity_on_hand) as on_hand'),
            ])
            ->havingRaw('SUM(stock_balances.quantity_on_hand) <= items.minimum_stock');

        $rows = $q->get()->map(function ($row) {
            $onHand = (int) $row->on_hand;
            $min = (int) $row->min_stock;
            $max = (int) $row->max_stock;
            $suggested = $max > 0 ? $max - $onHand : ($min > 0 ? $min * 2 - $onHand : 0);
            $suggested = max(0, $suggested);
            $coverage = $min > 0 ? $onHand / max(1, $min) : ($onHand <= 0 ? 0 : 1);

            return [
                'item_id' => (int) $row->item_id,
                'sku' => $row->sku,
                'item_name' => $row->item_name,
                'category_id' => (int) $row->category_id,
                'category_name' => $row->category_name,
                'warehouse_id' => (int) $row->warehouse_id,
                'warehouse_name' => $row->warehouse_name,
                'on_hand' => $onHand,
                'min_stock' => $min,
                'max_stock' => $max,
                'suggestedQty' => $suggested,
                'primary_supplier_id' => $row->primary_supplier_id ? (int) $row->primary_supplier_id : null,
                'primary_supplier_name' => $row->primary_supplier_name,
                'best_price' => $row->best_price,
                'unit_id' => $row->unit_id ? (int) $row->unit_id : null,
                'cost' => $row->cost,
                'coverage' => $coverage,
            ];
        });

        return $rows->sortBy('coverage')->values();
    }
}
