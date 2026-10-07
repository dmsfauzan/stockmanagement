@php
    $diffClass = function (int $diff): string {
        if ($diff > 0) return 'text-emerald-600 dark:text-emerald-400 font-semibold';
        if ($diff < 0) return 'text-rose-600 dark:text-rose-400 font-semibold';
        return 'text-app-muted';
    };
    $statuses = [
        'draft' => 'Draft',
        'counting' => 'Counting',
        'submitted' => 'Submitted',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'completed' => 'Completed',
    ];
@endphp

<div>
    <x-ui.page-header title="Laporan Opname" subtitle="Rekap hasil stock opname per barang (system vs fisik)">
        <x-slot:actions>
            @can('reports.export')
                <x-ui.button variant="secondary" wire:click="exportCsv">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                    CSV
                </x-ui.button>
                <x-ui.button variant="secondary" wire:click="exportExcel">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                    Excel
                </x-ui.button>
                <x-ui.button variant="secondary" wire:click="exportPdf">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                    PDF
                </x-ui.button>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
        <x-ui.stat-card label="Total Baris" :value="number_format($totals['rows'])" tone="slate" />
        <x-ui.stat-card label="Selisih Bersih" :value="number_format($totals['net'])" :tone="$totals['net'] === 0 ? 'slate' : ($totals['net'] > 0 ? 'emerald' : 'rose')" />
        <x-ui.stat-card label="Total Variance" :value="number_format($totals['abs'])" tone="amber" />
    </div>

    <x-ui.card padding="p-0">
        <div class="border-b border-app-border p-4">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div class="relative">
                    <svg class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-app-muted" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.3-4.3M10 18a8 8 0 110-16 8 8 0 010 16z"/></svg>
                    <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari no. opname / SKU / nama barang..." class="app-input pl-9">
                </div>
                <select wire:model.live="statusFilter" class="app-select">
                    <option value="">Semua Status</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <select wire:model.live="warehouseFilter" class="app-select">
                    <option value="">Semua Warehouse</option>
                    @foreach ($warehouses as $warehouse)<option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>@endforeach
                </select>
                <select wire:model.live="perPage" class="app-select w-24">
                    @foreach ([15, 25, 50, 100] as $size)<option value="{{ $size }}">{{ $size }}</option>@endforeach
                </select>
                <input type="date" wire:model.live="dateFrom" class="app-input" title="Tanggal dari">
                <input type="date" wire:model.live="dateTo" class="app-input" title="Tanggal sampai">
            </div>
            <div class="mt-3 flex items-center justify-between">
                <button type="button" wire:click="resetFilters" class="app-btn app-btn-ghost">Reset Filter</button>
                @include('livewire.reports._report-columns')
            </div>
            <div class="mt-3">
                @include('livewire.reports._saved-filters')
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="app-table">
                <thead>
                    <tr>
                        <th class="rc-number">No. Opname</th>
                        <th class="rc-date">Tanggal</th>
                        <th class="rc-warehouse">Warehouse</th>
                        <th class="rc-location">Lokasi</th>
                        <th class="rc-sku">SKU</th>
                        <th class="rc-item">Barang</th>
                        <th class="rc-system_qty text-right">System</th>
                        <th class="rc-physical_qty text-right">Fisik</th>
                        <th class="rc-difference text-right">Selisih</th>
                        <th class="rc-reason">Alasan</th>
                        <th class="rc-status">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        @php $diff = (int) $row->difference; @endphp
                        <tr>
                            <td class="rc-number whitespace-nowrap font-medium">{{ $row->opname_number }}</td>
                            <td class="rc-date whitespace-nowrap text-app-muted">{{ \Illuminate\Support\Carbon::parse($row->opname_date)->format('d M Y') }}</td>
                            <td class="rc-warehouse whitespace-nowrap text-app-muted">{{ $row->warehouse_name }}</td>
                            <td class="rc-location whitespace-nowrap text-app-muted">{{ $row->location_code ?? '-' }}</td>
                            <td class="rc-sku whitespace-nowrap font-medium">{{ $row->sku }}</td>
                            <td class="rc-item">{{ $row->item_name }}</td>
                            <td class="rc-system_qty whitespace-nowrap text-right">{{ number_format((int) $row->system_quantity) }}</td>
                            <td class="rc-physical_qty whitespace-nowrap text-right">{{ $row->physical_quantity !== null ? number_format((int) $row->physical_quantity) : '-' }}</td>
                            <td class="rc-difference whitespace-nowrap text-right {{ $diffClass($diff) }}">{{ $diff > 0 ? '+' : '' }}{{ number_format($diff) }}</td>
                            <td class="rc-reason text-app-muted">{{ $row->reason ?? '-' }}</td>
                            <td class="rc-status whitespace-nowrap"><x-ui.status-badge :status="$row->status" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="11"><x-ui.empty-state title="Tidak ada data opname" message="Belum ada data stock opname yang cocok dengan filter." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($rows->hasPages())<div class="border-t border-app-border px-4 py-3">{{ $rows->links() }}</div>@endif
    </x-ui.card>
</div>
