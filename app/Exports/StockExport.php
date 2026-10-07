<?php

namespace App\Exports;

use App\Enums\StockStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class StockExport implements FromCollection, WithHeadings
{
    public function __construct(protected array $filters = []) {}

    public function headings(): array
    {
        return ['SKU', 'Item', 'Category', 'Warehouse', 'Location', 'On Hand', 'Min', 'Max', 'Status'];
    }

    public function collection(): Collection
    {
        $rows = $this->query()->get();

        return $rows->map(fn ($r) => [
            $r->sku,
            $r->item_name,
            $r->category_name,
            $r->warehouse_name,
            $r->location_path,
            (int) $r->quantity_on_hand,
            (int) $r->min_stock,
            (int) $r->max_stock,
            StockStatus::evaluate((int) $r->quantity_on_hand, (int) $r->min_stock, (int) $r->max_stock)->label(),
        ]);
    }

    protected function query()
    {
        $f = $this->filters;
        $q = DB::table('stock_balances')
            ->join('items', 'stock_balances.item_id', '=', 'items.id')
            ->join('warehouses', 'stock_balances.warehouse_id', '=', 'warehouses.id')
            ->join('locations', 'stock_balances.location_id', '=', 'locations.id')
            ->join('racks', 'locations.rack_id', '=', 'racks.id')
            ->join('zones', 'racks.zone_id', '=', 'zones.id')
            ->join('categories', 'items.category_id', '=', 'categories.id')
            ->select([
                'items.sku',
                'items.name as item_name',
                'items.minimum_stock as min_stock',
                'items.maximum_stock as max_stock',
                'stock_balances.quantity_on_hand',
                'categories.name as category_name',
                'warehouses.name as warehouse_name',
                DB::raw("CONCAT_WS(' / ', warehouses.name, zones.name, racks.name, locations.code) as location_path"),
                'warehouses.id as warehouse_id',
                'categories.id as category_id',
                'locations.id as location_id',
            ])
            ->when(! empty($f['search']), function ($query) use ($f): void {
                $term = '%'.$f['search'].'%';
                $query->where(function ($inner) use ($term): void {
                    $inner->where('items.sku', 'like', $term)
                        ->orWhere('items.name', 'like', $term)
                        ->orWhere('items.barcode', 'like', $term);
                });
            })
            ->when(! empty($f['warehouse']), fn ($query) => $query->where('warehouses.id', $f['warehouse']))
            ->when(! empty($f['category']), fn ($query) => $query->where('categories.id', $f['category']))
            ->when(! empty($f['location']), fn ($query) => $query->where('locations.id', $f['location']));

        if (! empty($f['status'])) {
            match ($f['status']) {
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

        $sort = $f['sortField'] ?? 'sku';
        $dir = $f['sortDirection'] ?? 'asc';
        $col = $sort === 'on_hand' ? 'stock_balances.quantity_on_hand' : 'items.sku';
        $q->orderBy($col, $dir === 'desc' ? 'desc' : 'asc');

        return $q;
    }
}
