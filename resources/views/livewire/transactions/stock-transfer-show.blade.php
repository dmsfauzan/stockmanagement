<div>
    <x-ui.page-header title="Detail Transfer Barang" subtitle="{{ $transfer->number }}">
        <x-slot:actions>
            <a href="{{ route('stock-transfers.index') }}" class="app-btn app-btn-secondary">{{ __('Kembali') }}</a>
            @if($transfer->status === 'draft')
                @can('update', $transfer)
                    <a href="{{ route('stock-transfers.edit', $transfer) }}" class="app-btn app-btn-primary">Edit</a>
                @endcan
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <x-ui.card>
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="app-card-title">Header</h2>
                    <x-ui.status-badge :status="$transfer->status" />
                </div>
                <dl class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div><dt class="text-xs font-semibold uppercase tracking-wider text-app-muted">Number</dt><dd class="mt-1 text-sm font-medium text-app-text">{{ $transfer->number }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wider text-app-muted">{{ __('Tanggal') }}</dt><dd class="mt-1 text-sm text-app-text">{{ $transfer->transfer_date?->format('d M Y') }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wider text-app-muted">Dari Warehouse</dt><dd class="mt-1 text-sm text-app-text">{{ $transfer->fromWarehouse?->name ?? '-' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wider text-app-muted">Dari Location</dt><dd class="mt-1 text-sm text-app-text">{{ $transfer->fromLocation?->fullPath() ?? $transfer->fromLocation?->code ?? '-' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wider text-app-muted">Ke Warehouse</dt><dd class="mt-1 text-sm text-app-text">{{ $transfer->toWarehouse?->name ?? '-' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wider text-app-muted">Ke Location</dt><dd class="mt-1 text-sm text-app-text">{{ $transfer->toLocation?->fullPath() ?? $transfer->toLocation?->code ?? '-' }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-xs font-semibold uppercase tracking-wider text-app-muted">{{ __('Catatan') }}</dt><dd class="mt-1 text-sm text-app-text">{{ $transfer->notes ?? '-' }}</dd></div>
                    @if($transfer->rejection_reason)
                        <div class="sm:col-span-2"><dt class="text-xs font-semibold uppercase tracking-wider text-app-muted">Rejection Reason</dt><dd class="mt-1 text-sm font-medium text-rose-600 dark:text-rose-400">{{ $transfer->rejection_reason }}</dd></div>
                    @endif
                </dl>
            </x-ui.card>

            <x-ui.card padding="p-0">
                <div class="app-card-header">
                    <h2 class="app-card-title">{{ __('Detail Barang') }}</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="app-table">
                        <thead>
                            <tr>
                                <th>{{ __('Barang') }}</th>
                                <th class="text-right">Qty</th>
                                <th>Unit</th>
                                <th>{{ __('Catatan') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($transfer->items as $row)
                                <tr>
                                    <td class="text-app-text">{{ $row->item?->sku ?? '-' }} — {{ $row->item?->name ?? '-' }}</td>
                                    <td class="text-right font-medium text-app-text">{{ number_format($row->quantity) }}</td>
                                    <td class="text-app-muted">{{ $row->unit?->name ?? $row->unit?->code ?? '-' }}</td>
                                    <td class="text-app-muted">{{ $row->notes ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if($transfer->items->isEmpty())
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
                        $steps = ['draft' => 'Draft', 'requested' => 'Requested', 'approved' => 'Approved', 'in_transit' => 'In Transit', 'received' => 'Received', 'completed' => 'Completed'];
                        $current = $transfer->status;
                    @endphp
                    @foreach ($steps as $key => $label)
                        @php $active = $current === $key; $past = array_search($current, array_keys($steps)) !== false && array_search($key, array_keys($steps)) <= array_search($current, array_keys($steps)); @endphp
                        <div class="relative flex items-center gap-3 py-1.5 text-sm {{ $active ? 'font-semibold text-primary-600 dark:text-primary-400' : ($past ? 'text-app-text' : 'text-app-muted') }}">
                            <span class="absolute -left-6 flex h-3 w-3 items-center justify-center rounded-full ring-2 ring-app-surface {{ $past ? 'bg-emerald-500 ring-emerald-200 dark:ring-emerald-900/40' : 'bg-app-border ring-app-surface-2' }}"></span>
                            {{ $label }}
                        </div>
                    @endforeach
                    @if($transfer->status === 'rejected')
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
                    <div class="flex justify-between gap-2"><dt class="text-app-muted">Created</dt><dd class="text-app-text">{{ $transfer->creator?->name ?? '-' }} {{ to_display_tz($transfer->created_at)?->format('d M Y H:i') }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-app-muted">Requested</dt><dd class="text-app-text">{{ $transfer->requester?->name ?? '-' }} {{ to_display_tz($transfer->requested_at)?->format('d M Y H:i') ?? '-' }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-app-muted">Approved</dt><dd class="text-app-text">{{ $transfer->approver?->name ?? '-' }} {{ to_display_tz($transfer->approved_at)?->format('d M Y H:i') ?? '-' }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-app-muted">Received</dt><dd class="text-app-text">{{ $transfer->receiver?->name ?? '-' }} {{ to_display_tz($transfer->received_at)?->format('d M Y H:i') ?? '-' }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-app-muted">Completed</dt><dd class="text-app-text">{{ $transfer->completer?->name ?? '-' }} {{ to_display_tz($transfer->completed_at)?->format('d M Y H:i') ?? '-' }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-app-muted">Rejected</dt><dd class="text-app-text">{{ $transfer->rejecter?->name ?? '-' }} {{ to_display_tz($transfer->rejected_at)?->format('d M Y H:i') ?? '-' }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-app-muted">Shipped</dt><dd class="text-app-text">{{ $transfer->shipper?->name ?? '-' }} {{ to_display_tz($transfer->shipped_at)?->format('d M Y H:i') ?? '-' }}</dd></div>
                </dl>
            </x-ui.card>

            <x-ui.card>
                <h2 class="app-card-title">Actions</h2>
                <div class="mt-3 flex flex-col gap-2">
                    @can('request', $transfer)
                        @if($transfer->status === 'draft')
                            <x-ui.confirm action="request" title="Ajukan Transfer" message="Kirim transfer ini untuk disetujui?" confirm-label="Ajukan" class="app-btn app-btn-primary w-full">Request</x-ui.confirm>
                        @endif
                    @endcan
                    @can('approve', $transfer)
                        @if($transfer->status === 'requested')
                            <x-ui.confirm action="approve" title="Setujui Transfer" message="Transfer akan disetujui dan siap untuk dikirim." confirm-label="Approve" class="app-btn app-btn-primary w-full">{{ __('Approve') }}</x-ui.confirm>
                            <div class="rounded-lg border border-app-border bg-app-surface-2/50 p-3">
                                <label class="app-label">Rejection reason</label>
                                <textarea wire:model="rejectionReason" rows="2" class="app-textarea mt-1" placeholder="Alasan penolakan..."></textarea>
                                @error('rejectionReason') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                                <x-ui.confirm action="reject" title="Tolak Transfer" message="Berikan alasan penolakan lalu tolak transfer ini." confirm-label="Reject" variant="danger" class="app-btn app-btn-danger mt-2 w-full">{{ __('Reject') }}</x-ui.confirm>
                            </div>
                        @endif
                    @endcan
                    @if($transfer->status === 'approved')
                        @can('dispatch', $transfer)
                            <x-ui.confirm action="dispatchTransfer" title="Kirim Transfer" message="Transfer akan keluar dari lokasi asal dan mengurangi stok. Lanjutkan?" confirm-label="Dispatch" class="app-btn app-btn-primary w-full">Dispatch</x-ui.confirm>
                        @endcan
                    @endif
                    @if($transfer->status === 'in_transit')
                        @can('receive', $transfer)
                            <x-ui.confirm action="receive" title="Terima Transfer" message="Barang akan ditambahkan ke lokasi tujuan." confirm-label="Receive" class="app-btn app-btn-primary w-full">Receive</x-ui.confirm>
                        @endcan
                    @endif
                    @if($transfer->status === 'received')
                        @can('complete', $transfer)
                            <x-ui.confirm action="complete" title="Selesaikan Transfer" message="Selesaikan transfer ini?" confirm-label="Complete" class="app-btn app-btn-primary w-full">Complete</x-ui.confirm>
                        @endcan
                    @endif
                </div>
            </x-ui.card>

            <div x-data="{ open: false }">
                <x-ui.card>
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="app-card-title">{{ __('Riwayat Audit') }}</h2>
                        <button type="button" @click="open = true" class="app-btn app-btn-secondary app-btn-sm">{{ __('Riwayat') }}</button>
                    </div>
                    <p class="mt-1 text-xs text-app-muted">Jejak perubahan transfer ini.</p>
                </x-ui.card>
                <div x-show="open" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center p-4" role="dialog" aria-modal="true">
                    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="open = false"></div>
                    <div class="relative flex max-h-[90vh] w-full max-w-3xl flex-col rounded-xl border border-app-border bg-app-surface shadow-popover">
                        <div class="flex items-center justify-between border-b border-app-border px-5 py-3">
                            <h3 class="text-base font-semibold text-app-text">{{ __('Riwayat Audit') }}</h3>
                            <button type="button" @click="open = false" class="app-btn app-btn-ghost !p-1.5 text-lg leading-none">&times;</button>
                        </div>
                        <div class="overflow-y-auto p-5">
                            <x-ui.audit-history :auditableType="\App\Models\StockTransfer::class" :auditableId="$transfer->id" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
