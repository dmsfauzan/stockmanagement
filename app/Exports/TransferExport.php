<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class TransferExport implements FromCollection, WithHeadings
{
    public function __construct(protected array $filters = []) {}

    public function headings(): array
    {
        return ['Transfer No', 'Date', 'From (Wh/Loc)', 'To (Wh/Loc)', 'SKU', 'Item', 'Qty', 'Status'];
    }

    public function collection(): Collection
    {
        return $this->query()->get()->map(fn ($r) => [
            $r->tr_number,
            $r->transfer_date,
            $r->from_warehouse.($r->from_location ? ' / '.$r->from_location : ''),
            $r->to_warehouse.($r->to_location ? ' / '.$r->to_location : ''),
            $r->sku,
            $r->item_name,
            (int) $r->quantity,
            $r->status,
        ]);
    }

    protected function query()
    {
        $f = $this->filters;

        return DB::table('stock_transfers')
            ->join('stock_transfer_items', 'stock_transfer_items.stock_transfer_id', '=', 'stock_transfers.id')
            ->join('items', 'items.id', '=', 'stock_transfer_items.item_id')
            ->join('warehouses as fw', 'fw.id', '=', 'stock_transfers.from_warehouse_id')
            ->join('warehouses as tw', 'tw.id', '=', 'stock_transfers.to_warehouse_id')
            ->leftJoin('locations as fl', 'fl.id', '=', 'stock_transfers.from_location_id')
            ->leftJoin('locations as tl', 'tl.id', '=', 'stock_transfers.to_location_id')
            ->select([
                'stock_transfers.number as tr_number',
                'stock_transfers.transfer_date',
                'stock_transfers.status',
                'fw.name as from_warehouse',
                'fl.code as from_location',
                'tw.name as to_warehouse',
                'tl.code as to_location',
                'items.sku',
                'items.name as item_name',
                'stock_transfer_items.quantity',
            ])
            ->when(! empty($f['search']), function ($q) use ($f): void {
                $term = '%'.$f['search'].'%';
                $q->where(function ($inner) use ($term): void {
                    $inner->where('stock_transfers.number', 'like', $term)
                        ->orWhere('items.sku', 'like', $term)
                        ->orWhere('items.name', 'like', $term);
                });
            })
            ->when(! empty($f['status']), fn ($q) => $q->where('stock_transfers.status', $f['status']))
            ->when(! empty($f['from_warehouse']), fn ($q) => $q->where('stock_transfers.from_warehouse_id', $f['from_warehouse']))
            ->when(! empty($f['to_warehouse']), fn ($q) => $q->where('stock_transfers.to_warehouse_id', $f['to_warehouse']))
            ->when(! empty($f['from']), fn ($q) => $q->whereDate('stock_transfers.transfer_date', '>=', $f['from']))
            ->when(! empty($f['to']), fn ($q) => $q->whereDate('stock_transfers.transfer_date', '<=', $f['to']))
            ->orderBy('stock_transfers.transfer_date', 'desc')
            ->orderBy('stock_transfers.number', 'desc');
    }
}
