<div>
    <x-ui.page-header title="Mata Uang" subtitle="Kelola mata uang & kurs terhadap mata uang dasar">
        <x-slot:actions>
            <button type="button" wire:click="openCreate" class="app-btn app-btn-primary">{{ __('Tambah Mata Uang') }}</button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card padding="p-0">
        <div class="overflow-x-auto">
            <table class="app-table">
                <thead><tr><th>{{ __('Kode') }}</th><th>{{ __('Nama') }}</th><th>{{ __('Simbol') }}</th><th class="text-right">{{ __('Kurs ke Dasar') }}</th><th>{{ __('Dasar') }}</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    @foreach ($currencies as $currency)
                        <tr>
                            <td class="font-medium text-app-text">{{ $currency->code }}</td>
                            <td class="text-app-text">{{ $currency->name }}</td>
                            <td class="text-app-muted">{{ $currency->symbol ?? '-' }}</td>
                            <td class="text-right">{{ $currency->is_base ? '1' : number_format((float) ($currency->exchangeRates->first()->rate ?? 1), 4) }}</td>
                            <td>{!! $currency->is_base ? '<x-ui.status-badge status="active" label="Base" />' : '-' !!}</td>
                            <td><x-ui.status-badge :status="$currency->is_active ? 'active' : 'inactive'" /></td>
                            <td class="text-right"><button type="button" wire:click="openEdit({{ $currency->id }})" class="app-btn app-btn-ghost !py-1.5 text-xs">{{ __('Ubah') }}</button></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-ui.card>

    @if ($showModal)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/60 p-4">
            <div class="app-card w-full max-w-lg p-5">
                <h2 class="text-lg font-semibold text-app-text">{{ $editingId ? __('Ubah Mata Uang') : __('Tambah Mata Uang') }}</h2>
                <form wire:submit="save" class="mt-4 space-y-4">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="app-label mb-1">{{ __('Kode') }}<span class="text-rose-500">*</span></label>
                            <input type="text" wire:model="code" class="app-input uppercase" placeholder="USD" @disabled($editingId !== null)>
                            @error('code') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="app-label mb-1">{{ __('Simbol') }}</label>
                            <input type="text" wire:model="symbol" class="app-input" placeholder="$">
                        </div>
                    </div>
                    <div>
                        <label class="app-label mb-1">{{ __('Nama') }}<span class="text-rose-500">*</span></label>
                        <input type="text" wire:model="name" class="app-input" placeholder="Dolar AS">
                        @error('name') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="app-label mb-1">{{ __('Kurs ke Mata Uang Dasar') }} ({{ $baseCode }})</label>
                            <input type="number" step="0.00000001" min="0" wire:model="rate" class="app-input">
                            @error('rate') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="app-label mb-1">{{ __('Tanggal Berlaku') }}</label>
                            <input type="date" wire:model="effective_date" class="app-input">
                            @error('effective_date') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" wire:model="is_active" class="rounded border-app-border">
                        <span class="text-app-text">{{ __('Aktif') }}</span>
                    </label>
                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="closeModal" class="app-btn app-btn-secondary">{{ __('Batal') }}</button>
                        <button type="submit" class="app-btn app-btn-primary">{{ __('Simpan') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
