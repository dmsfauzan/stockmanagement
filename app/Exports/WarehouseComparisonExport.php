<?php

namespace App\Exports;

use App\Services\Inventory\ExpiryService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class WarehouseComparisonExport implements FromCollection, WithHeadings
{
    public function __construct(protected array $filters = [])
    {
    }

    public function headings(): array
    {
        return ['Kode', 'Gudang', 'Total Item', 'Total On Hand', 'Total Available', 'Low', 'Out', 'Expired', 'H-30'];
    }

    public function collection(): Collection
    {
        return $this->query()->get()->map(fn ($r) => [
            $r->code,
            $r->name,
            (int) $r->total_items,
            (int) $r->total_on_hand,
            (int) $r->total_available,
            (int) $r->low_count,
            (int) $r->out_count,
            (int) $r->expired_count,
            (int) $r->soon_count,
        ]);
    }

    protected function query()
    {
        $f = $this->filters;
        $today = Carbon::today()->toDateString();
        $warn = Carbon::today()->addDays(ExpiryService::warnDays())->toDateString();

        $expired = "SELECT COUNT(*) FROM stock_movements sm WHERE sm.warehouse_id = warehouses.id AND sm.transaction_type = 'incoming' AND sm.expiry_date IS NOT NULL AND sm.expiry_date < '{$today}'";
        $soon = "SELECT COUNT(*) FROM stock_movements sm WHERE sm.warehouse_id = warehouses.id AND sm.transaction_type = 'incoming' AND sm.expiry_date IS NOT NULL AND sm.expiry_date >= '{$today}' AND sm.expiry_date <= '{$warn}'";

        return DB::table('warehouses')
            ->leftJoin('stock_balances', 'stock_balances.warehouse_id', '=', 'warehouses.id')
            ->leftJoin('items', function ($join): void {
                $join->on('items.id', '=', 'stock_balances.item_id')->whereNull('items.deleted_at');
            })
            ->when(! empty($f['search']), function ($q) use ($f): void {
                $term = '%'.$f['search'].'%';
                $q->where(function ($inner) use ($term): void {
                    $inner->where('warehouses.name', 'like', $term)->orWhere('warehouses.code', 'like', $term);
                });
            })
            ->groupBy('warehouses.id', 'warehouses.code', 'warehouses.name')
            ->select([
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
}
