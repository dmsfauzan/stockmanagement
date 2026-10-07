<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class AccountingJournalExport implements FromCollection, WithHeadings
{
    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    public function __construct(protected Collection $rows) {}

    public function headings(): array
    {
        return ['Date', 'Reference', 'Type', 'SKU', 'Item', 'Warehouse', 'Qty', 'Unit Cost', 'Total', 'Debit', 'Credit', 'Description'];
    }

    public function collection(): Collection
    {
        return $this->rows->map(fn (array $r): array => [
            $r['date'],
            $r['reference'],
            $r['type'],
            $r['sku'],
            $r['item_name'],
            $r['warehouse_name'],
            $r['quantity'],
            $r['unit_cost'],
            $r['total_cost'],
            $r['debit_account'],
            $r['credit_account'],
            $r['description'],
        ]);
    }
}
