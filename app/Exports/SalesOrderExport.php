<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class SalesOrderExport implements FromCollection, WithHeadings
{
    public function __construct(protected array $filters = []) {}

    public function headings(): array
    {
        return ['SO Number', 'Date', 'Customer', 'Warehouse', 'SKU', 'Item', 'Quantity', 'Fulfilled', 'Remaining', 'Unit Price', 'Status'];
    }

    public function collection(): Collection
    {
        return $this->query()->get()->map(fn ($r) => [
            $r->so_number,
            $r->order_date,
            $r->customer_name,
            $r->warehouse_name,
            $r->sku,
            $r->item_name,
            (int) $r->quantity,
            (int) $r->fulfilled_quantity,
            max(0, (int) $r->quantity - (int) $r->fulfilled_quantity),
            $r->unit_price,
            $r->status,
        ]);
    }

    protected function query()
    {
        $f = $this->filters;

        return DB::table('sales_orders')
            ->join('sales_order_items', 'sales_order_items.sales_order_id', '=', 'sales_orders.id')
            ->join('items', 'items.id', '=', 'sales_order_items.item_id')
            ->join('customers', 'customers.id', '=', 'sales_orders.customer_id')
            ->join('warehouses', 'warehouses.id', '=', 'sales_orders.warehouse_id')
            ->select([
                'sales_orders.number as so_number',
                'sales_orders.order_date',
                'sales_orders.status',
                'customers.name as customer_name',
                'warehouses.name as warehouse_name',
                'items.sku',
                'items.name as item_name',
                'sales_order_items.quantity',
                'sales_order_items.fulfilled_quantity',
                'sales_order_items.unit_price',
            ])
            ->when(! empty($f['search']), function ($q) use ($f): void {
                $term = '%'.$f['search'].'%';
                $q->where(function ($inner) use ($term): void {
                    $inner->where('sales_orders.number', 'like', $term)
                        ->orWhere('customers.name', 'like', $term);
                });
            })
            ->when(! empty($f['status']), fn ($q) => $q->where('sales_orders.status', $f['status']))
            ->when(! empty($f['customer']), fn ($q) => $q->where('sales_orders.customer_id', $f['customer']))
            ->when(! empty($f['warehouse']), fn ($q) => $q->where('sales_orders.warehouse_id', $f['warehouse']))
            ->when(! empty($f['from']), fn ($q) => $q->whereDate('sales_orders.order_date', '>=', $f['from']))
            ->when(! empty($f['to']), fn ($q) => $q->whereDate('sales_orders.order_date', '<=', $f['to']))
            ->orderBy('sales_orders.order_date', 'desc')
            ->orderBy('sales_orders.number', 'desc');
    }
}
