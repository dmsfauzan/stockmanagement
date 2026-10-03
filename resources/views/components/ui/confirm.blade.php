@props([
    'action',
    'params' => [],
    'title' => 'Konfirmasi',
    'message' => 'Apakah Anda yakin ingin melanjutkan?',
    'confirmLabel' => 'Ya, Lanjutkan',
    'cancelLabel' => 'Batal',
    'variant' => 'primary',
])

@php
    $args = \Illuminate\Support\Js::from(array_values($params));
@endphp

<div x-data="{ open: false, args: {{ $args }} }" x-on:keydown.escape.window="open = false">
    <button type="button" @click="open = true" {{ $attributes->merge(['class' => 'app-btn']) }}>
        {{ $slot }}
    </button>

    <div x-show="open" x-cloak class="fixed inset-0 z-[60] flex items-center justify-center p-4" role="dialog" aria-modal="true">
        <div
            x-show="open"
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm"
            @click="open = false"
        ></div>

        <div
            x-show="open"
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 translate-y-2 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
            class="relative w-full max-w-md rounded-xl border border-app-border bg-app-surface p-6 shadow-popover"
        >
            <div class="flex items-start gap-4">
                <span @class([
                    'flex h-11 w-11 shrink-0 items-center justify-center rounded-full',
                    'bg-rose-100 text-rose-600 dark:bg-rose-900/40 dark:text-rose-300' => $variant === 'danger',
                    'bg-primary-100 text-primary-600 dark:bg-primary-900/40 dark:text-primary-300' => $variant !== 'danger',
                ])>
                    @if ($variant === 'danger')
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
                    @else
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    @endif
                </span>
                <div class="min-w-0">
                    <h3 class="text-base font-semibold text-app-text">{{ $title }}</h3>
                    <p class="mt-1 text-sm text-app-muted">{{ $message }}</p>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <button type="button" @click="open = false" class="app-btn app-btn-secondary">{{ $cancelLabel }}</button>
                <button
                    type="button"
                    wire:loading.attr="disabled"
                    @click="open = false; $wire.call('{{ $action }}', ...args)"
                    @class([
                        'app-btn',
                        'app-btn-danger' => $variant === 'danger',
                        'app-btn-primary' => $variant !== 'danger',
                    ])
                >
                    {{ $confirmLabel }}
                </button>
            </div>
        </div>
    </div>
</div>
