<div class="relative w-full" x-data="{ focused: false }" @click.outside="$wire.set('open', false)">
    <label class="relative flex w-full items-center">
        <svg class="pointer-events-none absolute left-3 h-4 w-4 text-app-muted" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.3-4.3M10 18a8 8 0 110-16 8 8 0 010 16z"/></svg>
        <input type="search" wire:model.live.debounce.300ms="query" placeholder="Search barang, supplier, PO, no. transaksi..." class="app-input py-2 pl-9 pr-8" autocomplete="off" @focus="focused = true" @keydown.escape="$wire.clear()" />
        @if($query !== '')
            <button type="button" wire:click="clear" class="absolute right-2 rounded p-1 text-app-muted hover:text-app-text" aria-label="Clear search">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        @endif
    </label>
    @if($open && count($results) > 0)
        <div class="app-card mt-1 max-h-96 overflow-auto py-1 shadow-dropdown absolute inset-x-0 z-40">
            @foreach($results as $group)
                <p class="px-3 pt-2 pb-1 text-xs font-semibold uppercase tracking-wider text-app-muted">{{ $group['group'] }}</p>
                @foreach($group['items'] as $item)
                    <a href="{{ $item['url'] }}" class="flex items-center gap-2 px-3 py-2 hover:bg-app-surface-2">
                        <span class="app-badge bg-app-surface-2 text-app-muted shrink-0">{{ $item['type'] }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-medium text-app-text">{{ $item['title'] }}</span>
                            @if(!empty($item['sub']))
                                <span class="block truncate text-xs text-app-muted">{{ $item['sub'] }}</span>
                            @endif
                        </span>
                    </a>
                @endforeach
            @endforeach
            @if(auth()->user()?->hasPermission('items.view'))
                <div class="border-t border-app-border mt-1 pt-1">
                    <a href="{{ route('items.index', ['search' => $query]) }}" class="block px-3 py-2 text-sm text-app-muted hover:bg-app-surface-2">Enter untuk daftar barang →</a>
                </div>
            @endif
        </div>
    @elseif($open && mb_strlen(trim($query)) >= 2)
        <div class="app-card mt-1 py-3 px-3 shadow-dropdown absolute inset-x-0 z-40">
            <p class="text-sm text-app-muted">Tidak ada hasil untuk '{{ $query }}'</p>
        </div>
    @endif
</div>
