<div>
    <x-ui.page-header title="Settings" subtitle="Konfigurasi aplikasi — key/value berdasar group">
        <x-slot:actions>
            <span class="text-xs text-app-muted">Grup tersedia: {{ implode(', ', array_keys($schema)) }}</span>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <form wire:submit="save" class="space-y-5">
                @foreach ($schema as $group => $fields)
                    <x-ui.card padding="p-0">
                        <div class="app-card-header">
                            <div>
                                <h2 class="app-card-title">{{ $group }}</h2>
                                <p class="mt-0.5 text-xs text-app-muted">Nilai disimpan via <code class="app-kbd">Setting::set</code> per group.</p>
                            </div>
                        </div>
                        <div class="app-card-body">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                @foreach ($fields as $alias => $field)
                                    <div class="sm:col-span-1">
                                        <label class="app-label mb-1.5">{{ $field['label'] }}</label>
                                        @if (($field['type'] ?? 'text') === 'select')
                                            <select wire:model="values.{{ $alias }}" class="app-select">
                                                @foreach ($field['options'] as $v => $label)
                                                    <option value="{{ $v }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        @else
                                            <input type="{{ $field['type'] ?? 'text' }}" wire:model="values.{{ $alias }}" class="app-input">
                                        @endif
                                        @error('values.'.$alias) <p class="mt-1 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </x-ui.card>
                @endforeach

                <div class="flex justify-end">
                    <button type="submit" class="app-btn app-btn-primary">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M17 11.5a2.5 2.5 0 115 0v2a2.5 2.5 0 01-2.5 2.5h-11A2.5 2.5 0 016 13.5v-2a2.5 2.5 0 012.5-2.5H11"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v10m0 0l-3-3m3 3l3-3"/></svg>
                        Simpan Pengaturan
                    </button>
                </div>
            </form>
        </div>

        <div class="space-y-6">
            <x-ui.card>
                <h3 class="app-card-title">System Information</h3>
                <dl class="mt-4 space-y-3 text-sm">
                    @foreach ($systemInfo as $label => $value)
                        <div class="flex items-start justify-between gap-2 border-b border-app-border py-2 last:border-0">
                            <dt class="text-app-muted">{{ $label }}</dt>
                            <dd class="text-right font-medium text-app-text break-all">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            </x-ui.card>

            <x-ui.card>
                <h3 class="app-card-title">Tentang Setting</h3>
                <p class="mt-2 text-sm text-app-muted">Group mewakili domain (<code class="app-kbd">general</code>, <code class="app-kbd">inventory</code>, dll). Semua key unik; putar ulang grup bisa ditambah skema baru tanpa migrasi.</p>
                <p class="mt-2 text-xs text-app-muted">Hanya <code class="app-kbd">settings.manage</code> yang boleh menyimpan.</p>
            </x-ui.card>
        </div>
    </div>
</div>
