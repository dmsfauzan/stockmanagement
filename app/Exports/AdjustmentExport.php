<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class AdjustmentExport implements FromCollection, WithHeadings
{
    public function __construct(protected array $filters = []) {}

    public function headings(): array
    {
        return ['Adjustment No', 'Date', 'Warehouse', 'Location', 'SKU', 'Item', 'System Qty', 'Actual Qty', 'Difference', 'Status'];
    }

    public function collection(): Collection
    {
        return $this->query()->get()->map(fn ($r) => [
            $r->adj_number,
            $r->transaction_date,
            $r->warehouse_name,
            $r->location_code ?? '-',
            $r->sku,
            $r->item_name,
            (int) $r->system_quantity,
            (int) $r->actual_quantity,
            (int) $r->difference,
            $r->status,
        ]);
    }

    protected function query()
    {
        $f = $this->filters;

        return DB::table('stock_adjustments')
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
            ->when(! empty($f['search']), function ($q) use ($f): void {
                $term = '%'.$f['search'].'%';
                $q->where(function ($inner) use ($term): void {
                    $inner->where('stock_adjustments.number', 'like', $term)
                        ->orWhere('stock_adjustments.reason', 'like', $term)
                        ->orWhere('items.sku', 'like', $term)
                        ->orWhere('items.name', 'like', $term);
                });
            })
            ->when(! empty($f['status']), fn ($q) => $q->where('stock_adjustments.status', $f['status']))
            ->when(! empty($f['warehouse']), fn ($q) => $q->where('stock_adjustments.warehouse_id', $f['warehouse']))
            ->when(! empty($f['from']), fn ($q) => $q->whereDate('stock_adjustments.transaction_date', '>=', $f['from']))
            ->when(! empty($f['to']), fn ($q) => $q->whereDate('stock_adjustments.transaction_date', '<=', $f['to']))
            ->orderBy('stock_adjustments.transaction_date', 'desc')
            ->orderBy('stock_adjustments.number', 'desc');
    }
}
