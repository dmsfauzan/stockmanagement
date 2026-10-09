<?php

namespace App\Livewire\Dashboard;

use App\Models\DashboardPreference;
use App\Models\Item;
use App\Models\Warehouse;
use App\Services\Inventory\ExpiryService;
use App\Services\Support\WarehouseAccess;
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
    /** @var array<int, string> */
    public const WIDGETS = ['stats', 'movement_chart', 'category_chart', 'expiring_soon', 'low_stock', 'recent_activities'];

    /** @var array<string, string> */
    public const WIDGET_LABELS = [
        'stats' => 'Ringkasan Statistik',
        'movement_chart' => 'Grafik Pergerakan Stok',
        'category_chart' => 'Stok per Kategori',
        'expiring_soon' => 'Batch Mendekati Kedaluwarsa',
        'low_stock' => 'Low Stock (Top 5)',
        'recent_activities' => 'Aktivitas Terbaru',
    ];

    public string $range = '7';

    public string $fromDate = '';

    public string $toDate = '';

    public ?int $warehouseFilter = null;

    public bool $showLayoutModal = false;

    /** @var array<int, string> */
    public array $widgetOrder = [];

    /** @var array<int, string> */
    public array $enabledWidgets = [];

    public function mount(): void
    {
        $this->warehouseFilter = $this->activeWarehouseId();
        $this->loadLayout();
    }

    protected function loadLayout(): void
    {
        $saved = DashboardPreference::where('user_id', auth()->id())->value('widgets');

        $enabled = is_array($saved) ? array_values(array_intersect($saved, self::WIDGETS)) : self::WIDGETS;

        $this->enabledWidgets = $enabled === [] ? self::WIDGETS : $enabled;
        $this->widgetOrder = array_values(array_unique(array_merge($this->enabledWidgets, self::WIDGETS)));
    }

    public function openLayoutModal(): void
    {
        $this->loadLayout();
        $this->showLayoutModal = true;
    }

    public function toggleWidget(string $key): void
    {
        if (! in_array($key, self::WIDGETS, true)) {
            return;
        }

        if (in_array($key, $this->enabledWidgets, true)) {
            $this->enabledWidgets = array_values(array_diff($this->enabledWidgets, [$key]));
        } else {
            $this->enabledWidgets[] = $key;
        }
    }

    public function moveWidget(string $key, string $direction): void
    {
        $index = array_search($key, $this->widgetOrder, true);

        if ($index === false) {
            return;
        }

        $target = $direction === 'up' ? $index - 1 : $index + 1;

        if ($target < 0 || $target >= count($this->widgetOrder)) {
            return;
        }

        $order = $this->widgetOrder;
        [$order[$index], $order[$target]] = [$order[$target], $order[$index]];
        $this->widgetOrder = $order;
    }

    public function saveLayout(): void
    {
        $visible = array_values(array_filter($this->widgetOrder, fn (string $key) => in_array($key, $this->enabledWidgets, true)));

        if ($visible === []) {
            $this->dispatch('toast', type: 'error', message: __('Pilih minimal satu widget.'));

            return;
        }

        DashboardPreference::updateOrCreate(
            ['user_id' => auth()->id()],
            ['widgets' => $visible],
        );

        $this->enabledWidgets = $visible;
        $this->showLayoutModal = false;

        $this->dispatch('toast', type: 'success', message: __('Layout dashboard disimpan.'));
    }

    public function resetLayout(): void
    {
        DashboardPreference::where('user_id', auth()->id())->delete();
        $this->loadLayout();
        $this->showLayoutModal = false;

        $this->dispatch('toast', type: 'success', message: __('Layout dashboard direset.'));
    }

    public function isWidgetVisible(string $key): bool
    {
        return in_array($key, $this->enabledWidgets, true);
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
            return WarehouseAccess::activeId();
        } catch (\Throwable) {
            return $this->warehouseFilter;
        }
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

        $inventoryValue = (float) DB::table('inventory_valuations')
            ->when($wid !== null, fn ($q) => $q->where('warehouse_id', $wid))
            ->sum('total_value');

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
            'inventoryValue' => $inventoryValue,
            'widgetLabels' => self::WIDGET_LABELS,
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
