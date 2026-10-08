<div>
    <x-ui.page-header title="Perbandingan Gudang" subtitle="Agregat stok per warehouse — on hand, low/out, expired & H-30">
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

    <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-7">
        <x-ui.stat-card label="Total Distinct Items" :value="number_format($totals['items'])" tone="indigo" />
        <x-ui.stat-card label="On Hand (Σ)" :value="number_format($totals['on_hand'])" tone="slate" />
        <x-ui.stat-card label="Available (Σ)" :value="number_format($totals['available'])" tone="emerald" />
        <x-ui.stat-card label="Low (Σ)" :value="number_format($totals['low'])" tone="amber" />
        <x-ui.stat-card label="Out (Σ)" :value="number_format($totals['out'])" tone="rose" />
        <x-ui.stat-card label="Expired (Σ)" :value="number_format($totals['expired'])" tone="rose" />
        <x-ui.stat-card label="H-30 (Σ)" :value="number_format($totals['soon'])" tone="amber" />
    </div>

    <x-ui.card padding="p-0">
        <div class="border-b border-app-border p-4">
            <div class="flex items-center gap-3">
                <div class="relative flex-1">
                    <svg class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-app-muted" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.3-4.3M10 18a8 8 0 110-16 8 8 0 010 16z"/></svg>
                    <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari kode / nama gudang..." class="app-input pl-9">
                </div>
                <span class="hidden text-sm text-app-muted sm:inline">{{ $rows->count() }} gudang</span>
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
                        <th class="rc-code">{{ __('Kode') }}</th>
                        <th class="rc-warehouse">{{ __('Gudang') }}</th>
                        <th class="rc-total_items text-right">Total Item</th>
                        <th class="rc-on_hand text-right">On Hand</th>
                        <th class="rc-available text-right">Available</th>
                        <th class="rc-low text-right">Low</th>
                        <th class="rc-out text-right">Out</th>
                        <th class="rc-expired text-right">Expired</th>
                        <th class="rc-soon text-right">H-30</th>
                        <th class="rc-health text-center w-16">Sehat</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $r)
                        @php $total = max(1, (int)$r->low_count + (int)$r->out_count + 1); $healthy = 1 - (($r->low_count + $r->out_count) / max(1, $r->total_items)); @endphp
                        <tr>
                            <td class="rc-code whitespace-nowrap font-mono text-xs">{{ $r->code }}</td>
                            <td class="rc-warehouse whitespace-nowrap font-medium">{{ $r->name }}</td>
                            <td class="rc-total_items text-right">{{ number_format((int)$r->total_items) }}</td>
                            <td class="rc-on_hand text-right font-medium">{{ number_format((int)$r->total_on_hand) }}</td>
                            <td class="rc-available text-right">{{ number_format((int)$r->total_available) }}</td>
                            <td class="rc-low text-right"><span class="inline-flex h-6 min-w-7 items-center justify-center rounded-full px-2 text-xs font-bold {{ (int)$r->low_count > 0 ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300' : 'bg-slate-100 text-slate-500 dark:bg-slate-800' }}">{{ (int)$r->low_count }}</span></td>
                            <td class="rc-out text-right"><span class="inline-flex h-6 min-w-7 items-center justify-center rounded-full px-2 text-xs font-bold {{ (int)$r->out_count > 0 ? 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300' : 'bg-slate-100 text-slate-500 dark:bg-slate-800' }}">{{ (int)$r->out_count }}</span></td>
                            <td class="rc-expired text-right">{{ number_format((int)$r->expired_count) }}</td>
                            <td class="rc-soon text-right">{{ number_format((int)$r->soon_count) }}</td>
                            <td class="rc-health text-center">
                                <div class="flex items-center justify-center gap-1">
                                    <div class="h-1.5 w-16 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"><div class="h-full rounded-full {{ ($r->out_count + $r->low_count) === 0 ? 'bg-emerald-500' : (($r->out_count > 0) ? 'bg-rose-500' : 'bg-amber-500') }}" style="width: {{ number_format(max(8, 100 * $healthy), 0) }}%"></div></div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10"><x-ui.empty-state title="Tidak ada gudang" message="Belum ada gudang yang cocok dengan pencarian." /></td></tr>
                    @endforelse
                </tbody>
                @if($rows->isNotEmpty())
                    <tfoot><tr class="border-t-2 border-app-border font-semibold"><td class="rc-warehouse" colspan="2">TOTAL</td><td class="rc-total_items text-right">{{ number_format($totals['items']) }}</td><td class="rc-on_hand text-right">{{ number_format($totals['on_hand']) }}</td><td class="rc-available text-right">{{ number_format($totals['available']) }}</td><td class="rc-low text-right">{{ number_format($totals['low']) }}</td><td class="rc-out text-right">{{ number_format($totals['out']) }}</td><td class="rc-expired text-right">{{ number_format($totals['expired']) }}</td><td class="rc-soon text-right">{{ number_format($totals['soon']) }}</td><td class="rc-health"></td></tr></tfoot>
                @endif
            </table>
        </div>
    </x-ui.card>
</div>
