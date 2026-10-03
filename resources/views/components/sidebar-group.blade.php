@props(['groupKey', 'label'])
<button
    type="button"
    x-show="!collapsed"
    @click="toggleGroup('{{ $groupKey }}')"
    class="mt-4 flex w-full items-center justify-between px-3 pb-1 text-xs font-semibold uppercase tracking-wider text-slate-400 transition hover:text-white"
    :aria-expanded="isOpen('{{ $groupKey }}').toString()"
>
    <span>{{ $label }}</span>
    <svg
        class="h-3.5 w-3.5 transition-transform duration-200"
        :class="isOpen('{{ $groupKey }}') ? 'rotate-90' : ''"
        fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor"
    >
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
    </svg>
</button>
<div
    x-show="collapsed || isOpen('{{ $groupKey }}')"
    x-transition:enter="transition ease-out duration-150"
    x-transition:enter-start="opacity-0 -translate-y-1"
    x-transition:enter-end="opacity-100 translate-y-0"
    class="space-y-1"
>
    {{ $slot }}
</div>
