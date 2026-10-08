<div>
    <x-ui.page-header title="Detail Sales Order" subtitle="{{ $order->number }}">
        <x-slot:actions>
            <a href="{{ route('sales-orders.index') }}" class="app-btn app-btn-secondary">Kembali</a>
            @if($order->status === 'draft')
                @can('update', $order)
                    <a href="{{ route('sales-orders.edit', $order) }}" class="app-btn app-btn-primary">Edit</a>
                @endcan
            @endif
            @if(in_array($order->status, ['approved','partial'], true) && auth()->user()?->can('goods_issue.create'))
                <a href="{{ route('goods-issues.create', ['so' => $order->id]) }}" target="_blank" class="app-btn app-btn-primary">Buat Barang Keluar dari SO</a>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <x-ui.card>
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="app-card-title">Header</h2>
                    <div class="flex items-center gap-2">
                        <x-ui.status-badge :status="$order->status" />
                    </div>
                </div>
                <dl class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div><dt class="text-xs font-semibold uppercase tracking-wider text-app-muted">Number</dt><dd class="mt-1 text-sm font-medium text-app-text">{{ $order->number }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wider text-app-muted">Tanggal SO</dt><dd class="mt-1 text-sm text-app-text">{{ $order->order_date?->format('d M Y') }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wider text-app-muted">Estimasi Kirim</dt><dd class="mt-1 text-sm text-app-text">{{ $order->expected_date?->format('d M Y') ?? '-' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wider text-app-muted">Customer</dt><dd class="mt-1 text-sm text-app-text">{{ $order->customer?->name ?? '-' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wider text-app-muted">Warehouse</dt><dd class="mt-1 text-sm text-app-text">{{ $order->warehouse?->name ?? '-' }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-xs font-semibold uppercase tracking-wider text-app-muted">Catatan</dt><dd class="mt-1 text-sm text-app-text">{{ $order->notes ?? '-' }}</dd></div>
                    @if($order->rejection_reason)
                        <div class="sm:col-span-2"><dt class="text-xs font-semibold uppercase tracking-wider text-app-muted">Rejection Reason</dt><dd class="mt-1 text-sm font-medium text-rose-600 dark:text-rose-400">{{ $order->rejection_reason }}</dd></div>
                    @endif
                </dl>
            </x-ui.card>

            <x-ui.card padding="p-0">
                <div class="app-card-header">
                    <h2 class="app-card-title">Detail Barang</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="app-table">
                        <thead>
                            <tr>
                                <th>Barang</th>
                                <th class="text-right">Qty Pesan</th>
                                <th class="text-right">Terpenuhi</th>
                                <th class="text-right">Sisa</th>
                                <th>Unit</th>
                                <th class="text-right">Harga</th>
                                <th class="text-right">Subtotal</th>
                                <th>Catatan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($order->items as $row)
                                @php $rem = max(0, (int) $row->quantity - (int) $row->fulfilled_quantity); @endphp
                                <tr>
                                    <td class="text-app-text">{{ $row->item?->sku ?? '-' }} — {{ $row->item?->name ?? '-' }}</td>
                                    <td class="text-right font-medium text-app-text">{{ number_format($row->quantity) }}</td>
                                    <td class="text-right">
                                        <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ (int) $row->fulfilled_quantity === 0 ? 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300' : ((int) $row->fulfilled_quantity >= (int) $row->quantity ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300') }}">{{ number_format($row->fulfilled_quantity) }}</span>
                                    </td>
                                    <td class="text-right font-medium {{ $rem > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400' }}">{{ number_format($rem) }}</td>
                                    <td class="text-app-muted">{{ $row->unit?->code ?? $row->unit?->name ?? '-' }}</td>
                                    <td class="text-right text-app-muted">{{ number_format((float) $row->unit_price, 0, ',', '.') }}</td>
                                    <td class="text-right font-medium text-app-text">{{ number_format((float) $row->quantity * (float) $row->unit_price, 0, ',', '.') }}</td>
                                    <td class="text-app-muted">{{ $row->notes ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="border-t border-app-border bg-app-surface-2/50">
                                <td colspan="6" class="text-right text-xs font-semibold uppercase tracking-wider text-app-muted">Total</td>
                                <td class="text-right text-sm font-bold text-app-text">{{ number_format($order->items->sum(fn($r) => (float) $r->quantity * (float) $r->unit_price), 0, ',', '.') }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                @if($order->items->isEmpty())
                    <x-ui.empty-state title="Tidak ada detail" message="SO ini belum memiliki baris barang." />
                @endif
            </x-ui.card>

            @if($order->goodsIssues->isNotEmpty())
                <x-ui.card padding="p-0">
                    <div class="app-card-header">
                        <h2 class="app-card-title">Barang Keluar Terkait</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="app-table">
                            <thead>
                                <tr>
                                    <th>Number</th>
                                    <th>Tanggal</th>
                                    <th>Status</th>
                                    <th class="text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($order->goodsIssues as $gi)
                                    <tr>
                                        <td class="whitespace-nowrap font-medium text-app-text">{{ $gi->number }}</td>
                                        <td class="whitespace-nowrap text-app-muted">{{ $gi->transaction_date?->format('d M Y') }}</td>
                                        <td class="whitespace-nowrap"><x-ui.status-badge :status="$gi->status" /></td>
                                        <td class="whitespace-nowrap text-right">
                                            <a href="{{ route('goods-issues.show', $gi) }}" class="app-btn app-btn-secondary px-2.5 py-1.5 text-xs">View</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-ui.card>
            @endif
        </div>

        <div class="space-y-4">
            <x-ui.card>
                <h2 class="app-card-title">Status Timeline</h2>
                <div class="relative mt-4 pl-6">
                    <div class="absolute bottom-2 left-[5px] top-2 w-px bg-app-border"></div>
                    @php
                        $steps = ['draft' => 'Draft', 'submitted' => 'Submitted', 'approved' => 'Approved', 'partial' => 'Partial', 'fulfilled' => 'Fulfilled', 'closed' => 'Closed'];
                        $current = $order->status;
                    @endphp
                    @foreach ($steps as $key => $label)
                        @php $active = $current === $key; $past = array_search($current, array_keys($steps)) !== false && array_search($key, array_keys($steps)) <= array_search($current, array_keys($steps)); @endphp
                        <div class="relative flex items-center gap-3 py-1.5 text-sm {{ $active ? 'font-semibold text-primary-600 dark:text-primary-400' : ($past ? 'text-app-text' : 'text-app-muted') }}">
                            <span class="absolute -left-6 flex h-3 w-3 items-center justify-center rounded-full ring-2 ring-app-surface {{ $past ? 'bg-emerald-500 ring-emerald-200 dark:ring-emerald-900/40' : 'bg-app-border ring-app-surface-2' }}"></span>
                            {{ $label }}
                        </div>
                    @endforeach
                    @if($order->status === 'rejected')
                        <div class="relative flex items-center gap-3 py-1.5 text-sm font-semibold text-rose-600 dark:text-rose-400">
                            <span class="absolute -left-6 flex h-3 w-3 rounded-full bg-rose-500 ring-2 ring-rose-200 dark:ring-rose-900/40"></span>
                            Rejected
                        </div>
                    @endif
                </div>
            </x-ui.card>

            <x-ui.card>
                <h2 class="app-card-title">Audit</h2>
                <dl class="mt-3 space-y-2 text-sm">
                    <div class="flex justify-between gap-2"><dt class="text-app-muted">Created</dt><dd class="text-app-text">{{ $order->creator?->name ?? '-' }} {{ to_display_tz($order->created_at)?->format('d M Y H:i') }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-app-muted">Submitted</dt><dd class="text-app-text">{{ $order->submitter?->name ?? '-' }} {{ to_display_tz($order->submitted_at)?->format('d M Y H:i') ?? '-' }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-app-muted">Approved</dt><dd class="text-app-text">{{ $order->approver?->name ?? '-' }} {{ to_display_tz($order->approved_at)?->format('d M Y H:i') ?? '-' }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-app-muted">Rejected</dt><dd class="text-app-text">{{ $order->rejecter?->name ?? '-' }} {{ to_display_tz($order->rejected_at)?->format('d M Y H:i') ?? '-' }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-app-muted">Closed</dt><dd class="text-app-text">{{ $order->closer?->name ?? '-' }} {{ to_display_tz($order->closed_at)?->format('d M Y H:i') ?? '-' }}</dd></div>
                </dl>
            </x-ui.card>

            <x-ui.card>
                <h2 class="app-card-title">Actions</h2>
                <div class="mt-3 flex flex-col gap-2">
                    @can('submit', $order)
                        @if($order->status === 'draft')
                            <x-ui.confirm action="submit" title="Submit SO" message="Kirim SO ini untuk disetujui?" confirm-label="Submit" class="app-btn app-btn-primary w-full">Submit</x-ui.confirm>
                        @endif
                    @endcan
                    @can('approve', $order)
                        @if($order->status === 'submitted')
                            <x-ui.confirm action="approve" title="Setujui SO" message="SO akan disetujui." confirm-label="Approve" class="app-btn app-btn-primary w-full">Approve</x-ui.confirm>
                            <div class="rounded-lg border border-app-border bg-app-surface-2/50 p-3">
                                <label class="app-label">Rejection reason</label>
                                <textarea wire:model="rejectionReason" rows="2" class="app-textarea mt-1" placeholder="Alasan penolakan..."></textarea>
                                @error('rejectionReason') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                                <x-ui.confirm action="reject" title="Tolak SO" message="Berikan alasan penolakan lalu tolak SO ini." confirm-label="Reject" variant="danger" class="app-btn app-btn-danger mt-2 w-full">Reject</x-ui.confirm>
                            </div>
                        @endif
                    @endcan
                    @can('close', $order)
                        @if($order->status === 'fulfilled')
                            <x-ui.confirm action="close" title="Close SO" message="SO akan ditutup. Status tidak bisa kembali." confirm-label="Close" class="app-btn app-btn-primary w-full">Close</x-ui.confirm>
                        @endif
                    @endcan
                </div>
            </x-ui.card>

            <div x-data="{ open: false }">
                <x-ui.card>
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="app-card-title">Riwayat Audit</h2>
                        <button type="button" @click="open = true" class="app-btn app-btn-secondary app-btn-sm">Riwayat</button>
                    </div>
                    <p class="mt-1 text-xs text-app-muted">Jejak perubahan SO ini.</p>
                </x-ui.card>
                <div x-show="open" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center p-4" role="dialog" aria-modal="true">
                    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="open = false"></div>
                    <div class="relative flex max-h-[90vh] w-full max-w-3xl flex-col rounded-xl border border-app-border bg-app-surface shadow-popover">
                        <div class="flex items-center justify-between border-b border-app-border px-5 py-3">
                            <h3 class="text-base font-semibold text-app-text">Riwayat Audit</h3>
                            <button type="button" @click="open = false" class="app-btn app-btn-ghost !p-1.5 text-lg leading-none">&times;</button>
                        </div>
                        <div class="overflow-y-auto p-5">
                            <x-ui.audit-history :auditableType="\App\Models\SalesOrder::class" :auditableId="$order->id" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
