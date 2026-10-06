<?php

namespace App\Exports;

use App\Services\Accounting\JournalService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class JournalExport implements FromCollection, WithHeadings
{
    public function __construct(protected array $filters = [])
    {
    }

    public function headings(): array
    {
        return ['Date', 'Reference', 'Type', 'SKU', 'Item', 'Warehouse', 'Qty', 'Unit Cost', 'Total', 'Debit Account', 'Credit Account', 'Description'];
    }

    public function collection(): Collection
    {
        return $this->query()->get()->map(function ($r): array {
            [$debit, $credit] = JournalService::mapping((string) $r->transaction_type);
            $qty = (int) $r->quantity_in > 0 ? (int) $r->quantity_in : (int) $r->quantity_out;

            return [
                $r->created_at,
                $r->reference_type.' #'.$r->reference_id,
                $r->transaction_type,
                $r->sku,
                $r->item_name,
                $r->warehouse_name,
                $qty,
                (float) $r->unit_cost,
                (float) $r->total_cost,
                $debit,
                $credit,
                $r->notes ?? '',
            ];
        });
    }

    protected function query()
    {
        $f = $this->filters;

        return DB::table('stock_movements')
            ->join('items', 'stock_movements.item_id', '=', 'items.id')
            ->join('warehouses', 'stock_movements.warehouse_id', '=', 'warehouses.id')
            ->whereNull('items.deleted_at')
            ->select([
                'stock_movements.created_at',
                'stock_movements.reference_type',
                'stock_movements.reference_id',
                'stock_movements.transaction_type',
                'stock_movements.quantity_in',
                'stock_movements.quantity_out',
                'stock_movements.unit_cost',
                'stock_movements.total_cost',
                'stock_movements.notes',
                'items.sku',
                'items.name as item_name',
                'warehouses.name as warehouse_name',
                'warehouses.id as warehouse_id',
            ])
            ->when(! empty($f['search']), function ($query) use ($f): void {
                $term = '%'.$f['search'].'%';
                $query->where(function ($inner) use ($term): void {
                    $inner->where('items.sku', 'like', $term)
                        ->orWhere('items.name', 'like', $term)
                        ->orWhere('stock_movements.reference_type', 'like', $term)
                        ->orWhere('stock_movements.notes', 'like', $term);
                });
            })
            ->when(! empty($f['transactionType']), fn ($query) => $query->where('stock_movements.transaction_type', $f['transactionType']))
            ->when(! empty($f['warehouse']), fn ($query) => $query->where('warehouses.id', $f['warehouse']))
            ->when(! empty($f['fromDate']), fn ($query) => $query->whereDate('stock_movements.created_at', '>=', $f['fromDate']))
            ->when(! empty($f['toDate']), fn ($query) => $query->whereDate('stock_movements.created_at', '<=', $f['toDate']))
            ->orderBy('stock_movements.created_at', 'desc')
            ->orderByDesc('stock_movements.id');
    }
}
