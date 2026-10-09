<div>
    <x-ui.page-header title="Detail Retur Penjualan" :subtitle="$return->number">
        <x-slot:actions>
            <a href="{{ route('customer-returns.index') }}" class="app-btn app-btn-secondary">{{ __('Kembali') }}</a>
            @if($return->status === 'draft')
                @can('update', $return)
                    <a href="{{ route('customer-returns.edit', $return) }}" class="app-btn app-btn-primary">{{ __('Ubah') }}</a>
                @endcan
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <x-ui.card>
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="app-card-title">Header</h2>
                    <x-ui.status-badge :status="$return->status" />
                </div>
                <dl class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div><dt class="text-xs font-semibold uppercase text-app-muted">Number</dt><dd class="mt-1 text-sm font-medium text-app-text">{{ $return->number }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase text-app-muted">{{ __('Tanggal') }}</dt><dd class="mt-1 text-sm text-app-text">{{ $return->transaction_date?->format('d M Y') }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase text-app-muted">{{ __('Pelanggan') }}</dt><dd class="mt-1 text-sm text-app-text">{{ $return->customer?->name ?? '-' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase text-app-muted">Dokumen Asal</dt><dd class="mt-1 text-sm text-app-text">@if($return->goodsIssue)<a href="{{ route('goods-issues.show', $return->goodsIssue) }}" class="text-primary-600 hover:underline dark:text-primary-400">{{ $return->goodsIssue->number }}</a>@else - @endif</dd></div>
                    <div><dt class="text-xs font-semibold uppercase text-app-muted">{{ __('Gudang') }}</dt><dd class="mt-1 text-sm text-app-text">{{ $return->warehouse?->name ?? '-' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase text-app-muted">{{ __('Lokasi') }}</dt><dd class="mt-1 text-sm text-app-text">{{ $return->location?->code ?? '-' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase text-app-muted">Alasan</dt><dd class="mt-1 text-sm text-app-text">{{ $return->reason ?? '-' }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-xs font-semibold uppercase text-app-muted">{{ __('Catatan') }}</dt><dd class="mt-1 text-sm text-app-text">{{ $return->notes ?? '-' }}</dd></div>
                    @if($return->rejection_reason)
                        <div class="sm:col-span-2"><dt class="text-xs font-semibold uppercase text-app-muted">Rejection Reason</dt><dd class="mt-1 text-sm font-medium text-rose-600">{{ $return->rejection_reason }}</dd></div>
                    @endif
                    @if($return->reversal_reason)
                        <div class="sm:col-span-2"><dt class="text-xs font-semibold uppercase text-app-muted">Reversal Reason</dt><dd class="mt-1 text-sm font-medium text-amber-600">{{ $return->reversal_reason }}</dd></div>
                    @endif
                </dl>
            </x-ui.card>

            <x-ui.card padding="p-0">
                <div class="app-card-header"><h2 class="app-card-title">{{ __('Detail Barang') }}</h2></div>
                <div class="overflow-x-auto">
                    <table class="app-table">
                        <thead><tr><th>{{ __('Barang') }}</th><th class="text-right">Qty</th><th>Unit</th><th>{{ __('Lokasi') }}</th><th class="text-right">{{ __('Harga') }}</th><th>Batch</th><th>Serial</th></tr></thead>
                        <tbody>
                            @foreach ($return->items as $row)
                                <tr>
                                    <td class="text-app-text">{{ $row->item?->sku ?? '-' }} — {{ $row->item?->name ?? '-' }}</td>
                                    <td class="text-right font-medium text-app-text">{{ number_format($row->quantity) }}</td>
                                    <td class="text-app-muted">{{ $row->unit?->code ?? '-' }}</td>
                                    <td class="text-app-muted">{{ $row->location?->code ?? '-' }}</td>
                                    <td class="text-right text-app-muted">{{ number_format((float) $row->unit_cost, 0, ',', '.') }}</td>
                                    <td class="text-app-muted">{{ $row->batch_number ?? '-' }}</td>
                                    <td class="text-app-muted">{{ $row->serial_number ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if($return->items->isEmpty())
                    <x-ui.empty-state title="Tidak ada detail" message="Retur ini belum memiliki baris barang." />
                @endif
            </x-ui.card>
        </div>

        <div class="space-y-4">
            <x-ui.approval-history :model="$return" />

            <x-ui.card>
                <h2 class="app-card-title">Audit</h2>
                <dl class="mt-3 space-y-2 text-sm">
                    <div class="flex justify-between gap-2"><dt class="text-app-muted">Created</dt><dd class="text-app-text">{{ $return->creator?->name ?? '-' }} {{ to_display_tz($return->created_at)?->format('d M Y H:i') }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-app-muted">Posted</dt><dd class="text-app-text">{{ $return->poster?->name ?? '-' }} {{ to_display_tz($return->posted_at)?->format('d M Y H:i') ?? '-' }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-app-muted">Reversed</dt><dd class="text-app-text">{{ $return->reverser?->name ?? '-' }} {{ to_display_tz($return->reversed_at)?->format('d M Y H:i') ?? '-' }}</dd></div>
                </dl>
            </x-ui.card>

            <x-ui.card>
                <h2 class="app-card-title">Actions</h2>
                <div class="mt-3 flex flex-col gap-2">
                    @can('submit', $return)
                        @if($return->status === 'draft')
                            <x-ui.confirm action="submit" title="Ajukan Retur" message="Kirim retur ini untuk disetujui?" confirm-label="Submit" class="app-btn app-btn-primary w-full">{{ __('Submit') }}</x-ui.confirm>
                        @endif
                    @endcan
                    @can('approve', $return)
                        @if($return->status === 'submitted' && \App\Services\Workflow\DocumentApproval::canApprove($return, auth()->user()))
                            <x-ui.confirm action="approve" title="Setujui Retur" message="Retur akan disetujui." confirm-label="Approve" class="app-btn app-btn-primary w-full">{{ __('Approve') }}</x-ui.confirm>
                            <div class="rounded-lg border border-app-border bg-app-surface-2/50 p-3">
                                <label class="app-label">Rejection reason</label>
                                <textarea wire:model="rejectionReason" rows="2" class="app-textarea mt-1" placeholder="Alasan penolakan..."></textarea>
                                @error('rejectionReason') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                                <x-ui.confirm action="reject" title="Tolak Retur" message="Berikan alasan penolakan lalu tolak retur ini." confirm-label="Reject" variant="danger" class="app-btn app-btn-danger mt-2 w-full">{{ __('Reject') }}</x-ui.confirm>
                            </div>
                        @endif
                    @endcan
                    @can('post', $return)
                        @if($return->status === 'approved')
                            <x-ui.confirm action="post" title="Posting Retur" message="Posting retur ini? Stok akan bertambah." confirm-label="Post" class="app-btn app-btn-primary w-full">Post</x-ui.confirm>
                        @endif
                    @endcan
                    @can('reverse', $return)
                        @if($return->status === 'posted' && $return->reversed_at === null)
                            <div class="rounded-lg border border-app-border bg-app-surface-2/50 p-3">
                                <label class="app-label">Reversal reason</label>
                                <textarea wire:model="reversalReason" rows="2" class="app-textarea mt-1" placeholder="Alasan pembatalan..."></textarea>
                                @error('reversalReason') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                                <x-ui.confirm action="reverse" title="Reversal Retur" message="Batalkan retur ini?" confirm-label="Reversal" variant="danger" class="app-btn app-btn-danger mt-2 w-full">Reversal</x-ui.confirm>
                            </div>
                        @endif
                    @endcan
                </div>
            </x-ui.card>

            <div x-data="{ open: false }">
                <x-ui.card>
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="app-card-title">{{ __('Riwayat Audit') }}</h2>
                        <button type="button" @click="open = true" class="app-btn app-btn-secondary app-btn-sm">{{ __('Riwayat') }}</button>
                    </div>
                    <p class="mt-1 text-xs text-app-muted">Jejak perubahan retur ini.</p>
                </x-ui.card>
                <div x-show="open" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center p-4" role="dialog" aria-modal="true">
                    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="open = false"></div>
                    <div class="relative flex max-h-[90vh] w-full max-w-3xl flex-col rounded-xl border border-app-border bg-app-surface shadow-popover">
                        <div class="flex items-center justify-between border-b border-app-border px-5 py-3">
                            <h3 class="text-base font-semibold text-app-text">{{ __('Riwayat Audit') }}</h3>
                            <button type="button" @click="open = false" class="app-btn app-btn-ghost !p-1.5 text-lg leading-none">&times;</button>
                        </div>
                        <div class="overflow-y-auto p-5">
                            <x-ui.audit-history :auditableType="\App\Models\CustomerReturn::class" :auditableId="$return->id" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
