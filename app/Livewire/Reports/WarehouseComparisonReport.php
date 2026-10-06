<?php

namespace App\Livewire\Reports;

use App\Exports\WarehouseComparisonExport;
use App\Livewire\Reports\Concerns\HandlesReportExport;
use App\Services\Inventory\ExpiryService;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts.app')]
#[Title('Perbandingan Gudang')]
class WarehouseComparisonReport extends Component
{
    use HandlesReportExport;

    public string $search = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->hasPermission('reports.view'), 403);
    }

    public function updatedSearch(): void
    {
        // Query is not paginated; re-render is sufficient.
    }

    public function exportCsv(): StreamedResponse
    {
        abort_unless(auth()->user()->hasPermission('reports.export'), 403);

        return $this->csvResponse(
            'warehouse-comparison-'.now()->format('Ymd-His').'.csv',
            ['Kode', 'Gudang', 'Total Item', 'Total On Hand', 'Total Available', 'Low', 'Out', 'Expired', 'H-30'],
            $this->rows()->map(fn ($r) => [
                $r->code,
                $r->name,
                (int) $r->total_items,
                (int) $r->total_on_hand,
                (int) $r->total_available,
                (int) $r->low_count,
                (int) $r->out_count,
                (int) $r->expired_count,
                (int) $r->soon_count,
            ])->all(),
        );
    }

    public function exportExcel()
    {
        abort_unless(auth()->user()->hasPermission('reports.export'), 403);

        return Excel::download(new WarehouseComparisonExport($this->filterPayload()), 'warehouse-comparison-'.now()->format('Ymd-His').'.xlsx');
    }

    public function exportPdf(): StreamedResponse
    {
        abort_unless(auth()->user()->hasPermission('reports.export'), 403);

        return $this->pdfResponse('reports.pdf.warehouse-comparison', [
            'rows' => $this->rows(),
            'filters' => $this->filterLines(),
            'totals' => $this->totals(),
            'generatedAt' => now()->format('d M Y H:i'),
        ], 'warehouse-comparison-'.now()->format('Ymd-His').'.pdf');
    }

    protected function filterLines(): array
    {
        return array_filter([
            'Search' => $this->search !== '' ? $this->search : null,
        ]);
    }

    protected function filterPayload(): array
    {
        return ['search' => $this->search];
    }

    public function totals(): array
    {
        $rows = $this->rows();

        return [
            'items' => (int) $rows->sum('total_items'),
            'on_hand' => (int) $rows->sum('total_on_hand'),
            'available' => (int) $rows->sum('total_available'),
            'low' => (int) $rows->sum('low_count'),
            'out' => (int) $rows->sum('out_count'),
            'expired' => (int) $rows->sum('expired_count'),
            'soon' => (int) $rows->sum('soon_count'),
        ];
    }

    protected function rows()
    {
        return $this->baseQuery()->get();
    }

    protected function baseQuery(): QueryBuilder
    {
        $today = Carbon::today()->toDateString();
        $warn = Carbon::today()->addDays(ExpiryService::warnDays())->toDateString();

        $expired = "SELECT COUNT(*) FROM stock_movements sm WHERE sm.warehouse_id = warehouses.id AND sm.transaction_type = 'incoming' AND sm.expiry_date IS NOT NULL AND sm.expiry_date < '{$today}'";
        $soon = "SELECT COUNT(*) FROM stock_movements sm WHERE sm.warehouse_id = warehouses.id AND sm.transaction_type = 'incoming' AND sm.expiry_date IS NOT NULL AND sm.expiry_date >= '{$today}' AND sm.expiry_date <= '{$warn}'";

        return DB::table('warehouses')
            ->leftJoin('stock_balances', 'stock_balances.warehouse_id', '=', 'warehouses.id')
            ->leftJoin('items', function ($join): void {
                $join->on('items.id', '=', 'stock_balances.item_id')->whereNull('items.deleted_at');
            })
            ->when($this->search !== '', fn (QueryBuilder $q) => $q->where(function (QueryBuilder $inner): void {
                $term = '%'.$this->search.'%';
                $inner->where('warehouses.name', 'like', $term)->orWhere('warehouses.code', 'like', $term);
            }))
            ->groupBy('warehouses.id', 'warehouses.code', 'warehouses.name')
            ->select([
                'warehouses.id',
                'warehouses.code',
                'warehouses.name',
                DB::raw('COUNT(DISTINCT CASE WHEN items.id IS NOT NULL THEN stock_balances.item_id END) as total_items'),
                DB::raw('COALESCE(SUM(stock_balances.quantity_on_hand),0) as total_on_hand'),
                DB::raw('COALESCE(SUM(stock_balances.quantity_available),0) as total_available'),
                DB::raw('COALESCE(SUM(CASE WHEN stock_balances.quantity_on_hand > 0 AND stock_balances.quantity_on_hand <= items.minimum_stock THEN 1 ELSE 0 END),0) as low_count'),
                DB::raw('COALESCE(SUM(CASE WHEN stock_balances.quantity_on_hand <= 0 THEN 1 ELSE 0 END),0) as out_count'),
                DB::raw("({$expired}) as expired_count"),
                DB::raw("({$soon}) as soon_count"),
            ])
            ->orderBy('warehouses.name');
    }

    public function render()
    {
        return view('livewire.reports.warehouse-comparison-report', [
            'rows' => $this->rows(),
            'totals' => $this->totals(),
        ]);
    }
}
