<div wire:poll.30s="refreshCount" x-data @click.outside="$wire.set('open', false)" class="relative">
    <button type="button" wire:click="openPanel" class="relative rounded-lg p-2 text-app-muted hover:bg-app-surface-2 hover:text-app-text" aria-label="Notifications">
        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M14.9 17.5H9.1a1 1 0 01-.9-.6 5 5 0 01-.4-1.9V12a4.5 4.5 0 019 0v3c0 .7-.1 1.3-.4 1.9a1 1 0 01-.9.6z"/><path stroke-linecap="round" stroke-linejoin="round" d="M10 19a2 2 0 004 0"/></svg>
        @if($unreadCount > 0)
        <span class="absolute -right-0.5 -top-0.5 flex h-5 min-w-[20px] items-center justify-center rounded-full bg-amber-500 px-1 text-xs font-bold leading-none text-white">{{ $unreadCount }}</span>
        @endif
    </button>
    @if($open)
    <div class="app-dropdown absolute right-0 mt-2 w-80 overflow-hidden p-0">
        <div class="flex items-center justify-between px-4 py-3">
            <p class="text-sm font-semibold text-app-text">Notifikasi</p>
            <button type="button" wire:click="markAllAsRead" class="text-xs font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400">Tandai semua dibaca</button>
        </div>
        <div class="max-h-96 divide-y divide-app-border overflow-y-auto border-t border-app-border">
            @forelse($items as $item)
            <div wire:key="notif-{{ $item['id'] }}" class="flex items-start gap-3 px-4 py-3 {{ $item['read'] ? '' : 'bg-primary-50 dark:bg-primary-900/20' }}">
                <span class="mt-1 flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs {{ str_contains($item['type'],'stock') ? 'bg-amber-100 text-amber-600 dark:bg-amber-900/30' : 'bg-primary-100 text-primary-600 dark:bg-primary-900/30' }}">
                    @if(str_contains($item['type'],'stock'))
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3m0 3h.01M10.9 3.1a1.5 1.5 0 012.2 0l6.4 11.1a1.5 1.5 0 01-1.1 2.3H5.6a1.5 1.5 0 01-1.1-2.3L10.9 3.1z"/></svg>
                    @else
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6M4 6h16v4H4z"/></svg>
                    @endif
                </span>
                <div class="min-w-0 flex-1">
                    <a href="{{ $item['link'] !== '#' ? $item['link'] : '#' }}" @if($item['link'] !== '#') wire:click.prevent="markAsRead({{ $item['id'] }})" onclick="window.location='{{ $item['link'] }}'" @else wire:click="markAsRead({{ $item['id'] }})" @endif class="block">
                        <p class="text-sm font-medium leading-5 text-app-text">{{ $item['title'] }}</p>
                        <p class="mt-0.5 line-clamp-2 text-xs leading-4 text-app-muted">{{ $item['message'] }}</p>
                        <p class="mt-1 text-xs text-app-muted">{{ $item['time'] }}</p>
                    </a>
                </div>
                @if(!$item['read'])
                <button type="button" wire:click="markAsRead({{ $item['id'] }})" class="shrink-0 rounded px-1.5 py-1 text-xs text-app-muted hover:bg-app-surface-2">Baca</button>
                @endif
            </div>
            @empty
            <p class="px-4 py-8 text-center text-sm text-app-muted">Belum ada notifikasi</p>
            @endforelse
        </div>
    </div>
    @endif
</div>
