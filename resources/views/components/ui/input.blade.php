@props(['type' => 'text', 'name' => null, 'label' => null, 'error' => null, 'placeholder' => null, 'value' => null, 'required' => false])
@if ($label)
    <label for="{{ $name }}" class="app-label mb-1.5">
        {{ $label }}@if ($required)<span class="text-rose-500"> *</span>@endif
    </label>
@endif
<input
    type="{{ $type }}"
    @if ($name) id="{{ $name }}" name="{{ $name }}" @endif
    @if (! is_null($value)) value="{{ $value }}" @endif
    @if ($placeholder) placeholder="{{ $placeholder }}" @endif
    @if ($required) required @endif
    {{ $attributes->merge(['class' => 'app-input']) }}
/>
@if ($error)
    <x-input-error :messages="$error" class="mt-1.5" />
@endif
