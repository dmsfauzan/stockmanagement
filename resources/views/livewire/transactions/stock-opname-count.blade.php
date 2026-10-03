<div>
    <x-ui.page-header title="Counting Stock Opname" subtitle="{{ $opname->number }} — {{ $opname->opname_date?->format('d M Y') }} — {{ $opname->warehouse?->name ?? '-' }}">
        <x-slot:actions>
            <a href="{{ route('stock-opnames.show', $opname) }}" class="app-btn app-btn-secondary">Kembali</a>
        </x-slot:actions>
    </x-ui.page-header>

    @if(empty($items))
        <x-ui.card>
            <x-ui.empty-state title="Tidak ada item" message="Generate item dari stock via form terlebih dahulu." />
        </x-ui.card>
    @else
        <x-ui.card padding="p-0">
            <div class="app-card-header flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <h2 class="app-card-title">Hasil Counting</h2>
                <div class="flex flex-wrap gap-2">
                    <button type="button" wire:click="fillEmpties" class="app-btn app-btn-secondary text-xs">Isi kosong = system</button>
                    <button type="button" wire:click="clearAll" class="app-btn app-btn-ghost text-xs">Kosongkan</button>
                </div>
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
                            <th>Catatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $index => $row)
                            <tr wire:key="opname-row-{{ $row['id'] }}" class="align-top">
                                <td>
                                    <span class="font-medium text-app-text">{{ $row['sku'] ?? '-' }}</span>
                                    <span class="text-app-muted">— {{ $row['name'] ?? '-' }}</span>
                                </td>
                                <td class="text-right text-app-muted">{{ number_format($row['system_quantity']) }}</td>
                                <td class="text-right">
                                    <input type="number" min="0" wire:model.live.debounce.200ms="items.{{ $index }}.physical_quantity" class="app-input w-28 px-2 py-2 text-right text-sm">
                                </td>
                                <td class="text-right">
                                    @php $diff = (int) ($row['difference'] ?? 0); @endphp
                                    <span @class([
                                        'inline-flex min-w-[3rem] justify-end font-semibold',
                                        'text-emerald-600 dark:text-emerald-400' => $diff > 0,
                                        'text-rose-600 dark:text-rose-400' => $diff < 0,
                                        'text-app-muted' => $diff === 0,
                                    ])>
                                        {{ $diff > 0 ? '+' : '' }}{{ number_format($diff) }}
                                    </span>
                                </td>
                                <td>
                                    <select wire:model.live="items.{{ $index }}.reason" class="app-select w-32 px-2 py-2 text-sm">
                                        <option value="">-- Pilih --</option>
                                        @foreach ($reasons as $reason)
                                            <option value="{{ $reason }}">{{ $reason }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <input type="text" wire:model="items.{{ $index }}.notes" class="app-input w-40 px-2 py-2 text-sm" placeholder="Catatan...">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-ui.card>

        <div class="mt-4 flex justify-end gap-2">
            <a href="{{ route('stock-opnames.show', $opname) }}" class="app-btn app-btn-secondary">Batal</a>
            <x-ui.confirm action="submit" title="Ajukan Opname" message="Pastikan semua physical qty terisi. Opname akan diajukan untuk persetujuan." confirm-label="Submit" class="app-btn app-btn-primary">Submit</x-ui.confirm>
        </div>
    @endif
</div>
