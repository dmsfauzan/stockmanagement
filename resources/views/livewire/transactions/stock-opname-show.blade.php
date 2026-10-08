<div>
    <x-ui.page-header title="Detail Stock Opname" subtitle="{{ $opname->number }}">
        <x-slot:actions>
            <a href="{{ route('stock-opnames.index') }}" class="app-btn app-btn-secondary">Kembali</a>
            @if($opname->status === 'draft')
                @can('update', $opname)
                    <a href="{{ route('stock-opnames.edit', $opname) }}" class="app-btn app-btn-primary">Edit</a>
                @endcan
            @endif
            @if(in_array($opname->status, ['draft','counting'], true))
                @can('create', App\Models\StockOpname::class)
                    <a href="{{ route('stock-opnames.count', $opname) }}" class="app-btn app-btn-primary">Counting</a>
                @endcan
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <x-ui.card>
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="app-card-title">Header</h2>
                    <x-ui.status-badge :status="$opname->status" />
                </div>
                <dl class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div><dt class="text-xs font-semibold uppercase tracking-wider text-app-muted">Number</dt><dd class="mt-1 text-sm font-medium text-app-text">{{ $opname->number }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wider text-app-muted">Tanggal</dt><dd class="mt-1 text-sm text-app-text">{{ $opname->opname_date?->format('d M Y') }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wider text-app-muted">Warehouse</dt><dd class="mt-1 text-sm text-app-text">{{ $opname->warehouse?->name ?? '-' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-wider text-app-muted">Location</dt><dd class="mt-1 text-sm text-app-text">{{ $opname->location?->fullPath() ?? $opname->location?->code ?? 'Semua Lokasi' }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-xs font-semibold uppercase tracking-wider text-app-muted">Catatan</dt><dd class="mt-1 text-sm text-app-text">{{ $opname->notes ?? '-' }}</dd></div>
                    @if($opname->rejection_reason)
                        <div class="sm:col-span-2"><dt class="text-xs font-semibold uppercase tracking-wider text-app-muted">Rejection Reason</dt><dd class="mt-1 text-sm font-medium text-rose-600 dark:text-rose-400">{{ $opname->rejection_reason }}</dd></div>
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
                                <th class="text-right">Physical Qty</th>
                                <th class="text-right">Difference</th>
                                <th>Alasan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($opname->items as $row)
                                <tr>
                                    <td class="text-app-text">{{ $row->item?->sku ?? '-' }} — {{ $row->item?->name ?? '-' }}</td>
                                    <td class="text-right text-app-muted">{{ number_format($row->system_quantity) }}</td>
                                    <td class="text-right font-medium text-app-text">{{ $row->physical_quantity === null ? '-' : number_format($row->physical_quantity) }}</td>
                                    <td class="text-right">
                                        @if($row->difference > 0)
                                            <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">+{{ number_format($row->difference) }}</span>
                                        @elseif($row->difference < 0)
                                            <span class="inline-flex rounded-full bg-rose-100 px-2.5 py-0.5 text-xs font-semibold text-rose-700 dark:bg-rose-900/30 dark:text-rose-300">{{ number_format($row->difference) }}</span>
                                        @else
                                            <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-600 dark:bg-slate-700 dark:text-slate-300">0</span>
                                        @endif
                                    </td>
                                    <td class="text-app-muted">{{ $row->reason ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if($opname->items->isEmpty())
                    <x-ui.empty-state title="Tidak ada detail" message="Opname ini belum memiliki baris barang." />
                @endif
            </x-ui.card>
        </div>

        <div class="space-y-4">
            <x-ui.card>
                <h2 class="app-card-title">Status Timeline</h2>
                <div class="relative mt-4 pl-6">
                    <div class="absolute bottom-2 left-[5px] top-2 w-px bg-app-border"></div>
                    @php
                        $steps = ['draft' => 'Draft', 'counting' => 'Counting', 'submitted' => 'Submitted', 'approved' => 'Approved', 'completed' => 'Completed'];
                        $order = array_keys($steps);
                        $current = $opname->status;
                        $currentIndex = array_search($current, $order, false);
                    @endphp
                    @foreach ($steps as $key => $label)
                        @php
                            $index = array_search($key, $order, false);
                            $active = $current === $key;
                            $past = $currentIndex !== false && $index !== false && $index < $currentIndex;
                        @endphp
                        <div class="relative flex items-center gap-3 py-1.5 text-sm {{ $active ? 'font-semibold text-primary-600 dark:text-primary-400' : ($past || $current === 'completed' ? 'text-app-text' : 'text-app-muted') }}">
                            <span class="absolute -left-6 flex h-3 w-3 items-center justify-center rounded-full ring-2 ring-app-surface {{ ($past || $current === 'completed') ? 'bg-emerald-500 ring-emerald-200 dark:ring-emerald-900/40' : 'bg-app-border ring-app-surface-2' }}"></span>
                            {{ $label }}
                        </div>
                    @endforeach
                    @if($opname->status === 'rejected')
                        <div class="relative flex items-center gap-3 py-1.5 text-sm font-semibold text-rose-600 dark:text-rose-400">
                            <span class="absolute -left-6 flex h-3 w-3 rounded-full bg-rose-500 ring-2 ring-rose-200 dark:ring-rose-900/40"></span>
                            Rejected
                        </div>
                    @endif
                </div>
            </x-ui.card>

            @if($opname->stockAdjustment)
                <x-ui.card>
                    <h2 class="app-card-title">Stock Adjustment</h2>
                    <p class="mt-2 text-sm text-app-muted">Penyesuaian otomatis dari opname ini:</p>
                    <a href="{{ route('stock-adjustments.show', $opname->stockAdjustment) }}" class="mt-2 inline-flex items-center gap-1 text-sm font-medium text-primary-600 hover:underline dark:text-primary-400">
                        {{ $opname->stockAdjustment->number }}
                    </a>
                </x-ui.card>
            @endif

            <x-ui.card>
                <h2 class="app-card-title">Audit</h2>
                <dl class="mt-3 space-y-2 text-sm">
                    <div class="flex justify-between gap-2"><dt class="text-app-muted">Created</dt><dd class="text-app-text">{{ $opname->creator?->name ?? '-' }} {{ to_display_tz($opname->created_at)?->format('d M Y H:i') }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-app-muted">Submitted</dt><dd class="text-app-text">{{ $opname->submitter?->name ?? '-' }} {{ to_display_tz($opname->submitted_at)?->format('d M Y H:i') ?? '-' }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-app-muted">Approved</dt><dd class="text-app-text">{{ $opname->approver?->name ?? '-' }} {{ to_display_tz($opname->approved_at)?->format('d M Y H:i') ?? '-' }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-app-muted">Rejected</dt><dd class="text-app-text">{{ $opname->rejecter?->name ?? '-' }} {{ to_display_tz($opname->rejected_at)?->format('d M Y H:i') ?? '-' }}</dd></div>
                </dl>
            </x-ui.card>

            <x-ui.card>
                <h2 class="app-card-title">Actions</h2>
                <div class="mt-3 flex flex-col gap-2">
                    @if($opname->status === 'draft')
                        @can('submit', $opname)
                            <x-ui.confirm action="startCounting" title="Mulai Counting" message="Ubah status opname menjadi counting?" confirm-label="Mulai" class="app-btn app-btn-secondary w-full">Mulai Counting</x-ui.confirm>
                        @endcan
                        @can('update', $opname)
                            <x-ui.confirm action="delete" title="Hapus Opname" message="Hapus draft opname ini beserta seluruh itemnya?" confirm-label="Hapus" variant="danger" class="app-btn app-btn-danger w-full">Hapus</x-ui.confirm>
                        @endcan
                    @endif

                    @if(in_array($opname->status, ['draft','counting'], true))
                        @can('submit', $opname)
                            <x-ui.confirm action="submit" title="Ajukan Opname" message="Kirim opname ini untuk disetujui?" confirm-label="Submit" class="app-btn app-btn-primary w-full">Submit</x-ui.confirm>
                        @endcan
                    @endif

                    @can('approve', $opname)
                        @if($opname->status === 'submitted')
                            <x-ui.confirm action="approveAndComplete" title="Setujui & Posting" message="Opname akan disetujui, adjustment dibuat otomatis, dan stok diposting. Lanjutkan?" confirm-label="Approve & Complete" class="app-btn app-btn-primary w-full">Approve &amp; Complete</x-ui.confirm>
                            <div class="rounded-lg border border-app-border bg-app-surface-2/50 p-3">
                                <label class="app-label">Rejection reason</label>
                                <textarea wire:model="rejectionReason" rows="2" class="app-textarea mt-1" placeholder="Alasan penolakan..."></textarea>
                                @error('rejectionReason') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                                <x-ui.confirm action="reject" title="Tolak Opname" message="Berikan alasan penolakan lalu tolak opname ini." confirm-label="Reject" variant="danger" class="app-btn app-btn-danger mt-2 w-full">Reject</x-ui.confirm>
                            </div>
                        @endif
                    @endcan

                    @if(in_array($opname->status, ['rejected','completed'], true))
                        <p class="text-sm text-app-muted">Tidak ada aksi tersedia untuk status ini.</p>
                    @endif
                </div>
            </x-ui.card>

            <div x-data="{ open: false }">
                <x-ui.card>
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="app-card-title">Riwayat Audit</h2>
                        <button type="button" @click="open = true" class="app-btn app-btn-secondary app-btn-sm">Riwayat</button>
                    </div>
                    <p class="mt-1 text-xs text-app-muted">Jejak perubahan opname ini.</p>
                </x-ui.card>
                <div x-show="open" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center p-4" role="dialog" aria-modal="true">
                    <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="open = false"></div>
                    <div class="relative flex max-h-[90vh] w-full max-w-3xl flex-col rounded-xl border border-app-border bg-app-surface shadow-popover">
                        <div class="flex items-center justify-between border-b border-app-border px-5 py-3">
                            <h3 class="text-base font-semibold text-app-text">Riwayat Audit</h3>
                            <button type="button" @click="open = false" class="app-btn app-btn-ghost !p-1.5 text-lg leading-none">&times;</button>
                        </div>
                        <div class="overflow-y-auto p-5">
                            <x-ui.audit-history :auditableType="\App\Models\StockOpname::class" :auditableId="$opname->id" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
