<?php

namespace App\Livewire\Dashboard;

use App\Models\Item;
use App\Services\Inventory\ExpiryService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Dashboard')]
class DashboardIndex extends Component
{
    public string $range = '7';

    public string $fromDate = '';

    public string $toDate = '';

    public function updatedRange(): void
    {
        if ($this->range !== 'custom') {
            $this->fromDate = '';
            $this->toDate = '';
        }
    }

    public function render()
    {
        $totalItems = Item::where('status', 'active')->count();
        $totalStock = (int) DB::table('stock_balances')->sum('quantity_on_hand');

        $today = Carbon::today();
        $incomingToday = (int) DB::table('stock_movements')->whereDate('created_at', $today)->where('transaction_type', 'incoming')->sum('quantity_in');
        $outgoingToday = (int) DB::table('stock_movements')->whereDate('created_at', $today)->where('transaction_type', 'outgoing')->sum('quantity_out');

        $lowRow = DB::table('stock_balances')
            ->join('items', 'items.id', '=', 'stock_balances.item_id')
            ->whereNull('items.deleted_at')
            ->selectRaw('SUM(CASE WHEN stock_balances.quantity_on_hand > 0 AND stock_balances.quantity_on_hand <= items.minimum_stock THEN 1 ELSE 0 END) as low_count, SUM(CASE WHEN stock_balances.quantity_on_hand <= 0 THEN 1 ELSE 0 END) as out_count')
            ->first();
        $lowStockCount = (int) ($lowRow->low_count ?? 0);
        $outOfStockCount = (int) ($lowRow->out_count ?? 0);

        [$dateFrom, $dateTo] = $this->resolveRange();
        $chartData = $this->buildMovementChart($dateFrom, $dateTo);
        $categoryChart = $this->buildCategoryChart();

        $lowStockItems = DB::table('stock_balances')
            ->join('items', 'stock_balances.item_id', '=', 'items.id')
            ->join('warehouses', 'stock_balances.warehouse_id', '=', 'warehouses.id')
            ->join('locations', 'stock_balances.location_id', '=', 'locations.id')
            ->whereNull('items.deleted_at')
            ->whereColumn('stock_balances.quantity_on_hand', '<=', 'items.minimum_stock')
            ->select(['items.sku', 'items.name as item_name', 'warehouses.name as warehouse_name', 'locations.code as location_code', 'stock_balances.quantity_on_hand', 'items.minimum_stock as min_stock'])
            ->orderBy('stock_balances.quantity_on_hand')
            ->limit(5)
            ->get();

        $recentActivities = DB::table('stock_movements')
            ->leftJoin('items', 'stock_movements.item_id', '=', 'items.id')
            ->leftJoin('users', 'stock_movements.created_by', '=', 'users.id')
            ->select(['stock_movements.id', 'stock_movements.created_at', 'stock_movements.transaction_type', 'stock_movements.reference_type', 'stock_movements.reference_id', 'stock_movements.quantity_in', 'stock_movements.quantity_out', 'items.sku', 'items.name as item_name', 'users.name as user_name'])
            ->orderByDesc('stock_movements.created_at')
            ->orderByDesc('stock_movements.id')
            ->limit(8)
            ->get();

        $expiringSoon = ExpiryService::rows('expiring_30')->limit(5)->get();
        $expiredCount = ExpiryService::counts()['expired'] ?? 0;

        return view('livewire.dashboard.dashboard-index', [
            'totalItems' => $totalItems,
            'totalStock' => $totalStock,
            'incomingToday' => $incomingToday,
            'outgoingToday' => $outgoingToday,
            'lowStockCount' => $lowStockCount,
            'outOfStockCount' => $outOfStockCount,
            'chartData' => $chartData,
            'categoryChart' => $categoryChart,
            'lowStockItems' => $lowStockItems,
            'recentActivities' => $recentActivities,
            'dateFrom' => $dateFrom->format('Y-m-d'),
            'dateTo' => $dateTo->format('Y-m-d'),
            'expiringSoon' => $expiringSoon,
            'expiredCount' => $expiredCount,
        ]);
    }

    protected function resolveRange(): array
    {
        if ($this->range === 'custom' && $this->fromDate !== '' && $this->toDate !== '') {
            try {
                $from = Carbon::parse($this->fromDate)->startOfDay();
                $to = Carbon::parse($this->toDate)->endOfDay();
                if ($from->lte($to)) {
                    return [$from, $to];
                }
            } catch (\Throwable) {
            }
        }
        if ($this->range === '30') {
            return [Carbon::today()->subDays(29)->startOfDay(), Carbon::today()->endOfDay()];
        }
        if ($this->range === 'month') {
            return [Carbon::now()->startOfMonth()->startOfDay(), Carbon::now()->endOfMonth()->endOfDay()];
        }

        return [Carbon::today()->subDays(6)->startOfDay(), Carbon::today()->endOfDay()];
    }

    protected function buildMovementChart(Carbon $from, Carbon $to): array
    {
        $rows = DB::table('stock_movements')
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('DATE(created_at) as d, COALESCE(SUM(quantity_in),0) as total_in, COALESCE(SUM(quantity_out),0) as total_out')
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('d')
            ->get()
            ->keyBy('d');

        $period = [];
        $cursor = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();

        if ($cursor->diffInDays($end) > 366) {
            $cursor = $end->copy()->subDays(365);
        }

        while ($cursor->lte($end)) {
            $key = $cursor->format('Y-m-d');
            $r = $rows->get($key);
            $period[] = ['date' => $cursor->format('d M'), 'incoming' => (int) ($r->total_in ?? 0), 'outgoing' => (int) ($r->total_out ?? 0)];
            $cursor->addDay();
        }

        return [
            'categories' => array_column($period, 'date'),
            'incoming' => array_column($period, 'incoming'),
            'outgoing' => array_column($period, 'outgoing'),
        ];
    }

    protected function buildCategoryChart(): array
    {
        $rows = DB::table('stock_balances')
            ->join('items', 'stock_balances.item_id', '=', 'items.id')
            ->join('categories', 'categories.id', '=', 'items.category_id')
            ->whereNull('items.deleted_at')
            ->groupBy('categories.id', 'categories.name')
            ->selectRaw('categories.name as label, COALESCE(SUM(stock_balances.quantity_on_hand),0) as total')
            ->orderByDesc('total')
            ->get();

        return [
            'labels' => $rows->pluck('label')->toArray(),
            'values' => $rows->pluck('total')->map(fn ($v) => (int) $v)->toArray(),
        ];
    }
}
