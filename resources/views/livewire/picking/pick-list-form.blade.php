<div>
    <x-ui.page-header title="Buat Pick List" subtitle="Pilih barang keluar yang sudah disetujui">
        <x-slot:actions>
            <a href="{{ route('picking.index') }}" class="app-btn app-btn-secondary">{{ __('Batal') }}</a>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card class="max-w-2xl">
        <form wire:submit="save" class="space-y-4">
            <div>
                <label class="app-label mb-1.5">{{ __('Barang Keluar') }}<span class="text-rose-500">*</span></label>
                <select wire:model="goods_issue_id" class="app-select">
                    <option value="">-- Pilih --</option>
                    @foreach ($issues as $issue)
                        <option value="{{ $issue->id }}">{{ $issue->number }} ({{ $issue->transaction_date?->format('d M Y') }})</option>
                    @endforeach
                </select>
                @error('goods_issue_id') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="app-label mb-1.5">{{ __('Ditugaskan ke') }}</label>
                <select wire:model="assigned_to" class="app-select">
                    <option value="">-- Tanpa assignee --</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                    @endforeach
                </select>
                @error('assigned_to') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="app-label mb-1.5">{{ __('Catatan') }}</label>
                <textarea wire:model="notes" rows="2" class="app-textarea"></textarea>
                @error('notes') <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
            </div>
            <div class="flex justify-end">
                <button type="submit" class="app-btn app-btn-primary">{{ __('Buat') }}</button>
            </div>
        </form>
    </x-ui.card>
</div>
