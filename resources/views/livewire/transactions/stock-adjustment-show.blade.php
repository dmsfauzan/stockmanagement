<div>
    <x-ui.page-header title="Detail Stock Adjustment" subtitle="{{ $adjustment->number }}">
        <x-slot:actions>
            <a href="{{ route('stock-adjustments.index') }}" class="app-btn app-btn-secondary">Kembali</a>
            @if($adjustment->status === 'draft')
                @can('update', $adjustment)
                    <a href="{{ route('stock-adjustments.edit', $adjustment) }}" class="app-btn app-btn-primary">Edit</a>
                @endcan
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <x-ui.card>
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="app-card-title">Header</h2>
                    <div class="flex items-center gap-2">
                        <x-ui.status-badge :status="$adjustment->status" />
                        @if($adjustment->isReversed())
                            <x-ui.status-badge status="reversed" label="REVERSED" />
                        @endif
                    </div>
                </div>
                <dl class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div><dt class="text-xs font-semibold uppercase tracking-wider text-app-muted">Number</dt><dd class="mt-1 text-sm font-medium text-app-text">{{ $adjustment->number }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wider text-app-muted">Tanggal</dt><dd class="mt-1 text-sm text-app-text">{{ $adjustment->transaction_date?->format('d M Y') }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wider text-app-muted">Warehouse</dt><dd class="mt-1 text-sm text-app-text">{{ $adjustment->warehouse?->name ?? '-' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wider text-app-muted">Location</dt><dd class="mt-1 text-sm text-app-text">{{ $adjustment->location?->fullPath() ?? $adjustment->location?->code ?? '-' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wider text-app-muted">Reason</dt><dd class="mt-1 text-sm text-app-text">{{ $adjustment->reason ?? '-' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wider text-app-muted">Attachment</dt><dd class="mt-1 text-sm text-app-text">@if($adjustment->attachment)<a href="{{ Storage::disk('public')->url($adjustment->attachment) }}" target="_blank" class="text-primary-600 hover:underline dark:text-primary-400">Lihat File</a>@else - @endif</dd></div>
                    <div class="sm:col-span-2"><dt class="text-xs font-semibold uppercase tracking-wider text-app-muted">Catatan</dt><dd class="mt-1 text-sm text-app-text">{{ $adjustment->notes ?? '-' }}</dd></div>
                    @if($adjustment->rejection_reason)
                        <div class="sm:col-span-2"><dt class="text-xs font-semibold uppercase tracking-wider text-app-muted">Rejection Reason</dt><dd class="mt-1 text-sm font-medium text-rose-600 dark:text-rose-400">{{ $adjustment->rejection_reason }}</dd></div>
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
                                <th class="text-right">System Qty</th>
                                <th class="text-right">Actual Qty</th>
                                <th class="text-right">Difference</th>
                                <th>Catatan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($adjustment->items as $row)
                                <tr>
                                    <td class="text-app-text">{{ $row->item?->sku ?? '-' }} — {{ $row->item?->name ?? '-' }}</td>
                                    <td class="text-right text-app-muted">{{ number_format($row->system_quantity) }}</td>
                                    <td class="text-right font-medium text-app-text">{{ number_format($row->actual_quantity) }}</td>
                                    <td class="text-right">
                                        @if($row->difference > 0)
                                            <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">+{{ number_format($row->difference) }}</span>
                                        @elseif($row->difference < 0)
                                            <span class="inline-flex rounded-full bg-rose-100 px-2.5 py-0.5 text-xs font-semibold text-rose-700 dark:bg-rose-900/30 dark:text-rose-300">{{ number_format($row->difference) }}</span>
                                        @else
                                            <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-600 dark:bg-slate-700 dark:text-slate-300">0</span>
                                        @endif
                                    </td>
                                    <td class="text-app-muted">{{ $row->notes ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if($adjustment->items->isEmpty())
                    <x-ui.empty-state title="Tidak ada detail" message="Transaksi ini belum memiliki baris barang." />
                @endif
            </x-ui.card>
        </div>

        <div class="space-y-4">
            <x-ui.card>
                <h2 class="app-card-title">Status Timeline</h2>
                <div class="relative mt-4 pl-6">
                    <div class="absolute bottom-2 left-[5px] top-2 w-px bg-app-border"></div>
                    @php
                        $steps = ['draft' => 'Draft', 'submitted' => 'Submitted', 'approved' => 'Approved', 'posted' => 'Posted'];
                        $current = $adjustment->status;
                    @endphp
                    @foreach ($steps as $key => $label)
                        @php $active = $current === $key; $past = array_search($current, array_keys($steps)) !== false && array_search($key, array_keys($steps)) <= array_search($current, array_keys($steps)); @endphp
                        <div class="relative flex items-center gap-3 py-1.5 text-sm {{ $active ? 'font-semibold text-primary-600 dark:text-primary-400' : ($past ? 'text-app-text' : 'text-app-muted') }}">
                            <span class="absolute -left-6 flex h-3 w-3 items-center justify-center rounded-full ring-2 ring-app-surface {{ $past ? 'bg-emerald-500 ring-emerald-200 dark:ring-emerald-900/40' : 'bg-app-border ring-app-surface-2' }}"></span>
                            {{ $label }}
                        </div>
                    @endforeach
                    @if($adjustment->status === 'rejected')
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
                    <div class="flex justify-between gap-2"><dt class="text-app-muted">Created</dt><dd class="text-app-text">{{ $adjustment->creator?->name ?? '-' }} {{ $adjustment->created_at?->format('d M Y H:i') }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-app-muted">Submitted</dt><dd class="text-app-text">{{ $adjustment->submitter?->name ?? '-' }} {{ $adjustment->submitted_at?->format('d M Y H:i') ?? '-' }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-app-muted">Approved</dt><dd class="text-app-text">{{ $adjustment->approver?->name ?? '-' }} {{ $adjustment->approved_at?->format('d M Y H:i') ?? '-' }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-app-muted">Rejected</dt><dd class="text-app-text">{{ $adjustment->rejecter?->name ?? '-' }} {{ $adjustment->rejected_at?->format('d M Y H:i') ?? '-' }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-app-muted">Posted</dt><dd class="text-app-text">{{ $adjustment->poster?->name ?? '-' }} {{ $adjustment->posted_at?->format('d M Y H:i') ?? '-' }}</dd></div>
                </dl>
            </x-ui.card>

            <x-ui.card>
                <h2 class="app-card-title">Actions</h2>
                <div class="mt-3 flex flex-col gap-2">
                    @can('submit', $adjustment)
                        @if($adjustment->status === 'draft')
                            <x-ui.confirm action="submit" title="Submit Adjustment" message="Kirim transaksi ini untuk disetujui?" confirm-label="Submit" class="app-btn app-btn-primary w-full">Submit</x-ui.confirm>
                        @endif
                    @endcan
                    @can('approve', $adjustment)
                        @if($adjustment->status === 'submitted')
                            <x-ui.confirm action="approve" title="Setujui Adjustment" message="Transaksi akan disetujui dan siap untuk posting." confirm-label="Approve" class="app-btn app-btn-primary w-full">Approve</x-ui.confirm>
                            <div class="rounded-lg border border-app-border bg-app-surface-2/50 p-3">
                                <label class="app-label">Rejection reason</label>
                                <textarea wire:model="rejectionReason" rows="2" class="app-textarea mt-1" placeholder="Alasan penolakan..."></textarea>
                                @error('rejectionReason') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                                <x-ui.confirm action="reject" title="Tolak Adjustment" message="Berikan alasan penolakan lalu tolak transaksi ini." confirm-label="Reject" variant="danger" class="app-btn app-btn-danger mt-2 w-full">Reject</x-ui.confirm>
                            </div>
                        @endif
                    @endcan
                    @can('post', $adjustment)
                        @if($adjustment->status === 'approved')
                            <x-ui.confirm action="post" title="Posting Stock Adjustment" message="Posting adjustment ini akan mengubah Stock On Hand dan membuat ledger. Lanjutkan?" confirm-label="Post" class="app-btn app-btn-primary w-full">Post</x-ui.confirm>
                        @endif
                    @endcan
                </div>
            </x-ui.card>

            @if($adjustment->isReversed())
                <x-ui.card>
                    <h2 class="app-card-title">Reversal</h2>
                    <dl class="mt-3 space-y-2 text-sm">
                        <div class="flex justify-between gap-2"><dt class="text-app-muted">Reversed By</dt><dd class="text-app-text">{{ $adjustment->reverser?->name ?? '-' }}</dd></div>
                        <div class="flex justify-between gap-2"><dt class="text-app-muted">Reversed At</dt><dd class="text-app-text">{{ $adjustment->reversed_at?->format('d M Y H:i') ?? '-' }}</dd></div>
                        <div class="sm:col-span-2"><dt class="text-app-muted">Reason</dt><dd class="mt-1 text-sm text-app-text">{{ $adjustment->reversal_reason ?? '-' }}</dd></div>
                    </dl>
                </x-ui.card>
            @else
                @can('reverse', $adjustment)
                    @if($adjustment->status === 'posted')
                        <x-ui.card>
                            <h2 class="app-card-title">Reversal</h2>
                            <div class="mt-3 rounded-lg border border-app-border bg-app-surface-2/50 p-3">
                                <label class="app-label">Reversal reason</label>
                                <textarea wire:model="reversalReason" rows="2" class="app-textarea mt-1" placeholder="Alasan reversal..."></textarea>
                                @error('reversalReason') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                                <x-ui.confirm action="reverse" title="Reversal" :message="'Reversal akan membalikkan jurnal stok yang telah diposting. '.$adjustment->items->count().' baris. Lanjutkan?'" confirm-label="Ok, Reversal" variant="danger" class="app-btn app-btn-danger mt-2 w-full">Reversal</x-ui.confirm>
                            </div>
                        </x-ui.card>
                    @endif
                @endcan
            @endif

            <div x-data="{ open: false }">
                <x-ui.card>
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="app-card-title">Riwayat Audit</h2>
                        <button type="button" @click="open = true" class="app-btn app-btn-secondary app-btn-sm">Riwayat</button>
                    </div>
                    <p class="mt-1 text-xs text-app-muted">Jejak perubahan transaksi ini.</p>
                </x-ui.card>
                <div x-show="open" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center p-4" role="dialog" aria-modal="true">
                    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="open = false"></div>
                    <div class="relative flex max-h-[90vh] w-full max-w-3xl flex-col rounded-xl border border-app-border bg-app-surface shadow-popover">
                        <div class="flex items-center justify-between border-b border-app-border px-5 py-3">
                            <h3 class="text-base font-semibold text-app-text">Riwayat Audit</h3>
                            <button type="button" @click="open = false" class="app-btn app-btn-ghost !p-1.5 text-lg leading-none">&times;</button>
                        </div>
                        <div class="overflow-y-auto p-5">
                            <x-ui.audit-history :auditableType="\App\Models\StockAdjustment::class" :auditableId="$adjustment->id" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
