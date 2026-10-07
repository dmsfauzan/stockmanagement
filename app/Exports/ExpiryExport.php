<?php

namespace App\Exports;

use App\Services\Inventory\ExpiryService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ExpiryExport implements FromCollection, WithHeadings
{
    public function __construct(protected array $filters = []) {}

    public function headings(): array
    {
        return ['SKU', 'Item', 'Warehouse', 'Location', 'Batch', 'Expiry Date', 'Days Left', 'Qty Received', 'Reference'];
    }

    public function collection(): Collection
    {
        return $this->query()->get()->map(fn ($r) => [
            $r->sku,
            $r->item_name,
            $r->warehouse_name,
            $r->location_code ?? '-',
            $r->batch_number ?? '-',
            $r->expiry_date,
            (int) ($r->days_left ?? 0),
            (int) $r->quantity_in,
            trim(($r->reference_type ?? '').' #'.($r->reference_id ?? '-')),
        ]);
    }

    protected function query()
    {
        $f = $this->filters;
        $q = ExpiryService::rows(
            $f['status'] ?? 'all',
            $f['search'] ?? null,
            ! empty($f['warehouse']) ? (int) $f['warehouse'] : null,
        );

        if (! empty($f['location'])) {
            $q->where('stock_movements.location_id', $f['location']);
        }

        if (! empty($f['fromDate'])) {
            $q->whereDate('stock_movements.expiry_date', '>=', $f['fromDate']);
        }

        if (! empty($f['toDate'])) {
            $q->whereDate('stock_movements.expiry_date', '<=', $f['toDate']);
        }

        if (! empty($f['batch'])) {
            $q->where('stock_movements.batch_number', 'like', '%'.$f['batch'].'%');
        }

        return $q;
    }
}
