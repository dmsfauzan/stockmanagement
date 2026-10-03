@props(['variant' => 'primary', 'type' => 'button', 'href' => null, 'size' => null])
@php
    $variants = [
        'primary' => 'app-btn-primary',
        'secondary' => 'app-btn-secondary',
        'ghost' => 'app-btn-ghost',
        'danger' => 'app-btn-danger',
    ];
    $sizes = [
        'sm' => 'px-3 py-1.5 text-xs',
        'lg' => 'px-4 py-2.5 text-base',
    ];
    $classes = 'app-btn ' . ($variants[$variant] ?? $variants['primary']) . ($size && isset($sizes[$size]) ? ' ' . $sizes[$size] : '');
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </button>
@endif
