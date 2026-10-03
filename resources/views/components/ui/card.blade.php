@props(['padding' => 'p-5'])
<div {{ $attributes->merge(['class' => 'app-card ' . $padding]) }}>
    {{ $slot }}
</div>
