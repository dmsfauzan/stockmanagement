<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class OutgoingExport implements FromCollection, WithHeadings
{
    public function __construct(protected array $filters = [])
    {
    }

    public function headings(): array
    {
        return ['Issue No', 'Date', 'Customer', 'Warehouse', 'SKU', 'Item', 'Qty', 'Posted At'];
    }

    public function collection(): Collection
    {
        return $this->query()->get()->map(fn ($r) => [
            $r->issue_number,
            $r->transaction_date,
            $r->customer_name ?? $r->destination ?? '-',
            $r->warehouse_name,
            $r->sku,
            $r->item_name,
            (int) $r->qty,
            $r->posted_at ?? '-',
        ]);
    }

    protected function query()
    {
        $f = $this->filters;

        return DB::table('goods_issue_items')
            ->join('goods_issues', 'goods_issue_items.goods_issue_id', '=', 'goods_issues.id')
            ->join('items', 'goods_issue_items.item_id', '=', 'items.id')
            ->leftJoin('customers', 'goods_issues.customer_id', '=', 'customers.id')
            ->join('warehouses', 'goods_issues.warehouse_id', '=', 'warehouses.id')
            ->where('goods_issues.status', 'posted')
            ->select([
                'goods_issues.number as issue_number',
                'goods_issues.transaction_date',
                'customers.name as customer_name',
                'goods_issues.destination',
                'warehouses.name as warehouse_name',
                'items.sku',
                'items.name as item_name',
                'goods_issue_items.quantity as qty',
                'goods_issues.posted_at',
            ])
            ->when(! empty($f['search']), function ($q) use ($f): void {
                $term = '%'.$f['search'].'%';
                $q->where(function ($inner) use ($term): void {
                    $inner->where('goods_issues.number', 'like', $term)
                        ->orWhere('items.sku', 'like', $term)
                        ->orWhere('items.name', 'like', $term);
                });
            })
            ->when(! empty($f['customer']), fn ($q) => $q->where('goods_issues.customer_id', $f['customer']))
            ->when(! empty($f['warehouse']), fn ($q) => $q->where('goods_issues.warehouse_id', $f['warehouse']))
            ->when(! empty($f['fromDate']), fn ($q) => $q->whereDate('goods_issues.transaction_date', '>=', $f['fromDate']))
            ->when(! empty($f['toDate']), fn ($q) => $q->whereDate('goods_issues.transaction_date', '<=', $f['toDate']))
            ->orderBy('goods_issues.transaction_date', 'desc')
            ->orderBy('goods_issues.id', 'desc');
    }
}
