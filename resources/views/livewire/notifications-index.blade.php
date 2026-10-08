<div>
    <x-ui.page-header title="Notifikasi" subtitle="Semua notifikasi Anda">
        <x-slot:actions>
            <span class="app-badge bg-primary-50 text-primary-700 ring-primary-600/20 dark:bg-primary-900/40 dark:text-primary-300 dark:ring-primary-800">
                {{ $unreadCount }} belum dibaca
            </span>
            @if ($unreadCount > 0)
                <button type="button" wire:click="markAllAsRead" class="app-btn app-btn-secondary">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                    Tandai semua dibaca
                </button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card padding="p-0">
        <div class="border-b border-app-border p-4">
            <div class="flex flex-wrap items-center gap-3">
                <div class="relative min-w-[220px] flex-1">
                    <svg class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-app-muted" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.3-4.3M10 18a8 8 0 110-16 8 8 0 010 16z"/></svg>
                    <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari judul / pesan..." class="app-input pl-9">
                </div>
                <select wire:model.live="typeFilter" class="app-select w-auto min-w-[180px]">
                    <option value="">Semua Tipe</option>
                    @foreach ($types as $type)
                        <option value="{{ $type }}">{{ $type }}</option>
                    @endforeach
                </select>
                <select wire:model.live="readFilter" class="app-select w-auto min-w-[150px]">
                    <option value="">Semua Status</option>
                    <option value="unread">Belum dibaca</option>
                    <option value="read">Sudah dibaca</option>
                </select>
                <select wire:model.live="perPage" class="app-select w-auto">
                    @foreach ([15, 25, 50, 100] as $size)<option value="{{ $size }}">{{ $size }}</option>@endforeach
                </select>
                <button type="button" wire:click="resetFilters" class="app-btn app-btn-secondary">Reset</button>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="app-table">
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Tipe</th>
                        <th>Judul</th>
                        <th>Pesan</th>
                        <th>Link</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        @php $link = $this->linkFor($item); $hasLink = $link !== route('dashboard'); @endphp
                        <tr wire:key="notif-{{ $item->id }}" class="{{ $item->read_at ? 'text-app-muted' : 'bg-primary-50/20 font-medium' }}">
                            <td class="whitespace-nowrap text-app-muted">{{ to_display_tz($item->created_at)?->format('d M Y H:i') ?? '-' }}</td>
                            <td class="whitespace-nowrap">
                                <span class="app-badge {{ $this->badgeClasses($item->type) }}">{{ $item->type }}</span>
                            </td>
                            <td class="min-w-[160px] {{ $item->read_at ? 'text-app-muted' : 'font-semibold text-app-text' }}">{{ $item->title }}</td>
                            <td class="max-w-md text-app-muted">{{ $item->message }}</td>
                            <td class="whitespace-nowrap">
                                @if ($hasLink)
                                    <a href="{{ $link }}" class="app-link text-xs">Buka</a>
                                @else
                                    <span class="text-xs text-app-muted">—</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap text-right">
                                @if ($item->read_at === null)
                                    <button type="button" wire:click="markAsRead({{ $item->id }})" class="app-btn app-btn-secondary px-2.5 py-1 text-xs">Tandai dibaca</button>
                                @else
                                    <span class="text-xs text-app-muted">Dibaca</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-ui.empty-state title="Tidak ada notifikasi" message="Belum ada notifikasi yang sesuai filter." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($items->hasPages())<div class="border-t border-app-border px-4 py-3">{{ $items->links() }}</div>@endif
    </x-ui.card>
</div>
