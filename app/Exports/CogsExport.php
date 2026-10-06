<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class CogsExport implements FromCollection, WithHeadings
{
    public function __construct(protected array $filters = [])
    {
    }

    public function headings(): array
    {
        return ['Date', 'Reference', 'SKU', 'Item', 'Warehouse', 'Qty Out', 'Unit Cost', 'Total Cost'];
    }

    public function collection(): Collection
    {
        return $this->query()->get()->map(fn ($r) => [
            $r->created_at,
            $r->reference_type.' #'.$r->reference_id,
            $r->sku,
            $r->item_name,
            $r->warehouse_name,
            (int) $r->quantity_out,
            (float) $r->unit_cost,
            (float) $r->total_cost,
        ]);
    }

    protected function query()
    {
        $f = $this->filters;

        return DB::table('stock_movements')
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
                'warehouses.id as warehouse_id',
                'stock_movements.quantity_out',
                'stock_movements.unit_cost',
                'stock_movements.total_cost',
            ])
            ->when(! empty($f['search']), function ($q) use ($f): void {
                $term = '%'.$f['search'].'%';
                $q->where(function ($inner) use ($term): void {
                    $inner->where('items.sku', 'like', $term)
                        ->orWhere('items.barcode', 'like', $term)
                        ->orWhere('items.name', 'like', $term);
                });
            })
            ->when(! empty($f['warehouse']), fn ($q) => $q->where('warehouses.id', $f['warehouse']))
            ->when(! empty($f['fromDate']), fn ($q) => $q->whereDate('stock_movements.created_at', '>=', $f['fromDate']))
            ->when(! empty($f['toDate']), fn ($q) => $q->whereDate('stock_movements.created_at', '<=', $f['toDate']))
            ->orderBy('stock_movements.created_at', 'desc')
            ->orderBy('stock_movements.id', 'desc');
    }
}
