<div>
    <x-ui.page-header title="Perakitan / Kit" subtitle="Rakit & bongkar barang kit berbasis BOM">
        <x-slot:actions>
            @can('assembly.create')
                <a href="{{ route('assembly-orders.create') }}" class="app-btn app-btn-primary">{{ __('Buat Perakitan') }}</a>
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
                    @foreach (['draft', 'approved', 'posted'] as $status)
                        <option value="{{ $status }}">{{ \Illuminate\Support\Str::headline($status) }}</option>
                    @endforeach
                </select>
                <select wire:model.live="typeFilter" class="app-select sm:w-auto">
                    <option value="">{{ __('Semua Tipe') }}</option>
                    @foreach ($types as $type)
                        <option value="{{ $type->value }}">{{ $type->label() }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="app-table">
                <thead><tr><th>{{ __('Nomor') }}</th><th>{{ __('Tipe') }}</th><th>{{ __('Barang') }}</th><th class="text-right">{{ __('Qty') }}</th><th>{{ __('Warehouse') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td class="font-medium text-app-text">{{ $row->number }}</td>
                            <td><x-ui.status-badge :status="$row->type instanceof \App\Enums\AssemblyType ? $row->type->value : $row->type" /></td>
                            <td class="text-app-text">{{ $row->item?->sku }} — {{ $row->item?->name }}</td>
                            <td class="text-right">{{ number_format($row->quantity) }}</td>
                            <td class="text-app-muted">{{ $row->warehouse?->name ?? '-' }}</td>
                            <td><x-ui.status-badge :status="$row->status" /></td>
                            <td class="text-right">
                                <div class="flex justify-end gap-2">
                                    @can('assembly.post')
                                        @if ($row->status === 'approved')
                                            <button type="button" wire:click="post({{ $row->id }})" wire:confirm="{{ __('Posting perakitan ini?') }}" class="app-btn app-btn-primary !py-1.5 text-xs">{{ __('Posting') }}</button>
                                        @endif
                                        @if ($row->status === 'posted' && ! $row->reversed_at)
                                            <button type="button" wire:click="reverse({{ $row->id }})" wire:confirm="{{ __('Reversal perakitan ini?') }}" class="app-btn app-btn-ghost !py-1.5 text-xs hover:!text-rose-600">{{ __('Reversal') }}</button>
                                        @endif
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-ui.empty-state title="Belum ada perakitan" message="Buat perakitan untuk merakit atau membongkar kit." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($rows->hasPages())
            <div class="border-t border-app-border px-4 py-3">{{ $rows->links() }}</div>
        @endif
    </x-ui.card>
</div>
