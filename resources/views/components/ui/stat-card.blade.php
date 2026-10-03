@props(['label', 'value', 'icon' => null, 'tone' => 'slate', 'hint' => null, 'href' => null])
@php
    $tones = [
        'slate' => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
        'indigo' => 'bg-indigo-100 text-indigo-600 dark:bg-indigo-900/40 dark:text-indigo-300',
        'emerald' => 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/40 dark:text-emerald-300',
        'rose' => 'bg-rose-100 text-rose-600 dark:bg-rose-900/40 dark:text-rose-300',
        'amber' => 'bg-amber-100 text-amber-600 dark:bg-amber-900/40 dark:text-amber-300',
        'sky' => 'bg-sky-100 text-sky-600 dark:bg-sky-900/40 dark:text-sky-300',
    ];
    $toneClass = $tones[$tone] ?? $tones['slate'];
    $tag = $href ? 'a' : 'div';
@endphp
<{{ $tag }} @if($href) href="{{ $href }}" @endif class="app-card block p-5 transition hover:shadow-md {{ $href ? 'hover:border-primary-300 dark:hover:border-primary-700' : '' }}">
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="truncate text-sm font-medium text-app-muted">{{ $label }}</p>
            <p class="mt-2 text-2xl font-semibold tracking-tight text-app-text">{{ $value }}</p>
            @if ($hint)
                <p class="mt-1 text-xs text-app-muted">{{ $hint }}</p>
            @endif
        </div>
        @if ($icon)
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ $toneClass }}">
                {!! $icon !!}
            </span>
        @endif
    </div>
</{{ $tag }}>
