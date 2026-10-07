@php
    $routeTitle = match (true) {
        request()->routeIs('dashboard') => 'Dashboard',
        request()->routeIs('items.*') => 'Master Barang',
        request()->routeIs('categories.*') => 'Kategori',
        request()->routeIs('units.*') => 'Unit',
        request()->routeIs('suppliers.*') => 'Supplier',
        request()->routeIs('customers.*') => 'Customer / Department',
        request()->routeIs('warehouses.*') => 'Warehouse',
        request()->routeIs('locations.*') => 'Location / Rack',
        request()->routeIs('purchase-orders.*') => 'Purchase Order',
        request()->routeIs('goods-receipts.*') => 'Barang Masuk',
        request()->routeIs('goods-issues.*') => 'Barang Keluar',
        request()->routeIs('stock-adjustments.*') => 'Stock Adjustment',
        request()->routeIs('stock-opnames.*') => 'Stock Opname',
        request()->routeIs('stock-transfers.*') => 'Transfer Barang',
        request()->routeIs('stock.index') => 'Stock On Hand',
        request()->routeIs('stock.movements') => 'Stock Movement',
        request()->routeIs('stock.low') => 'Low Stock',
        request()->routeIs('scan') => 'Scan',
        request()->routeIs('reports.stock') => 'Laporan Stock',
        request()->routeIs('reports.incoming') => 'Laporan Barang Masuk',
        request()->routeIs('reports.outgoing') => 'Laporan Barang Keluar',
        request()->routeIs('reports.movement') => 'Laporan Movement',
        request()->routeIs('reports.expiry') => 'Laporan Kedaluwarsa',
        request()->routeIs('reports.opname') => 'Laporan Opname',
        request()->routeIs('reports.adjustment') => 'Laporan Adjustment',
        request()->routeIs('reports.transfer') => 'Laporan Transfer',
        request()->routeIs('reports.warehouse-comparison') => 'Perbandingan Gudang',
        request()->routeIs('reports.replenishment') => 'Replenishment',
        request()->routeIs('reports.valuation') => 'Valuasi Persediaan',
        request()->routeIs('reports.cogs') => 'Laporan COGS',
        request()->routeIs('reports.journal') => 'Jurnal Akuntansi',
        request()->routeIs('admin.users') => 'Users',
        request()->routeIs('admin.roles') => 'Roles & Permissions',
        request()->routeIs('admin.audit-logs') => 'Audit Logs',
        request()->routeIs('admin.api-tokens') => 'API Tokens',
        request()->routeIs('admin.settings') => 'Settings',
        request()->routeIs('profile.*') => 'Profile',
        default => 'Warehouse',
    };
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0f172a">
    <meta name="color-scheme" content="light dark">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="WSM">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="192x192" href="/icons/icon-192.png">
    <title>{{ $routeTitle }} — {{ config('app.name', 'Warehouse Stock Management') }}</title>
    <script>
        (function () {
            try {
                var t = localStorage.getItem('app-theme');
                if (t === 'dark' || (!t && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                    document.documentElement.classList.add('dark');
                }
            } catch (e) {}
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    <style>[x-cloak]{display:none !important}</style>
</head>
<body
    class="font-sans antialiased bg-app-bg text-app-text"
    x-data="{
        sidebarOpen: false,
        collapsed: JSON.parse(localStorage.getItem('sidebar-collapsed') ?? 'false'),
        g: JSON.parse(localStorage.getItem('sidebar-groups') ?? 'null'),
        get groups() {
            if (this.g !== null) return this.g
            return ['inventory','transactions','masterdata','reports','admin']
        },
        isOpen(k) { return this.groups.includes(k) },
        toggleGroup(k) {
            let a = [...this.groups]
            if (a.includes(k)) a = a.filter(x => x !== k)
            else a.push(k)
            this.g = a
            localStorage.setItem('sidebar-groups', JSON.stringify(a))
        },
    }"
    x-init="$watch('collapsed', v => localStorage.setItem('sidebar-collapsed', JSON.stringify(v)))"
>

    <aside
        class="fixed inset-y-0 left-0 z-30 flex w-64 flex-col border-r border-slate-800 bg-slate-900 text-slate-100 transition-all duration-200 lg:translate-x-0"
        :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
        :style="collapsed ? 'width:72px' : 'width:16rem'"
        x-cloak
    >
        <div class="flex h-16 shrink-0 items-center gap-3 border-b border-slate-800 px-4">
            <a href="{{ route('dashboard') }}" class="flex min-w-0 items-center gap-2.5" :class="collapsed ? 'lg:mx-auto' : ''">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-primary-600 text-sm font-bold text-white shadow-sm">WS</span>
                <span x-show="!collapsed" class="truncate text-sm font-semibold tracking-tight">Stock Management</span>
            </a>
            <button @click="collapsed = !collapsed" class="ml-auto hidden shrink-0 rounded-lg p-1.5 text-slate-500 hover:bg-slate-800 hover:text-white lg:inline-flex" aria-label="Toggle sidebar">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5"/></svg>
            </button>
        </div>

        <nav class="flex-1 overflow-y-auto overflow-x-hidden px-3 py-3 no-scrollbar">
            @can('dashboard.view')
            <a href="{{ route('dashboard') }}" @class(['group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition', 'bg-primary-600 text-white shadow-sm' => request()->routeIs('dashboard'), 'text-slate-400 hover:bg-slate-800 hover:text-white' => ! request()->routeIs('dashboard')])>
                <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l9-9 9 9M4 10v10a1 1 0 001 1h5v-5h4v5h5a1 1 0 001-1V10"/></svg>
                <span x-show="!collapsed" class="truncate">Dashboard</span>
            </a>
            @endcan

            @canany(['stock.view', 'stock.movement', 'items.view'])
            <x-sidebar-group group-key="inventory" label="Inventory">
                @can('items.view')
                <a href="{{ route('scan') }}" @class(['flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition', 'bg-slate-800 text-white' => request()->routeIs('scan'), 'text-slate-400 hover:bg-slate-800 hover:text-white' => ! request()->routeIs('scan')])>
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 013.75 9.375v-4.5zM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 01-1.125-1.125v-4.5zM13.5 4.875c0-.621.504-1.125 1.125-1.125H19.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.875a1.125 1.125 0 01-1.125-1.125v-4.5zM13.5 14.625c0-.621.504-1.125 1.125-1.125H19.5c.621 0 1.125.504 1.125 1.125v1.875c0 .621-.504 1.125-1.125 1.125h-4.875a1.125 1.125 0 01-1.125-1.125v-1.875z"/></svg>
                    <span x-show="!collapsed" class="truncate">Scan</span>
                </a>
                @endcan
                @can('stock.view')
                <a href="{{ route('stock.index') }}" @class(['flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition', 'bg-slate-800 text-white' => request()->routeIs('stock.index'), 'text-slate-400 hover:bg-slate-800 hover:text-white' => ! request()->routeIs('stock.index')])>
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M20 13V7a2 2 0 00-2-2H6a2 2 0 00-2 2v6m16 0H4m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5"/></svg>
                    <span x-show="!collapsed" class="truncate">Stock On Hand</span>
                </a>
                @endcan
                @can('stock.movement')
                <a href="{{ route('stock.movements') }}" @class(['flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition', 'bg-slate-800 text-white' => request()->routeIs('stock.movements'), 'text-slate-400 hover:bg-slate-800 hover:text-white' => ! request()->routeIs('stock.movements')])>
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M7 16V4m0 0L3 8m4-4l4 4M17 8v12m0 0l4-4m-4 4l-4-4"/></svg>
                    <span x-show="!collapsed" class="truncate">Stock Movement</span>
                </a>
                @endcan
                @can('stock.view')
                <a href="{{ route('stock.low') }}" @class(['flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition', 'bg-slate-800 text-white' => request()->routeIs('stock.low'), 'text-slate-400 hover:bg-slate-800 hover:text-white' => ! request()->routeIs('stock.low')])>
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3m0 3h.01M10.9 3.1a1.5 1.5 0 012.2 0l6.4 11.1a1.5 1.5 0 01-1.1 2.3H5.6a1.5 1.5 0 01-1.1-2.3L10.9 3.1z"/></svg>
                    <span x-show="!collapsed" class="flex flex-1 items-center justify-between truncate">Low Stock @if(($lowStockCount ?? 0) > 0) <span class="ml-2 rounded-full bg-amber-500 px-2 py-0.5 text-xs font-bold text-white">{{ $lowStockCount }}</span> @endif</span>
                </a>
                @endcan
            </x-sidebar-group>
            @endcanany

            @canany(['purchase_order.view', 'goods_receipt.view', 'goods_issue.view', 'stock.adjustment', 'stock_opname.view', 'transfer.view'])
            <x-sidebar-group group-key="transactions" label="Transactions">
                @can('purchase_order.view')
                <a href="{{ route('purchase-orders.index') }}" @class(['flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition', 'bg-slate-800 text-white' => request()->routeIs('purchase-orders.*'), 'text-slate-400 hover:bg-slate-800 hover:text-white' => ! request()->routeIs('purchase-orders.*')])>
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/><path stroke-linecap="round" stroke-linejoin="round" d="M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/><path stroke-linecap="round" stroke-linejoin="round" d="M9 13h6M9 17h4"/></svg>
                    <span x-show="!collapsed" class="truncate">Purchase Order</span>
                </a>
                @endcan
                @can('goods_receipt.view')
                <a href="{{ route('goods-receipts.index') }}" @class(['flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition', 'bg-slate-800 text-white' => request()->routeIs('goods-receipts.*'), 'text-slate-400 hover:bg-slate-800 hover:text-white' => ! request()->routeIs('goods-receipts.*')])>
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6M12 9v6m7 2a2 2 0 01-2 2H7a2 2 0 01-2-2V7a2 2 0 012-2h10a2 2 0 012 2v10z"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2"/></svg>
                    <span x-show="!collapsed" class="truncate">Barang Masuk</span>
                </a>
                @endcan
                @can('goods_issue.view')
                <a href="{{ route('goods-issues.index') }}" @class(['flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition', 'bg-slate-800 text-white' => request()->routeIs('goods-issues.*'), 'text-slate-400 hover:bg-slate-800 hover:text-white' => ! request()->routeIs('goods-issues.*')])>
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12H9M12 9v6m7 2a2 2 0 01-2 2H7a2 2 0 01-2-2V7a2 2 0 012-2h10a2 2 0 012 2v10z"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2"/></svg>
                    <span x-show="!collapsed" class="truncate">Barang Keluar</span>
                </a>
                @endcan
                @can('stock.adjustment')
                <a href="{{ route('stock-adjustments.index') }}" @class(['flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition', 'bg-slate-800 text-white' => request()->routeIs('stock-adjustments.*'), 'text-slate-400 hover:bg-slate-800 hover:text-white' => ! request()->routeIs('stock-adjustments.*')])>
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4h16v4H4z"/><path stroke-linecap="round" stroke-linejoin="round" d="M8 8v10a2 2 0 002 2h4a2 2 0 002-2V8"/><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6"/></svg>
                    <span x-show="!collapsed" class="truncate">Stock Adjustment</span>
                </a>
                @endcan
                @can('stock_opname.view')
                <a href="{{ route('stock-opnames.index') }}" @class(['flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition', 'bg-slate-800 text-white' => request()->routeIs('stock-opnames.*'), 'text-slate-400 hover:bg-slate-800 hover:text-white' => ! request()->routeIs('stock-opnames.*')])>
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/><path stroke-linecap="round" stroke-linejoin="round" d="M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6M9 16h6"/></svg>
                    <span x-show="!collapsed" class="truncate">Stock Opname</span>
                </a>
                @endcan
                @can('transfer.view')
                <a href="{{ route('stock-transfers.index') }}" @class(['flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition', 'bg-slate-800 text-white' => request()->routeIs('stock-transfers.*'), 'text-slate-400 hover:bg-slate-800 hover:text-white' => ! request()->routeIs('stock-transfers.*')])>
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h11m0 0l-3-3m3 3l-3 3M20 17H9m0 0l3-3m-3 3l3 3"/></svg>
                    <span x-show="!collapsed" class="truncate">Transfer Barang</span>
                </a>
                @endcan
            </x-sidebar-group>
            @endcanany

            @canany(['items.view', 'warehouse.view', 'location.view'])
            <x-sidebar-group group-key="masterdata" label="Master Data">
                @can('items.view')
                <a href="{{ route('items.index') }}" @class(['flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition', 'bg-slate-800 text-white' => request()->routeIs('items.*'), 'text-slate-400 hover:bg-slate-800 hover:text-white' => ! request()->routeIs('items.*')])>
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4 8 4 8-4zm-8 4v10M4 7v10l8 4 8-4V7"/></svg>
                    <span x-show="!collapsed" class="truncate">Barang</span>
                </a>
                @endcan
                @can('items.view')
                <a href="{{ route('categories.index') }}" @class(['flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition', 'bg-slate-800 text-white' => request()->routeIs('categories.*'), 'text-slate-400 hover:bg-slate-800 hover:text-white' => ! request()->routeIs('categories.*')])>
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h4l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H6a2 2 0 01-2-2V6z"/></svg>
                    <span x-show="!collapsed" class="truncate">Kategori</span>
                </a>
                @endcan
                @can('items.view')
                <a href="{{ route('suppliers.index') }}" @class(['flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition', 'bg-slate-800 text-white' => request()->routeIs('suppliers.*'), 'text-slate-400 hover:bg-slate-800 hover:text-white' => ! request()->routeIs('suppliers.*')])>
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h8M8 12h8M8 17h5M5 5a2 2 0 012-2h10a2 2 0 012 2v14a2 2 0 01-2 2H7a2 2 0 01-2-2V5z"/></svg>
                    <span x-show="!collapsed" class="truncate">Supplier</span>
                </a>
                @endcan
                @can('items.view')
                <a href="{{ route('customers.index') }}" @class(['flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition', 'bg-slate-800 text-white' => request()->routeIs('customers.*'), 'text-slate-400 hover:bg-slate-800 hover:text-white' => ! request()->routeIs('customers.*')])>
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5V4H2v16h5m10 0a3 3 0 11-6 0 3 3 0 016 0zM7 8h10M7 12h7"/></svg>
                    <span x-show="!collapsed" class="truncate">Customer / Department</span>
                </a>
                @endcan
                @can('warehouse.view')
                <a href="{{ route('warehouses.index') }}" @class(['flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition', 'bg-slate-800 text-white' => request()->routeIs('warehouses.*'), 'text-slate-400 hover:bg-slate-800 hover:text-white' => ! request()->routeIs('warehouses.*')])>
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M3 10h18M5 6h14a2 2 0 012 2v11H3V8a2 2 0 012-2z"/></svg>
                    <span x-show="!collapsed" class="truncate">Warehouse</span>
                </a>
                @endcan
                @can('location.view')
                <a href="{{ route('locations.index') }}" @class(['flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition', 'bg-slate-800 text-white' => request()->routeIs('locations.*'), 'text-slate-400 hover:bg-slate-800 hover:text-white' => ! request()->routeIs('locations.*')])>
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10c0 5-7.5 10-7.5 10S4.5 15 4.5 10a7.5 7.5 0 0115 0z"/></svg>
                    <span x-show="!collapsed" class="truncate">Location / Rack</span>
                </a>
                @endcan
                @can('items.view')
                <a href="{{ route('units.index') }}" @class(['flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition', 'bg-slate-800 text-white' => request()->routeIs('units.*'), 'text-slate-400 hover:bg-slate-800 hover:text-white' => ! request()->routeIs('units.*')])>
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 6a3 3 0 013-3h12a3 3 0 013 3v12a3 3 0 01-3 3H6a3 3 0 01-3-3V6z"/><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6M12 9v6"/></svg>
                    <span x-show="!collapsed" class="truncate">Unit</span>
                </a>
                @endcan
            </x-sidebar-group>
            @endcanany

            @can('reports.view')
            <x-sidebar-group group-key="reports" label="Reports">
                <a href="{{ route('reports.stock') }}" @class(['flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition', 'bg-slate-800 text-white' => request()->routeIs('reports.stock'), 'text-slate-400 hover:bg-slate-800 hover:text-white' => ! request()->routeIs('reports.stock')])>
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3v18h18M7 16v-4M12 16V8M17 16v-6"/></svg>
                    <span x-show="!collapsed" class="truncate">Stock</span>
                </a>
                <a href="{{ route('reports.incoming') }}" @class(['flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition', 'bg-slate-800 text-white' => request()->routeIs('reports.incoming'), 'text-slate-400 hover:bg-slate-800 hover:text-white' => ! request()->routeIs('reports.incoming')])>
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v12m0 0l-4-4m4 4l4-4M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2"/></svg>
                    <span x-show="!collapsed" class="truncate">Incoming</span>
                </a>
                <a href="{{ route('reports.outgoing') }}" @class(['flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition', 'bg-slate-800 text-white' => request()->routeIs('reports.outgoing'), 'text-slate-400 hover:bg-slate-800 hover:text-white' => ! request()->routeIs('reports.outgoing')])>
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16V4m0 0l4 4m-4-4l-4 4M4 14v2a2 2 0 002 2h12a2 2 0 002-2v-2"/></svg>
                    <span x-show="!collapsed" class="truncate">Outgoing</span>
                </a>
                <a href="{{ route('reports.movement') }}" @class(['flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition', 'bg-slate-800 text-white' => request()->routeIs('reports.movement'), 'text-slate-400 hover:bg-slate-800 hover:text-white' => ! request()->routeIs('reports.movement')])>
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16 8l4 4-4 4M8 12h8"/></svg>
                    <span x-show="!collapsed" class="truncate">Movement</span>
                </a>
                <a href="{{ route('reports.expiry') }}" @class(['flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition', 'bg-slate-800 text-white' => request()->routeIs('reports.expiry'), 'text-slate-400 hover:bg-slate-800 hover:text-white' => ! request()->routeIs('reports.expiry')])>
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v1m0 0V6m0 2a2 2 0 11-4 0 2 2 0 014 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M6 12h12"/></svg>
                    <span x-show="!collapsed" class="truncate">Kedaluwarsa</span>
                </a>
                <a href="{{ route('reports.opname') }}" @class(['flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition', 'bg-slate-800 text-white' => request()->routeIs('reports.opname'), 'text-slate-400 hover:bg-slate-800 hover:text-white' => ! request()->routeIs('reports.opname')])>
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6M9 16h6M9 8h6M5 5a2 2 0 012-2h10a2 2 0 012 2v14a2 2 0 01-2 2H7a2 2 0 01-2-2V5z"/></svg>
                    <span x-show="!collapsed" class="truncate">Opname</span>
                </a>
                <a href="{{ route('reports.adjustment') }}" @class(['flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition', 'bg-slate-800 text-white' => request()->routeIs('reports.adjustment'), 'text-slate-400 hover:bg-slate-800 hover:text-white' => ! request()->routeIs('reports.adjustment')])>
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v12M9 15h6M12 3v1.5"/></svg>
                    <span x-show="!collapsed" class="truncate">Adjustment</span>
                </a>
                <a href="{{ route('reports.transfer') }}" @class(['flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition', 'bg-slate-800 text-white' => request()->routeIs('reports.transfer'), 'text-slate-400 hover:bg-slate-800 hover:text-white' => ! request()->routeIs('reports.transfer')])>
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M7 12h16M3 12l3 3 3-3"/><path stroke-linecap="round" stroke-linejoin="round" d="M17 12H3"/></svg>
                    <span x-show="!collapsed" class="truncate">Transfer</span>
                </a>
                <a href="{{ route('reports.warehouse-comparison') }}" @class(['flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition', 'bg-slate-800 text-white' => request()->routeIs('reports.warehouse-comparison'), 'text-slate-400 hover:bg-slate-800 hover:text-white' => ! request()->routeIs('reports.warehouse-comparison')])>
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21V7a2 2 0 012-2h2.5a1 1 0 011 1v1.5A1 1 0 009 8.5H15a1 1 0 001-1V6a1 1 0 011-1H19a2 2 0 012 2v14M3.75 21h16.5"/></svg>
                    <span x-show="!collapsed" class="truncate">Warehouse Comparison</span>
                </a>
                <a href="{{ route('reports.valuation') }}" @class(['flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition', 'bg-slate-800 text-white' => request()->routeIs('reports.valuation'), 'text-slate-400 hover:bg-slate-800 hover:text-white' => ! request()->routeIs('reports.valuation')])>
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c2.21 0 4 1.79 4 4s-1.79 4-4 4-4-1.79-4-4 1.79-4 4-4z"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1M12 19v1M3 12h1M20 12h1M5.6 5.6l.7.7M17.7 17.7l.7.7M5.6 18.4l.7-.7M17.7 6.3l.7-.7"/></svg>
                    <span x-show="!collapsed" class="truncate">Valuasi</span>
                </a>
                <a href="{{ route('reports.cogs') }}" @class(['flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition', 'bg-slate-800 text-white' => request()->routeIs('reports.cogs'), 'text-slate-400 hover:bg-slate-800 hover:text-white' => ! request()->routeIs('reports.cogs')])>
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7h18M3 12h18M3 17h18"/><path stroke-linecap="round" stroke-linejoin="round" d="M6 7V5a1 1 0 011-1h10a1 1 0 011 1v2"/></svg>
                    <span x-show="!collapsed" class="truncate">COGS</span>
                </a>
                <a href="{{ route('reports.journal') }}" @class(['flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition', 'bg-slate-800 text-white' => request()->routeIs('reports.journal'), 'text-slate-400 hover:bg-slate-800 hover:text-white' => ! request()->routeIs('reports.journal')])>
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4h16v16H4z"/><path stroke-linecap="round" stroke-linejoin="round" d="M4 9h16M9 9v11M15 9v11"/></svg>
                    <span x-show="!collapsed" class="truncate">Jurnal</span>
                </a>
                <a href="{{ route('reports.replenishment') }}" @class(['flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition', 'bg-slate-800 text-white' => request()->routeIs('reports.replenishment'), 'text-slate-400 hover:bg-slate-800 hover:text-white' => ! request()->routeIs('reports.replenishment')])>
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/><path stroke-linecap="round" stroke-linejoin="round" d="M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/><path stroke-linecap="round" stroke-linejoin="round" d="M9 13h6M9 17h4"/></svg>
                    <span x-show="!collapsed" class="truncate">Replenishment</span>
                </a>
            </x-sidebar-group>
            @endcan

            @canany(['users.manage', 'roles.manage', 'audit_logs.view', 'settings.manage'])
            <x-sidebar-group group-key="admin" label="Administration">
                @can('users.manage')
                <a href="{{ route('admin.users') }}" @class(['flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition', 'bg-slate-800 text-white' => request()->routeIs('admin.users'), 'text-slate-400 hover:bg-slate-800 hover:text-white' => ! request()->routeIs('admin.users')])>
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19a3 3 0 10-6 0m6 0H9m6 0a3 3 0 01-6 0M15 13a3 3 0 10-6 0 3 3 0 006 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M5 19a7 7 0 0114 0"/></svg>
                    <span x-show="!collapsed" class="truncate">Users</span>
                </a>
                @endcan
                @can('roles.manage')
                <a href="{{ route('admin.roles') }}" @class(['flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition', 'bg-slate-800 text-white' => request()->routeIs('admin.roles'), 'text-slate-400 hover:bg-slate-800 hover:text-white' => ! request()->routeIs('admin.roles')])>
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span x-show="!collapsed" class="truncate">Roles &amp; Permissions</span>
                </a>
                @endcan
                @can('audit_logs.view')
                <a href="{{ route('admin.audit-logs') }}" @class(['flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition', 'bg-slate-800 text-white' => request()->routeIs('admin.audit-logs'), 'text-slate-400 hover:bg-slate-800 hover:text-white' => ! request()->routeIs('admin.audit-logs')])>
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6M9 16h6M9 8h6M5 5a2 2 0 012-2h10a2 2 0 012 2v14a2 2 0 01-2 2H7a2 2 0 01-2-2V5z"/></svg>
                    <span x-show="!collapsed" class="truncate">Audit Logs</span>
                </a>
                @endcan
                @can('settings.manage')
                <a href="{{ route('admin.settings') }}" @class(['flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition', 'bg-slate-800 text-white' => request()->routeIs('admin.settings'), 'text-slate-400 hover:bg-slate-800 hover:text-white' => ! request()->routeIs('admin.settings')])>
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6a1.5 1.5 0 113 0 1.5 1.5 0 01-3 0zM10.5 12a1.5 1.5 0 113 0 1.5 1.5 0 01-3 0zM10.5 18a1.5 1.5 0 113 0 1.5 1.5 0 01-3 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h4M16 6h4M4 12h4M16 12h4M4 18h4M16 18h4"/></svg>
                    <span x-show="!collapsed" class="truncate">API Tokens</span>
                </a>
                @endcan
                @can('settings.manage')
                <a href="{{ route('admin.settings') }}" @class(['flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition', 'bg-slate-800 text-white' => request()->routeIs('admin.settings'), 'text-slate-400 hover:bg-slate-800 hover:text-white' => ! request()->routeIs('admin.settings')])>
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v8h16V4"/><path stroke-linecap="round" stroke-linejoin="round" d="M4 12v6a2 2 0 002 2h12a2 2 0 002-2v-6"/><path stroke-linecap="round" stroke-linejoin="round" d="M9 16h6"/></svg>
                    <span x-show="!collapsed" class="truncate">Settings</span>
                </a>
                @endcan
            </x-sidebar-group>
            @endcanany
        </nav>

        <div class="border-t border-slate-800 p-3">
            <div class="flex items-center gap-3 rounded-xl px-2 py-2" :class="collapsed ? 'lg:justify-center' : ''">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-700 text-xs font-semibold text-white">{{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}</span>
                <div x-show="!collapsed" class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium text-white">{{ auth()->user()->name ?? 'User' }}</p>
                    <p class="truncate text-xs text-slate-500">{{ auth()->user()->email ?? '' }}</p>
                </div>
            </div>
        </div>
    </aside>

    <div x-show="sidebarOpen" @click="sidebarOpen = false" x-transition.opacity class="fixed inset-0 z-20 bg-slate-900/60 backdrop-blur-sm lg:hidden" x-cloak></div>

    <div :style="collapsed ? 'padding-left:72px' : 'padding-left:16rem'" class="flex min-h-screen flex-col transition-all duration-200 max-lg:!pl-0">
        <header class="sticky top-0 z-10 flex h-16 items-center gap-3 border-b border-app-border bg-app-surface/80 px-4 backdrop-blur sm:px-6">
            <button @click="sidebarOpen = !sidebarOpen" class="rounded-lg p-2 text-app-muted hover:bg-app-surface-2 lg:hidden" aria-label="Open sidebar">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5"/></svg>
            </button>

            <div class="min-w-0">
                <h1 class="truncate text-sm font-semibold text-app-text">{{ $title ?? $routeTitle }}</h1>
                <p class="hidden truncate text-xs text-app-muted sm:block">Warehouse Stock Management</p>
            </div>

            <livewire:layout.warehouse-switcher />

            <div class="ml-auto hidden max-w-sm flex-1 md:flex">
                <livewire:layout.global-search />
            </div>

            <div class="ml-auto flex items-center gap-1 md:ml-0">
                <button
                    type="button"
                    x-data="{ dark: document.documentElement.classList.contains('dark') }"
                    @click="dark = !dark; document.documentElement.classList.toggle('dark', dark); localStorage.setItem('app-theme', dark ? 'dark' : 'light')"
                    class="rounded-lg p-2 text-app-muted hover:bg-app-surface-2 hover:text-app-text"
                    aria-label="Toggle theme"
                >
                    <svg x-show="!dark" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/></svg>
                    <svg x-show="dark" x-cloak class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1.5M12 19.5V21M4.22 4.22l1.06 1.06M18.72 18.72l1.06 1.06M3 12h1.5M19.5 12H21M4.22 19.78l1.06-1.06M18.72 5.28l1.06-1.06M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </button>

                @can('stock.view')
                <a href="{{ route('stock.low') }}" class="relative rounded-lg p-2 text-app-muted hover:bg-app-surface-2 hover:text-app-text" title="Low stock">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3m0 3h.01M10.9 3.1a1.5 1.5 0 012.2 0l6.4 11.1a1.5 1.5 0 01-1.1 2.3H5.6a1.5 1.5 0 01-1.1-2.3L10.9 3.1z"/></svg>
                    @if(($lowStockCount ?? 0) > 0)
                    <span class="absolute -right-0.5 -top-0.5 flex h-5 min-w-[20px] items-center justify-center rounded-full bg-amber-500 px-1 text-xs font-bold leading-none text-white">{{ $lowStockCount }}</span>
                    @endif
                </a>
                @endcan

                <livewire:notifications-bell />

                <div x-data="{ open: false }" class="relative ml-1">
                    <button @click="open = !open" @click.outside="open = false" class="flex items-center gap-2 rounded-full border border-app-border bg-app-surface px-1.5 py-1 text-sm hover:bg-app-surface-2">
                        <span class="flex h-7 w-7 items-center justify-center rounded-full bg-primary-600 text-xs font-semibold text-white">{{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}</span>
                        <span class="hidden max-w-[120px] truncate font-medium text-app-text sm:inline">{{ auth()->user()->name ?? 'User' }}</span>
                        <svg class="mr-1 hidden h-4 w-4 text-app-muted sm:block" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6"/></svg>
                    </button>
                    <div x-show="open" x-transition x-cloak class="app-dropdown right-0 mt-2 w-52">
                        <div class="px-4 py-2">
                            <p class="truncate text-sm font-medium text-app-text">{{ auth()->user()->name }}</p>
                            <p class="truncate text-xs text-app-muted">{{ auth()->user()->email }}</p>
                        </div>
                        <div class="my-1 border-t border-app-border"></div>
                        <a href="{{ route('profile.edit') }}" class="block px-4 py-2 text-sm text-app-text hover:bg-app-surface-2">Profile</a>
                        <a href="{{ route('profile.notifications') }}" class="block px-4 py-2 text-sm text-app-text hover:bg-app-surface-2">Preferensi Notifikasi</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-rose-600 hover:bg-app-surface-2">Log Out</button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
            {{ $slot }}
        </main>

        <footer class="border-t border-app-border bg-app-surface px-4 py-4 text-center text-xs text-app-muted sm:px-6">
            &copy; {{ date('Y') }} {{ config('app.name', 'Warehouse Stock Management') }} &middot; Metronic-inspired UI
        </footer>
    </div>

    <div
        x-data="{
            toasts: [],
            push(detail) {
                let raw = detail && typeof detail === 'object' && !Array.isArray(detail) ? detail : (Array.isArray(detail) ? detail[0] : { message: String(detail ?? '') });
                let msg = raw.message ?? raw.msg ?? '';
                if (!msg && typeof detail === 'string') msg = detail;
                if (!msg) return;
                let type = raw.type ?? 'info';
                let id = Date.now() + Math.random();
                this.toasts.push({ id, type, message: msg });
                setTimeout(() => this.toasts = this.toasts.filter(t => t.id !== id), 4000);
            },
            remove(id) { this.toasts = this.toasts.filter(t => t.id !== id); }
        }"
        @toast.window="push($event.detail)"
        @notify.window="push($event.detail)"
        x-init="
            @if(session('status')) push({ type: 'success', message: @js(session('status')) }); @endif
            @if(session('success')) push({ type: 'success', message: @js(session('success')) }); @endif
            @if(session('error')) push({ type: 'error', message: @js(session('error')) }); @endif
            @if(session('warning')) push({ type: 'warning', message: @js(session('warning')) }); @endif
            @if($errors->any()) push({ type: 'error', message: @js($errors->first()) }); @endif
        "
        class="pointer-events-none fixed right-4 top-4 z-50 flex w-80 max-w-[calc(100vw-2rem)] flex-col gap-2"
        aria-live="polite"
    >
        <template x-for="t in toasts" :key="t.id">
            <div
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 translate-y-1"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="pointer-events-auto flex items-start gap-3 rounded-xl border bg-app-surface px-4 py-3 shadow-dropdown"
                :class="{
                    'border-green-200 dark:border-green-900': t.type === 'success',
                    'border-red-200 dark:border-red-900': t.type === 'error' || t.type === 'danger',
                    'border-amber-200 dark:border-amber-900': t.type === 'warning',
                    'border-app-border': t.type === 'info' || !['success','error','danger','warning'].includes(t.type)
                }"
            >
                <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full" :class="{ 'bg-green-500': t.type==='success', 'bg-red-500': t.type==='error'||t.type==='danger', 'bg-amber-500': t.type==='warning', 'bg-slate-400': t.type==='info' || !['success','error','danger','warning'].includes(t.type) }"></span>
                <p class="flex-1 text-sm font-medium leading-5 text-app-text" x-text="t.message"></p>
                <button @click="remove(t.id)" class="-mr-1 rounded p-1 text-app-muted opacity-70 hover:opacity-100" aria-label="Dismiss">&times;</button>
            </div>
        </template>
    </div>

    @livewireScripts
    <script>
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw.js').catch(function () {});
        }
    </script>
</body>
</html>
