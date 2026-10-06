<div x-data="{ open: false }" class="relative">
    <button @click="open = !open" @click.outside="open = false"
        class="inline-flex items-center gap-2 rounded-full border border-app-border bg-app-surface px-3 py-1.5 text-sm font-medium text-app-text shadow-sm transition hover:bg-app-surface-2 dark:border-slate-700">
        <svg class="h-4 w-4 shrink-0 text-primary-600 dark:text-primary-400" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21V7a2 2 0 012-2h2.5a1 1 0 011 1v1.5A1 1 0 009 8.5H15a1 1 0 001-1V6a1 1 0 011-1H19a2 2 0 012 2v14M3.75 21h16.5M6 10h12M8 14h8"/></svg>
        <span class="max-w-[140px] truncate">{{ $activeWarehouseId ? ($warehouses->firstWhere('id', $activeWarehouseId)?->name ?? 'Gudang') : 'Semua Gudang' }}</span>
        <svg class="h-4 w-4 shrink-0 text-app-muted" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6"/></svg>
    </button>
    <div x-show="open" x-transition x-cloak class="app-dropdown right-0 mt-2 w-64 p-1">
        <button wire:click="select(null)" @click="open = false" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm hover:bg-app-surface-2 {{ $activeWarehouseId === null ? 'bg-primary-50 font-semibold text-primary-700 dark:bg-primary-900/30 dark:text-primary-300' : 'text-app-text' }}">
            <span class="flex h-6 w-6 items-center justify-center rounded-full bg-slate-100 text-xs dark:bg-slate-800">≡</span>
            Semua Gudang
            @if($activeWarehouseId === null)<svg class="ml-auto h-4 w-4 text-primary-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>@endif
        </button>
        <div class="my-1 border-t border-app-border"></div>
        @foreach($warehouses as $wh)
            <button wire:click="select({{ $wh->id }})" @click="open = false" class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm hover:bg-app-surface-2 {{ (int)$activeWarehouseId === (int)$wh->id ? 'bg-primary-50 font-semibold text-primary-700 dark:bg-primary-900/30 dark:text-primary-300' : 'text-app-text' }}">
                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-primary-100 text-[10px] font-bold text-primary-700 dark:bg-primary-900/40 dark:text-primary-300">{{ strtoupper(substr($wh->code ?? $wh->name, 0, 2)) }}</span>
                <span class="flex-1 truncate">{{ $wh->name }}</span>
                <span class="truncate text-xs text-app-muted">{{ $wh->code }}</span>
                @if((int)$activeWarehouseId === (int)$wh->id)<svg class="h-4 w-4 text-primary-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>@endif
            </button>
        @endforeach
        @if($warehouses->isEmpty())
            <p class="px-3 py-2 text-center text-xs text-app-muted">Tidak ada gudang aktif</p>
        @endif
    </div>
</div>
