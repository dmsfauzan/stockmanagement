<?php

namespace App\Livewire\Reports\Concerns;

use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

trait HandlesReportExport
{
    /**
     * Stream an array of rows as a downloadable CSV file.
     */
    protected function csvResponse(string $filename, array $headings, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headings, $rows): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headings);

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Render a Blade view to PDF and stream it back (Livewire-safe).
     */
    protected function pdfResponse(string $view, array $data, string $filename): StreamedResponse
    {
        $pdf = Pdf::loadView($view, $data)->setPaper('a4', 'landscape');

        return response()->streamDownload(function () use ($pdf): void {
            echo $pdf->output();
        }, $filename, ['Content-Type' => 'application/pdf']);
    }

    /**
     * Build a human readable summary of the active filters for PDF/Excel headers.
     */
    protected function filterSummary(array $filters): array
    {
        $labels = [
            'warehouse' => 'Warehouse',
            'location' => 'Location',
            'category' => 'Category',
            'supplier' => 'Supplier',
            'customer' => 'Customer',
            'item' => 'Item',
            'search' => 'Search',
            'status' => 'Status',
            'transaction_type' => 'Type',
            'user' => 'User',
            'fromDate' => 'From',
            'toDate' => 'To',
        ];

        $summary = [];

        foreach ($filters as $key => $value) {
            if ($value === null || $value === '' || ! isset($labels[$key])) {
                continue;
            }

            $summary[] = $labels[$key].': '.$value;
        }

        return $summary;
    }
}
