<div>
    <x-ui.page-header title="Tarik Batch" subtitle="Tandai batch / serial bermasalah dan lihat dampaknya" />

    <x-ui.card class="mb-4">
        <form wire:submit="search" class="flex flex-wrap items-end gap-3">
            <label>
                <span class="app-label">{{ __('Tipe') }}</span>
                <select wire:model="mode" class="app-select sm:w-auto">
                    <option value="batch">Batch / Lot</option>
                    <option value="serial">Serial Number</option>
                </select>
            </label>
            <label class="min-w-[16rem] flex-1">
                <span class="app-label">{{ __('Nomor') }}</span>
                <input type="text" wire:model="query" class="app-input" placeholder="{{ $mode === 'serial' ? 'SN-...' : 'BATCH-...' }}">
            </label>
            <button type="submit" class="app-btn app-btn-primary">{{ __('Telusuri Dampak') }}</button>
        </form>
    </x-ui.card>

    @if ($impact)
        <x-ui.card class="mb-4">
            <div class="mb-3 grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div class="app-card p-4"><p class="text-xs font-semibold uppercase text-app-muted">Stok tersisa</p><p class="mt-2 text-2xl font-semibold text-app-text">{{ number_format($impact['on_hand']) }}</p></div>
                <div class="app-card p-4"><p class="text-xs font-semibold uppercase text-app-muted">Dokumen terdampak</p><p class="mt-2 text-2xl font-semibold text-app-text">{{ number_format(count($impact['documents'])) }}</p></div>
                <div class="app-card p-4">
                    <p class="text-xs font-semibold uppercase text-app-muted">Status Penarikan</p>
                    <p class="mt-2">@if ($impact['active_recall'])<x-ui.status-badge status="recalled" />@else<span class="text-sm text-app-muted">{{ __('Tidak ada') }}</span>@endif</p>
                </div>
            </div>

            @if (count($impact['stock']) > 0)
                <div class="overflow-x-auto">
                    <table class="app-table">
                        <thead><tr><th>SKU</th><th>{{ __('Barang') }}</th><th>Warehouse</th><th>Lokasi</th><th class="text-right">{{ __('Qty') }}</th></tr></thead>
                        <tbody>
                            @foreach ($impact['stock'] as $row)
                                <tr>
                                    <td class="font-medium text-app-text">{{ $row['sku'] }}</td>
                                    <td class="text-app-text">{{ $row['item_name'] }}</td>
                                    <td class="text-app-muted">{{ $row['warehouse'] }}</td>
                                    <td class="text-app-muted">{{ $row['location'] }}</td>
                                    <td class="text-right">{{ number_format($row['quantity']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if (count($impact['documents']) > 0)
                <h3 class="app-card-title mb-2 mt-5">{{ __('Dokumen Terdampak') }}</h3>
                <div class="overflow-x-auto">
                    <table class="app-table">
                        <thead><tr><th>{{ __('Tanggal') }}</th><th>{{ __('Tipe') }}</th><th>{{ __('Referensi') }}</th><th>Warehouse</th><th class="text-right">In</th><th class="text-right">Out</th></tr></thead>
                        <tbody>
                            @foreach ($impact['documents'] as $doc)
                                <tr>
                                    <td class="whitespace-nowrap text-app-muted">{{ $doc['date'] ? \Illuminate\Support\Carbon::parse($doc['date'])->format('d M Y H:i') : '-' }}</td>
                                    <td><x-ui.status-badge :status="$doc['transaction_type']" /></td>
                                    <td class="text-app-text">{{ $doc['reference'] }}</td>
                                    <td class="text-app-muted">{{ $doc['warehouse'] }}</td>
                                    <td class="text-right">{{ number_format($doc['quantity_in']) }}</td>
                                    <td class="text-right">{{ number_format($doc['quantity_out']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if (! $impact['active_recall'])
                <div class="mt-5">
                    <label class="app-label mb-1.5">{{ __('Alasan Penarikan') }}<span class="text-rose-500">*</span></label>
                    <textarea wire:model="reason" rows="2" class="app-textarea" placeholder="{{ __('Jelaskan alasan penarikan') }}..."></textarea>
                    @error('reason') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    <button type="button" wire:click="recall" wire:confirm="{{ __('Tandai batch ini sebagai ditarik?') }}" class="app-btn app-btn-primary mt-3">{{ __('Tandai Ditarik') }}</button>
                </div>
            @else
                <div class="mt-5">
                    <button type="button" wire:click="lift({{ $impact['active_recall']['id'] }})" wire:confirm="{{ __('Cabut penarikan ini?') }}" class="app-btn app-btn-secondary">{{ __('Cabut Penarikan') }}</button>
                </div>
            @endif
        </x-ui.card>
    @endif

    <x-ui.card padding="p-0">
        <div class="app-card-header flex-col items-stretch gap-3 sm:flex-row">
            <div class="flex flex-1 flex-wrap gap-3">
                <select wire:model.live="recalledFilter" class="app-select sm:w-auto">
                    <option value="">{{ __('Semua Penarikan') }}</option>
                    <option value="active">{{ __('Aktif') }}</option>
                    <option value="lifted">{{ __('Dicabut') }}</option>
                </select>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="app-table">
                <thead><tr><th>{{ __('Tipe') }}</th><th>{{ __('Nomor') }}</th><th>{{ __('Alasan') }}</th><th>Status</th><th>{{ __('Oleh') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($recalls as $recall)
                        <tr>
                            <td class="text-app-muted">{{ $recall->type }}</td>
                            <td class="font-medium text-app-text">{{ $recall->type === 'serial' ? $recall->serial_number : $recall->batch_number }}</td>
                            <td class="text-app-muted">{{ $recall->reason }}</td>
                            <td><x-ui.status-badge :status="$recall->status === 'active' ? 'recalled' : 'closed'" /></td>
                            <td class="whitespace-nowrap text-app-muted">{{ $recall->recalled_at?->format('d M Y H:i') }}</td>
                            <td class="text-right">
                                @if ($recall->status === 'active')
                                    <button type="button" wire:click="lift({{ $recall->id }})" wire:confirm="{{ __('Cabut penarikan ini?') }}" class="app-btn app-btn-ghost !py-1.5 text-xs">{{ __('Cabut') }}</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-ui.empty-state title="Belum ada penarikan" message="Belum ada batch yang ditarik." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($recalls->hasPages())
            <div class="border-t border-app-border px-4 py-3">{{ $recalls->links() }}</div>
        @endif
    </x-ui.card>
</div>
