@php
    $reportCols = method_exists($this, 'reportColumns') ? $this->reportColumns() : [];
@endphp
@if (count($reportCols) > 0)
    @foreach ($hiddenColumns as $hidden)
        <style>.rc-{{ $hidden }}{ display: none !important; }</style>
    @endforeach
    <div x-data="{ open: false }" class="relative">
        <button type="button" @click="open = !open" class="app-btn app-btn-secondary app-btn-sm gap-1.5">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 5.25h16.5M3.75 9.75h16.5M3.75 14.25h16.5M3.75 18.75h9"/></svg>
            Kolom
        </button>
        <div x-show="open" x-cloak x-transition @click.outside="open = false" class="app-dropdown right-0 mt-2 w-56">
            <p class="px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-app-muted">Tampilkan kolom</p>
            <div class="max-h-64 overflow-y-auto py-1">
                @foreach ($reportCols as $key => $meta)
                    <label class="flex items-center gap-2 px-3 py-1.5 text-sm text-app-text hover:bg-app-surface-2">
                        <input type="checkbox" @checked($this->showColumn($key)) wire:click="toggleColumn('{{ $key }}')" class="rounded border-app-border text-primary-600 focus:ring-primary-500">
                        <span>{{ $meta['label'] }}</span>
                    </label>
                @endforeach
            </div>
            <div class="border-t border-app-border px-3 py-2">
                <button type="button" wire:click="resetColumns" class="app-link text-xs">Tampilkan semua kolom</button>
            </div>
        </div>
    </div>
@endif
