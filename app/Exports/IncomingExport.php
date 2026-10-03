<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class IncomingExport implements FromCollection, WithHeadings
{
    public function __construct(protected array $filters = [])
    {
    }

    public function headings(): array
    {
        return ['Receipt No', 'Date', 'Supplier', 'Warehouse', 'SKU', 'Item', 'Qty', 'Batch', 'Expiry', 'Posted At'];
    }

    public function collection(): Collection
    {
        return $this->query()->get()->map(fn ($r) => [
            $r->receipt_number,
            $r->transaction_date,
            $r->supplier_name,
            $r->warehouse_name,
            $r->sku,
            $r->item_name,
            (int) $r->qty,
            $r->batch_number ?? '-',
            $r->expiry_date ?? '-',
            $r->posted_at ?? '-',
        ]);
    }

    protected function query()
    {
        $f = $this->filters;

        return DB::table('goods_receipt_items')
            ->join('goods_receipts', 'goods_receipt_items.goods_receipt_id', '=', 'goods_receipts.id')
            ->join('items', 'goods_receipt_items.item_id', '=', 'items.id')
            ->leftJoin('suppliers', 'goods_receipts.supplier_id', '=', 'suppliers.id')
            ->join('warehouses', 'goods_receipts.warehouse_id', '=', 'warehouses.id')
            ->where('goods_receipts.status', 'posted')
            ->select([
                'goods_receipts.number as receipt_number',
                'goods_receipts.transaction_date',
                'suppliers.name as supplier_name',
                'warehouses.name as warehouse_name',
                'items.sku',
                'items.name as item_name',
                'goods_receipt_items.quantity as qty',
                'goods_receipt_items.batch_number',
                'goods_receipt_items.expiry_date',
                'goods_receipts.posted_at',
            ])
            ->when(! empty($f['search']), function ($q) use ($f): void {
                $term = '%'.$f['search'].'%';
                $q->where(function ($inner) use ($term): void {
                    $inner->where('goods_receipts.number', 'like', $term)
                        ->orWhere('items.sku', 'like', $term)
                        ->orWhere('items.name', 'like', $term);
                });
            })
            ->when(! empty($f['supplier']), fn ($q) => $q->where('goods_receipts.supplier_id', $f['supplier']))
            ->when(! empty($f['warehouse']), fn ($q) => $q->where('goods_receipts.warehouse_id', $f['warehouse']))
            ->when(! empty($f['fromDate']), fn ($q) => $q->whereDate('goods_receipts.transaction_date', '>=', $f['fromDate']))
            ->when(! empty($f['toDate']), fn ($q) => $q->whereDate('goods_receipts.transaction_date', '<=', $f['toDate']))
            ->orderBy('goods_receipts.transaction_date', 'desc')
            ->orderBy('goods_receipts.id', 'desc');
    }
}
