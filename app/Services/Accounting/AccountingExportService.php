<?php

namespace App\Services\Accounting;

use App\Exports\AccountingJournalExport;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class AccountingExportService
{
    public static function generate(Carbon $from, Carbon $to, string $format = 'csv', string $disk = 'local'): string
    {
        $rows = JournalService::journalRows($from, $to);

        $extension = $format === 'xlsx' ? 'xlsx' : 'csv';
        $path = 'accounting-exports/journal-'.$from->format('Ymd').'_'.$to->format('Ymd').'-'.now()->format('His').'.'.$extension;

        if ($extension === 'xlsx') {
            Excel::store(new AccountingJournalExport($rows), $path, $disk);

            return $path;
        }

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, ['Date', 'Reference', 'Type', 'SKU', 'Item', 'Warehouse', 'Qty', 'Unit Cost', 'Total', 'Debit', 'Credit', 'Description']);

        foreach ($rows as $row) {
            fputcsv($handle, [
                $row['date'],
                $row['reference'],
                $row['type'],
                $row['sku'],
                $row['item_name'],
                $row['warehouse_name'],
                $row['quantity'],
                $row['unit_cost'],
                $row['total_cost'],
                $row['debit_account'],
                $row['credit_account'],
                $row['description'],
            ]);
        }

        rewind($handle);
        Storage::disk($disk)->put($path, stream_get_contents($handle));
        fclose($handle);

        return $path;
    }
}
