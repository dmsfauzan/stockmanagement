@props(['title' => 'No data found', 'message' => null, 'icon' => true])
<div class="flex flex-col items-center justify-center px-6 py-14 text-center">
    @if ($icon)
        <span class="mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-app-surface-2 text-app-muted">
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M20 13V7a2 2 0 00-2-2H6a2 2 0 00-2 2v6m16 0H4m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5" />
            </svg>
        </span>
    @endif
    <h3 class="text-sm font-semibold text-app-text">{{ $title }}</h3>
    @if ($message)
        <p class="mt-1 max-w-md text-sm text-app-muted">{{ $message }}</p>
    @endif
    @isset($action)
        <div class="mt-4">{{ $action }}</div>
    @endisset
</div>
