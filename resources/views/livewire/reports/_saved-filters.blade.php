@php
    $savedFilters = method_exists($this, 'savedFilters') ? $this->savedFilters() : [];
@endphp
<div class="flex flex-wrap items-center gap-2">
    <div class="flex items-center gap-1">
        <input type="text" wire:model="savedFilterName" placeholder="Nama filter" class="app-input w-36 text-xs">
        <button type="button" wire:click="saveCurrentFilter" class="app-btn app-btn-secondary app-btn-sm whitespace-nowrap">Simpan Filter</button>
    </div>

    @if (count($savedFilters) > 0)
        <div class="flex flex-wrap items-center gap-1">
            @foreach ($savedFilters as $sf)
                <span class="inline-flex items-center gap-1 rounded-full border border-app-border bg-app-surface px-2.5 py-1 text-xs">
                    <button type="button" wire:click="applySavedFilter({{ $sf->id }})" class="font-medium text-primary-600 hover:underline dark:text-primary-400">{{ $sf->name }}</button>
                    <button type="button" wire:click="deleteSavedFilter({{ $sf->id }})" class="text-app-muted hover:text-rose-600" aria-label="Hapus filter">&times;</button>
                </span>
            @endforeach
        </div>
    @endif
</div>
