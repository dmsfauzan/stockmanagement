<?php

namespace App\Livewire\Reports;

use App\Models\CustomerReturn;
use App\Models\SupplierReturn;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts.app')]
#[Title('Laporan Retur')]
class ReturnReport extends Component
{
    public string $fromDate = '';

    public string $toDate = '';

    public string $type = 'all';

    public function mount(): void
    {
        abort_unless(auth()->user()->hasPermission('reports.view'), 403);

        $this->fromDate = now()->subDays(29)->format('Y-m-d');
        $this->toDate = now()->format('Y-m-d');
    }

    /** @return array<int, array<string, mixed>> */
    protected function rows(): array
    {
        $from = $this->fromDate !== '' ? Carbon::parse($this->fromDate)->startOfDay() : null;
        $to = $this->toDate !== '' ? Carbon::parse($this->toDate)->endOfDay() : null;

        $out = [];

        if (in_array($this->type, ['all', 'customer'], true)) {
            $returns = CustomerReturn::with(['customer', 'warehouse', 'items'])
                ->when($from, fn ($q) => $q->where('transaction_date', '>=', $from->toDateString()))
                ->when($to, fn ($q) => $q->where('transaction_date', '<=', $to->toDateString()))
                ->get();

            foreach ($returns as $r) {
                $qty = (int) $r->items->sum('quantity');
                $value = (float) $r->items->sum(fn ($i) => (float) $i->quantity * (float) $i->unit_cost);
                $out[] = [
                    'number' => $r->number,
                    'type' => 'customer',
                    'date' => $r->transaction_date?->toDateString(),
                    'party' => $r->customer?->name ?? '-',
                    'warehouse' => $r->warehouse?->name ?? '-',
                    'qty' => $qty,
                    'value' => $value,
                    'status' => $r->status,
                ];
            }
        }

        if (in_array($this->type, ['all', 'supplier'], true)) {
            $returns = SupplierReturn::with(['supplier', 'warehouse', 'items'])
                ->when($from, fn ($q) => $q->where('transaction_date', '>=', $from->toDateString()))
                ->when($to, fn ($q) => $q->where('transaction_date', '<=', $to->toDateString()))
                ->get();

            foreach ($returns as $r) {
                $qty = (int) $r->items->sum('quantity');
                $value = (float) $r->items->sum(fn ($i) => (float) $i->quantity * (float) $i->unit_cost);
                $out[] = [
                    'number' => $r->number,
                    'type' => 'supplier',
                    'date' => $r->transaction_date?->toDateString(),
                    'party' => $r->supplier?->name ?? '-',
                    'warehouse' => $r->warehouse?->name ?? '-',
                    'qty' => $qty,
                    'value' => $value,
                    'status' => $r->status,
                ];
            }
        }

        usort($out, fn ($a, $b) => strcmp((string) $b['date'], (string) $a['date']));

        return $out;
    }

    public function export(): StreamedResponse
    {
        abort_unless(auth()->user()->hasPermission('reports.export'), 403);

        $rows = $this->rows();

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Number', 'Type', 'Date', 'Party', 'Warehouse', 'Qty', 'Value', 'Status']);

            foreach ($rows as $row) {
                fputcsv($handle, [$row['number'], $row['type'], $row['date'], $row['party'], $row['warehouse'], $row['qty'], $row['value'], $row['status']]);
            }

            fclose($handle);
        }, 'returns-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function render()
    {
        $rows = $this->rows();

        return view('livewire.reports.return-report', [
            'rows' => $rows,
            'totalQty' => array_sum(array_column($rows, 'qty')),
            'totalValue' => array_sum(array_column($rows, 'value')),
        ]);
    }
}
