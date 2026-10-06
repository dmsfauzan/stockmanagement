<?php

namespace App\Livewire\Dashboard;

use App\Models\Item;
use App\Models\Warehouse;
use App\Services\Inventory\ExpiryService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Dashboard')]
class DashboardIndex extends Component
{
    public string $range = '7';

    public string $fromDate = '';

    public string $toDate = '';

    public ?int $warehouseFilter = null;

    public function mount(): void
    {
        $this->warehouseFilter = $this->activeWarehouseId();
    }

    public function updatedRange(): void
    {
        if ($this->range !== 'custom') {
            $this->fromDate = '';
            $this->toDate = '';
        }
    }

    public function updatedWarehouseFilter(): void
    {
        if ($this->warehouseFilter === null) {
            session()->forget('active_warehouse_id');
        } else {
            session(['active_warehouse_id' => $this->warehouseFilter]);
        }

        $this->dispatch('warehouse-changed', warehouseId: $this->warehouseFilter);
    }

    public function clearWarehouseFilter(): void
    {
        $this->warehouseFilter = null;
        session()->forget('active_warehouse_id');
        $this->dispatch('warehouse-changed', warehouseId: null);
    }

    #[On('warehouse-changed')]
    public function refreshFromSwitcher(): void
    {
        $this->warehouseFilter = $this->activeWarehouseId();
    }

    protected function activeWarehouseId(): ?int
    {
        try {
            $id = session('active_warehouse_id');
        } catch (\Throwable) {
            return $this->warehouseFilter;
        }

        if ($id === null || $id === '' || $id === 0 || $id === '0') {
            return null;
        }

        return (int) $id;
    }

    public function render()
    {
        $wid = $this->activeWarehouseId();
        if ($this->warehouseFilter !== $wid) {
            $this->warehouseFilter = $wid;
        }

        $totalItems = Item::where('status', 'active')->count();
        $totalStock = (int) DB::table('stock_balances')->when($wid !== null, fn ($q) => $q->where('warehouse_id', $wid))->sum('quantity_on_hand');

        $today = Carbon::today();
        $incomingToday = (int) DB::table('stock_movements')->whereDate('created_at', $today)->where('transaction_type', 'incoming')->when($wid !== null, fn ($q) => $q->where('warehouse_id', $wid))->sum('quantity_in');
        $outgoingToday = (int) DB::table('stock_movements')->whereDate('created_at', $today)->where('transaction_type', 'outgoing')->when($wid !== null, fn ($q) => $q->where('warehouse_id', $wid))->sum('quantity_out');

        $lowRow = DB::table('stock_balances')
            ->join('items', 'items.id', '=', 'stock_balances.item_id')
            ->whereNull('items.deleted_at')
            ->when($wid !== null, fn ($q) => $q->where('stock_balances.warehouse_id', $wid))
            ->selectRaw('SUM(CASE WHEN stock_balances.quantity_on_hand > 0 AND stock_balances.quantity_on_hand <= items.minimum_stock THEN 1 ELSE 0 END) as low_count, SUM(CASE WHEN stock_balances.quantity_on_hand <= 0 THEN 1 ELSE 0 END) as out_count')
            ->first();
        $lowStockCount = (int) ($lowRow->low_count ?? 0);
        $outOfStockCount = (int) ($lowRow->out_count ?? 0);

        [$dateFrom, $dateTo] = $this->resolveRange();
        $chartData = $this->buildMovementChart($dateFrom, $dateTo, $wid);
        $categoryChart = $this->buildCategoryChart($wid);

        $lowStockItems = DB::table('stock_balances')
            ->join('items', 'stock_balances.item_id', '=', 'items.id')
            ->join('warehouses', 'stock_balances.warehouse_id', '=', 'warehouses.id')
            ->join('locations', 'stock_balances.location_id', '=', 'locations.id')
            ->whereNull('items.deleted_at')
            ->whereColumn('stock_balances.quantity_on_hand', '<=', 'items.minimum_stock')
            ->when($wid !== null, fn ($q) => $q->where('stock_balances.warehouse_id', $wid))
            ->select(['items.sku', 'items.name as item_name', 'warehouses.name as warehouse_name', 'locations.code as location_code', 'stock_balances.quantity_on_hand', 'items.minimum_stock as min_stock'])
            ->orderBy('stock_balances.quantity_on_hand')
            ->limit(5)
            ->get();

        $recentActivities = DB::table('stock_movements')
            ->leftJoin('items', 'stock_movements.item_id', '=', 'items.id')
            ->leftJoin('users', 'stock_movements.created_by', '=', 'users.id')
            ->when($wid !== null, fn ($q) => $q->where('stock_movements.warehouse_id', $wid))
            ->select(['stock_movements.id', 'stock_movements.created_at', 'stock_movements.transaction_type', 'stock_movements.reference_type', 'stock_movements.reference_id', 'stock_movements.quantity_in', 'stock_movements.quantity_out', 'items.sku', 'items.name as item_name', 'users.name as user_name'])
            ->orderByDesc('stock_movements.created_at')
            ->orderByDesc('stock_movements.id')
            ->limit(8)
            ->get();

        $expiringSoon = ExpiryService::rows('expiring_30', null, $wid)->limit(5)->get();
        $expiredCount = $wid === null ? (ExpiryService::counts()['expired'] ?? 0) : (int) ExpiryService::rows('expired', null, $wid)->count();

        $activeWarehouseName = $wid !== null ? Warehouse::whereKey($wid)->value('name') : null;

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
            'activeWarehouseName' => $activeWarehouseName,
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

    protected function buildMovementChart(Carbon $from, Carbon $to, ?int $warehouseId = null): array
    {
        $rows = DB::table('stock_movements')
            ->whereBetween('created_at', [$from, $to])
            ->when($warehouseId !== null, fn ($q) => $q->where('warehouse_id', $warehouseId))
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

    protected function buildCategoryChart(?int $warehouseId = null): array
    {
        $rows = DB::table('stock_balances')
            ->join('items', 'stock_balances.item_id', '=', 'items.id')
            ->join('categories', 'categories.id', '=', 'items.category_id')
            ->whereNull('items.deleted_at')
            ->when($warehouseId !== null, fn ($q) => $q->where('stock_balances.warehouse_id', $warehouseId))
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
