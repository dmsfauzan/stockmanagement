<div>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

    <x-ui.page-header title="Dashboard" subtitle="Ringkasan stok gudang">
        <x-slot:actions>
            <div class="flex flex-wrap items-center gap-2">
                <select wire:model.live="range" class="app-select w-auto min-w-[160px]">
                    <option value="7">7 hari terakhir</option>
                    <option value="30">30 hari terakhir</option>
                    <option value="month">Bulan ini</option>
                    <option value="custom">Custom</option>
                </select>
                @if ($range === 'custom')
                    <input type="date" wire:model.live="fromDate" class="app-input w-auto">
                    <input type="date" wire:model.live="toDate" class="app-input w-auto">
                @endif
            </div>
        </x-slot:actions>
    </x-ui.page-header>

    @if($warehouseFilter !== null)
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <span class="inline-flex items-center gap-2 rounded-full bg-primary-50 px-3 py-1.5 text-sm font-medium text-primary-700 dark:bg-primary-900/30 dark:text-primary-300">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21V7a2 2 0 012-2h2.5a1 1 0 011 1v1.5A1 1 0 009 8.5H15a1 1 0 001-1V6a1 1 0 011-1H19a2 2 0 012 2v14M3.75 21h16.5"/></svg>
                Filtered: Warehouse {{ $activeWarehouseName ?? ('#'.$warehouseFilter) }}
                <button wire:click="clearWarehouseFilter" class="ml-1 rounded-full px-1.5 font-bold hover:bg-primary-100 dark:hover:bg-primary-900/50" title="Clear filter">×</button>
            </span>
        </div>
    @endif

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-4">
        <x-ui.stat-card label="Total Items" :value="number_format($totalItems)" tone="indigo" hint="Item aktif">
            <x-slot:icon><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4 8 4 8-4zm-8 4v10M4 7v10l8 4 8-4V7"/></svg></x-slot:icon>
        </x-ui.stat-card>
        <x-ui.stat-card label="Total Stock On Hand" :value="number_format($totalStock)" tone="slate">
            <x-slot:icon><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M20 13V7a2 2 0 00-2-2H6a2 2 0 00-2 2v6m16 0H4m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5"/></svg></x-slot:icon>
        </x-ui.stat-card>
        <x-ui.stat-card label="Incoming Today" :value="number_format($incomingToday)" tone="emerald">
            <x-slot:icon><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v12m0 0l-4-4m4 4l4-4M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2"/></svg></x-slot:icon>
        </x-ui.stat-card>
        <x-ui.stat-card label="Outgoing Today" :value="number_format($outgoingToday)" tone="rose">
            <x-slot:icon><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16V4m0 0l4 4m-4-4l-4 4M4 14v2a2 2 0 002 2h12a2 2 0 002-2v-2"/></svg></x-slot:icon>
        </x-ui.stat-card>
        <x-ui.stat-card label="Low Stock" :value="number_format($lowStockCount)" tone="amber" :href="route('stock.low')">
            <x-slot:icon><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3m0 3h.01M10.9 3.1a1.5 1.5 0 012.2 0l6.4 11.1a1.5 1.5 0 01-1.1 2.3H5.6a1.5 1.5 0 01-1.1-2.3L10.9 3.1z"/></svg></x-slot:icon>
        </x-ui.stat-card>
        <x-ui.stat-card label="Out of Stock" :value="number_format($outOfStockCount)" tone="rose" :href="route('stock.low')">
            <x-slot:icon><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></x-slot:icon>
        </x-ui.stat-card>
        <x-ui.stat-card label="Expiring" :value="number_format(count($expiringSoon ?? []))" tone="amber" :href="route('reports.expiry')" hint="H-30 / expired={{ $expiredCount ?? 0 }}">
            <x-slot:icon><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l3 3"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 2a10 10 0 100 20 10 10 0 000-20z"/></svg></x-slot:icon>
        </x-ui.stat-card>
        <x-ui.stat-card label="Nilai Persediaan" :value="'Rp '.number_format($inventoryValue ?? 0, 0, ',', '.')" tone="emerald" :href="route('reports.valuation')" hint="Moving average">
            <x-slot:icon><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></x-slot:icon>
        </x-ui.stat-card>
    </div>

    <div class="mb-6 grid grid-cols-1 gap-4 lg:grid-cols-3">
        <x-ui.card class="lg:col-span-2">
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h2 class="app-card-title">Stock Movement</h2>
                    <p class="text-xs text-app-muted">{{ $dateFrom }} s/d {{ $dateTo }}</p>
                </div>
                <div class="flex items-center gap-3 text-xs text-app-muted">
                    <span class="inline-flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span>Incoming</span>
                    <span class="inline-flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-full bg-rose-500"></span>Outgoing</span>
                </div>
            </div>
            <div
                wire:key="movement-chart-{{ $dateFrom }}-{{ $dateTo }}"
                x-data="{
                    chart: null,
                    renderChart() {
                        if (typeof ApexCharts === 'undefined') { return; }
                        const isDark = document.documentElement.classList.contains('dark');
                        const fore = isDark ? '#94a3b8' : '#64748b';
                        const gridColor = isDark ? '#334155' : '#e2e8f0';
                        this.chart = new ApexCharts(this.$refs.canvas, {
                            chart: { type: 'bar', height: 300, stacked: false, toolbar: { show: false }, fontFamily: 'inherit', foreColor: fore },
                            series: [
                                { name: 'Incoming', data: @js($chartData['incoming']) },
                                { name: 'Outgoing', data: @js($chartData['outgoing']) }
                            ],
                            xaxis: { categories: @js($chartData['categories']), labels: { rotate: -45, style: { fontSize: '11px', colors: fore } } },
                            yaxis: { labels: { style: { colors: fore } } },
                            colors: ['#10b981', '#f43f5e'],
                            plotOptions: { bar: { columnWidth: '55%', borderRadius: 3 } },
                            dataLabels: { enabled: false },
                            legend: { show: false, labels: { colors: fore } },
                            grid: { borderColor: gridColor },
                            tooltip: { theme: isDark ? 'dark' : 'light', y: { formatter: function (v) { return Number(v).toLocaleString(); } } }
                        });
                        this.chart.render();
                    },
                    destroy() { if (this.chart) { this.chart.destroy(); } }
                }"
                x-init="renderChart()"
            >
                <div x-ref="canvas"></div>
            </div>
        </x-ui.card>

        <x-ui.card>
            <h2 class="app-card-title mb-4">Inventory by Category</h2>
            @if (count($categoryChart['labels']) > 0)
                <div
                    x-data="{
                        chart: null,
                        renderChart() {
                            if (typeof ApexCharts === 'undefined') { return; }
                            const isDark = document.documentElement.classList.contains('dark');
                            const fore = isDark ? '#94a3b8' : '#64748b';
                            this.chart = new ApexCharts(this.$refs.canvas, {
                                chart: { type: 'donut', height: 300, fontFamily: 'inherit', foreColor: fore },
                                series: @js($categoryChart['values']),
                                labels: @js($categoryChart['labels']),
                                colors: ['#6366f1', '#10b981', '#f59e0b', '#ef4444', '#0ea5e9', '#8b5cf6', '#14b8a6', '#ec4899'],
                                legend: { position: 'bottom', fontSize: '12px', labels: { colors: fore } },
                                dataLabels: { enabled: true, formatter: function (val) { return Math.round(val) + '%'; } },
                                tooltip: { theme: isDark ? 'dark' : 'light', y: { formatter: function (v) { return Number(v).toLocaleString(); } } }
                            });
                            this.chart.render();
                        },
                        destroy() { if (this.chart) { this.chart.destroy(); } }
                    }"
                    x-init="renderChart()"
                >
                    <div x-ref="canvas"></div>
                </div>
            @else
                <x-ui.empty-state title="Belum ada data" message="Belum ada saldo stok per kategori." />
            @endif
        </x-ui.card>
    </div>

    <div class="mb-4">
        <x-ui.card padding="p-0">
            <div class="flex items-center justify-between border-b border-app-border px-4 py-3">
                <h2 class="app-card-title">Expiring Soon @if (($expiredCount ?? 0) > 0)<span class="ml-1 rounded-full bg-rose-500 px-2 py-0.5 text-xs font-bold text-white">{{ $expiredCount }} expired</span>@endif</h2>
                <a href="{{ route('reports.expiry') }}" class="app-link text-xs font-medium">Lihat semua</a>
            </div>
            <div class="overflow-x-auto">
                <table class="app-table">
                    <thead>
                        <tr>
                            <th>SKU</th>
                            <th>Item</th>
                            <th>Warehouse</th>
                            <th>Batch</th>
                            <th>Expiry</th>
                            <th class="text-right">Sisa</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($expiringSoon as $row)
                            @php $days = (int) ($row->days_left ?? 0); @endphp
                            <tr>
                                <td class="whitespace-nowrap font-medium">{{ $row->sku }}</td>
                                <td>{{ $row->item_name }}</td>
                                <td class="whitespace-nowrap text-app-muted">{{ $row->warehouse_name }}</td>
                                <td class="whitespace-nowrap text-app-muted">{{ $row->batch_number ?? '-' }}</td>
                                <td class="whitespace-nowrap text-app-muted">{{ \Illuminate\Support\Carbon::parse($row->expiry_date)->format('d M Y') }}</td>
                                <td class="whitespace-nowrap text-right font-medium {{ $days < 0 ? 'text-rose-600 dark:text-rose-400' : 'text-amber-600 dark:text-amber-400' }}">{{ $days < 0 ? 'Lewat '.abs($days).' hari' : 'H-'.$days }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><x-ui.empty-state title="Tidak ada batch mendekati kedaluwarsa" message="Semua batch aman dalam 30 hari ke depan." /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-ui.card>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <x-ui.card padding="p-0">
            <div class="flex items-center justify-between border-b border-app-border px-4 py-3">
                <h2 class="app-card-title">Low Stock (Top 5)</h2>
                <a href="{{ route('stock.low') }}" class="app-link text-xs font-medium">Lihat semua</a>
            </div>
            <div class="overflow-x-auto">
                <table class="app-table">
                    <thead>
                        <tr>
                            <th>SKU</th>
                            <th>Item</th>
                            <th>Warehouse</th>
                            <th class="text-right">On Hand</th>
                            <th class="text-right">Min</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($lowStockItems as $row)
                            <tr>
                                <td class="whitespace-nowrap font-medium">{{ $row->sku }}</td>
                                <td>{{ $row->item_name }}</td>
                                <td class="whitespace-nowrap text-app-muted">{{ $row->warehouse_name }}</td>
                                <td class="whitespace-nowrap text-right font-medium {{ (int) $row->quantity_on_hand <= 0 ? 'text-rose-600 dark:text-rose-400' : 'text-amber-600 dark:text-amber-400' }}">{{ number_format($row->quantity_on_hand) }}</td>
                                <td class="whitespace-nowrap text-right text-app-muted">{{ number_format($row->min_stock) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><x-ui.empty-state title="Semua stok aman" message="Tidak ada item di bawah minimum." /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-ui.card>

        <x-ui.card padding="p-0">
            <div class="flex items-center justify-between border-b border-app-border px-4 py-3">
                <h2 class="app-card-title">Recent Activities</h2>
                <a href="{{ route('stock.movements') }}" class="app-link text-xs font-medium">Lihat semua</a>
            </div>
            <div class="overflow-x-auto">
                <table class="app-table">
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>User</th>
                            <th>Type</th>
                            <th>Item</th>
                            <th class="text-right">In</th>
                            <th class="text-right">Out</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentActivities as $activity)
                            <tr>
                                <td class="whitespace-nowrap text-app-muted">{{ \Illuminate\Support\Carbon::parse($activity->created_at)->format('d M Y H:i') }}</td>
                                <td class="whitespace-nowrap text-app-muted">{{ $activity->user_name ?? '-' }}</td>
                                <td class="whitespace-nowrap"><x-ui.status-badge :status="$activity->transaction_type" /></td>
                                <td>{{ $activity->sku ? $activity->sku.' — ' : '' }}{{ $activity->item_name ?? '-' }}</td>
                                <td class="whitespace-nowrap text-right text-emerald-600 dark:text-emerald-400">{{ $activity->quantity_in > 0 ? number_format($activity->quantity_in) : '-' }}</td>
                                <td class="whitespace-nowrap text-right text-rose-600 dark:text-rose-400">{{ $activity->quantity_out > 0 ? number_format($activity->quantity_out) : '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><x-ui.empty-state title="Belum ada aktivitas" message="Belum ada pergerakan stok." /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-ui.card>
    </div>
</div>
