<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class OpnameExport implements FromCollection, WithHeadings
{
    public function __construct(protected array $filters = [])
    {
    }

    public function headings(): array
    {
        return ['Opname Number', 'Date', 'Warehouse', 'Location', 'SKU', 'Item', 'System Qty', 'Physical Qty', 'Difference', 'Reason', 'Status'];
    }

    public function collection(): Collection
    {
        return $this->query()->get()->map(fn ($r) => [
            $r->opname_number,
            $r->opname_date,
            $r->warehouse_name,
            $r->location_code ?? '-',
            $r->sku,
            $r->item_name,
            (int) $r->system_quantity,
            $r->physical_quantity !== null ? (int) $r->physical_quantity : '-',
            (int) $r->difference,
            $r->reason ?? '-',
            $r->status,
        ]);
    }

    protected function query()
    {
        $f = $this->filters;

        return DB::table('stock_opnames')
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
            ->when(! empty($f['search']), function ($q) use ($f): void {
                $term = '%'.$f['search'].'%';
                $q->where(function ($inner) use ($term): void {
                    $inner->where('stock_opnames.number', 'like', $term)
                        ->orWhere('items.sku', 'like', $term)
                        ->orWhere('items.name', 'like', $term);
                });
            })
            ->when(! empty($f['status']), fn ($q) => $q->where('stock_opnames.status', $f['status']))
            ->when(! empty($f['warehouse']), fn ($q) => $q->where('stock_opnames.warehouse_id', $f['warehouse']))
            ->when(! empty($f['from']), fn ($q) => $q->whereDate('stock_opnames.opname_date', '>=', $f['from']))
            ->when(! empty($f['to']), fn ($q) => $q->whereDate('stock_opnames.opname_date', '<=', $f['to']))
            ->orderBy('stock_opnames.opname_date', 'desc')
            ->orderBy('stock_opnames.number', 'desc');
    }
}
