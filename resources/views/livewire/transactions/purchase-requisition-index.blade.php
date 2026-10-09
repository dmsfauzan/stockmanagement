<div>
    <x-ui.page-header title="Requisition" subtitle="Pengajuan kebutuhan pembelian">
        <x-slot:actions>
            @can('requisition.create')
                <a href="{{ route('requisitions.create') }}" class="app-btn app-btn-primary">{{ __('Buat Requisition') }}</a>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card padding="p-0">
        <div class="app-card-header flex-col items-stretch gap-3 sm:flex-row">
            <div class="flex flex-1 flex-wrap gap-3">
                <label class="relative block w-full sm:max-w-xs">
                    <svg class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-app-muted" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.3-4.3M10 18a8 8 0 110-16 8 8 0 010 16z"/></svg>
                    <input type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('Cari nomor') }}..." class="app-input pl-9">
                </label>
                <select wire:model.live="statusFilter" class="app-select sm:w-auto">
                    <option value="">{{ __('Semua Status') }}</option>
                    @foreach (['draft', 'submitted', 'approved', 'rejected'] as $status)
                        <option value="{{ $status }}">{{ \Illuminate\Support\Str::headline($status) }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="app-table">
                <thead><tr><th>{{ __('Nomor') }}</th><th>{{ __('Tanggal') }}</th><th>Warehouse</th><th>{{ __('Nilai') }}</th><th>Status</th><th>PO</th><th></th></tr></thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td class="font-medium text-app-text">{{ $row->number }}</td>
                            <td class="text-app-muted">{{ $row->request_date?->format('d M Y') }}</td>
                            <td class="text-app-muted">{{ $row->warehouse?->name ?? '-' }}</td>
                            <td class="text-right">{{ number_format($row->items->sum(fn ($i) => (float) $i->estimated_price * (int) $i->quantity) ?? 0, 2) }}</td>
                            <td><x-ui.status-badge :status="$row->status" /></td>
                            <td class="text-app-muted">{{ $row->purchaseOrder?->number ?? '-' }}</td>
                            <td class="text-right">
                                <div class="flex justify-end gap-2">
                                    @can('requisition.submit')
                                        @if ($row->status === 'draft')
                                            <button type="button" wire:click="submit({{ $row->id }})" wire:confirm="{{ __('Ajukan requisition ini?') }}" class="app-btn app-btn-secondary !py-1.5 text-xs">{{ __('Ajukan') }}</button>
                                        @endif
                                    @endcan
                                    @can('requisition.approve')
                                        @if ($row->status === 'submitted')
                                            <button type="button" wire:click="approve({{ $row->id }})" wire:confirm="{{ __('Setujui requisition ini?') }}" class="app-btn app-btn-primary !py-1.5 text-xs">{{ __('Setujui') }}</button>
                                            <button type="button" wire:click="reject({{ $row->id }})" wire:confirm="{{ __('Tolak requisition ini?') }}" class="app-btn app-btn-ghost !py-1.5 text-xs hover:!text-rose-600">{{ __('Tolak') }}</button>
                                        @endif
                                    @endcan
                                    @can('requisition.convert')
                                        @if ($row->status === 'approved' && ! $row->converted_purchase_order_id)
                                            <button type="button" wire:click="convert({{ $row->id }})" wire:confirm="{{ __('Konversi ke PO?') }}" class="app-btn app-btn-primary !py-1.5 text-xs">{{ __('Konversi ke PO') }}</button>
                                        @endif
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-ui.empty-state title="Belum ada requisition" message="Buat pengajuan pembelian baru." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($rows->hasPages())
            <div class="border-t border-app-border px-4 py-3">{{ $rows->links() }}</div>
        @endif
    </x-ui.card>
</div>
