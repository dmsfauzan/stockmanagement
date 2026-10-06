<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ValuationExport implements FromCollection, WithHeadings
{
    public function __construct(protected array $filters = [])
    {
    }

    public function headings(): array
    {
        return ['SKU', 'Item', 'Category', 'Warehouse', 'Qty', 'Average Cost', 'Total Value'];
    }

    public function collection(): Collection
    {
        return $this->query()->get()->map(fn ($r) => [
            $r->sku,
            $r->item_name,
            $r->category_name,
            $r->warehouse_name,
            (int) $r->quantity,
            (float) $r->average_cost,
            (float) $r->total_value,
        ]);
    }

    protected function query()
    {
        $f = $this->filters;

        return DB::table('inventory_valuations')
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
                'warehouses.id as warehouse_id',
                'categories.id as category_id',
            ])
            ->when(! empty($f['search']), function ($query) use ($f): void {
                $term = '%'.$f['search'].'%';
                $query->where(function ($inner) use ($term): void {
                    $inner->where('items.sku', 'like', $term)
                        ->orWhere('items.barcode', 'like', $term)
                        ->orWhere('items.name', 'like', $term);
                });
            })
            ->when(! empty($f['warehouse']), fn ($query) => $query->where('warehouses.id', $f['warehouse']))
            ->when(! empty($f['category']), fn ($query) => $query->where('categories.id', $f['category']))
            ->orderBy('items.sku');
    }
}
