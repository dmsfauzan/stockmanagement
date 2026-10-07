<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class MovementExport implements FromCollection, WithHeadings
{
    public function __construct(protected array $filters = []) {}

    public function headings(): array
    {
        return ['Date', 'SKU', 'Item', 'Warehouse', 'Location', 'Type', 'Qty In', 'Qty Out', 'Balance', 'Unit Cost', 'Total Cost', 'User'];
    }

    public function collection(): Collection
    {
        return $this->query()->get()->map(fn ($r) => [
            $r->created_at,
            $r->sku,
            $r->item_name,
            $r->warehouse_name,
            $r->location_code ?? '-',
            $r->transaction_type,
            (int) $r->quantity_in,
            (int) $r->quantity_out,
            (int) $r->balance_after,
            (float) $r->unit_cost,
            (float) $r->total_cost,
            $r->user_name ?? '-',
        ]);
    }

    protected function query()
    {
        $f = $this->filters;

        return DB::table('stock_movements')
            ->leftJoin('items', 'stock_movements.item_id', '=', 'items.id')
            ->leftJoin('warehouses', 'stock_movements.warehouse_id', '=', 'warehouses.id')
            ->leftJoin('locations', 'stock_movements.location_id', '=', 'locations.id')
            ->leftJoin('users', 'stock_movements.created_by', '=', 'users.id')
            ->select([
                'stock_movements.created_at',
                'items.sku',
                'items.name as item_name',
                'warehouses.name as warehouse_name',
                'locations.code as location_code',
                'stock_movements.transaction_type',
                'stock_movements.quantity_in',
                'stock_movements.quantity_out',
                'stock_movements.balance_after',
                'stock_movements.unit_cost',
                'stock_movements.total_cost',
                'users.name as user_name',
            ])
            ->when(! empty($f['search']), function ($q) use ($f): void {
                $term = '%'.$f['search'].'%';
                $q->where(function ($inner) use ($term): void {
                    $inner->where('items.sku', 'like', $term)->orWhere('items.name', 'like', $term);
                });
            })
            ->when(! empty($f['warehouse']), fn ($q) => $q->where('stock_movements.warehouse_id', $f['warehouse']))
            ->when(! empty($f['item']), fn ($q) => $q->where('stock_movements.item_id', $f['item']))
            ->when(! empty($f['transaction_type']), fn ($q) => $q->where('stock_movements.transaction_type', $f['transaction_type']))
            ->when(! empty($f['user']), fn ($q) => $q->where('stock_movements.created_by', $f['user']))
            ->when(! empty($f['fromDate']), fn ($q) => $q->whereDate('stock_movements.created_at', '>=', $f['fromDate']))
            ->when(! empty($f['toDate']), fn ($q) => $q->whereDate('stock_movements.created_at', '<=', $f['toDate']))
            ->orderBy('stock_movements.created_at', 'desc')
            ->orderBy('stock_movements.id', 'desc');
    }
}
