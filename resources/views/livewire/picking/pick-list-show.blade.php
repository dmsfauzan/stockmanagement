<div>
    @php $status = $pickList->status instanceof \App\Enums\PickStatus ? $pickList->status : \App\Enums\PickStatus::from($pickList->status); @endphp
    <x-ui.page-header :title="'Pick List '.$pickList->number" subtitle="{{ $pickList->warehouse?->name }}">
        <x-slot:actions>
            <a href="{{ route('picking.slip', $pickList) }}" target="_blank" rel="noopener" class="app-btn app-btn-secondary">{{ __('Packing Slip') }}</a>
            <a href="{{ route('picking.index') }}" class="app-btn app-btn-secondary">{{ __('Kembali') }}</a>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card class="mb-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap gap-6 text-sm">
                <div><span class="text-app-muted">Status:</span> <x-ui.status-badge :status="$status->value" /></div>
                <div><span class="text-app-muted">Barang Keluar:</span> <span class="text-app-text">{{ $pickList->goodsIssue?->number ?? '-' }}</span></div>
                <div><span class="text-app-muted">Assignee:</span> <span class="text-app-text">{{ $pickList->assignee?->name ?? '-' }}</span></div>
            </div>
            <div class="flex flex-wrap gap-2">
                @can('picking.pick')
                    @if ($status === \App\Enums\PickStatus::Pending)
                        <button type="button" wire:click="start" class="app-btn app-btn-secondary">{{ __('Mulai Picking') }}</button>
                    @endif
                    @if (in_array($status, [\App\Enums\PickStatus::Pending, \App\Enums\PickStatus::Picking], true))
                        <button type="button" wire:click="complete" class="app-btn app-btn-primary">{{ __('Selesaikan Picking') }}</button>
                    @endif
                @endcan
                @can('picking.pack')
                    @if ($status === \App\Enums\PickStatus::Picked)
                        <button type="button" wire:click="pack" class="app-btn app-btn-primary">{{ __('Kemas') }}</button>
                    @endif
                    @if (! in_array($status, [\App\Enums\PickStatus::Packed, \App\Enums\PickStatus::Cancelled], true))
                        <button type="button" wire:click="cancel" wire:confirm="{{ __('Batalkan pick list ini?') }}" class="app-btn app-btn-ghost hover:!text-rose-600">{{ __('Batalkan') }}</button>
                    @endif
                @endcan
            </div>
        </div>
    </x-ui.card>

    <x-ui.card padding="p-0">
        <div class="overflow-x-auto">
            <table class="app-table">
                <thead><tr><th>{{ __('Barang') }}</th><th>{{ __('Lokasi') }}</th><th>Batch / Serial</th><th class="text-right">{{ __('Diminta') }}</th><th class="text-right">{{ __('Dipicking') }}</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    @foreach ($pickList->items as $line)
                        @php $lineStatus = $line->status instanceof \App\Enums\PickStatus ? $line->status : \App\Enums\PickStatus::from($line->status); @endphp
                        <tr wire:key="pick-{{ $line->id }}">
                            <td class="text-app-text">{{ $line->item?->sku }} — {{ $line->item?->name }}</td>
                            <td class="text-app-muted">{{ $line->location?->fullPath() ?? '-' }}</td>
                            <td>
                                @if ($line->serial_number)
                                    <span class="text-app-muted">SN: {{ $line->serial_number }}</span>
                                @elseif ($line->batch_number)
                                    <span class="text-app-muted">Batch: {{ $line->batch_number }}</span>
                                @else
                                    <span class="text-app-muted">-</span>
                                @endif
                            </td>
                            <td class="text-right font-medium">{{ number_format($line->quantity) }}</td>
                            <td class="text-right">
                                @if (in_array($status, [\App\Enums\PickStatus::Packed, \App\Enums\PickStatus::Cancelled], true))
                                    {{ number_format($line->picked_quantity) }}
                                @else
                                    <input type="number" min="0" max="{{ $line->quantity }}" wire:model="picks.{{ $line->id }}.picked_quantity" class="app-input w-20 px-2 py-1.5 text-right text-sm">
                                @endif
                            </td>
                            <td><x-ui.status-badge :status="$lineStatus->value" /></td>
                            <td class="text-right">
                                @can('picking.pick')
                                    @if (! in_array($status, [\App\Enums\PickStatus::Packed, \App\Enums\PickStatus::Cancelled], true))
                                        <button type="button" wire:click="confirmItem({{ $line->id }})" class="app-btn app-btn-secondary !py-1.5 text-xs">{{ __('Konfirmasi') }}</button>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-ui.card>
</div>
