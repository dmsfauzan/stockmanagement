@props(['name' => null, 'label' => null, 'error' => null, 'placeholder' => null, 'value' => null, 'rows' => 3, 'required' => false])
@if ($label)
    <label for="{{ $name }}" class="app-label mb-1.5">
        {{ $label }}@if ($required)<span class="text-rose-500"> *</span>@endif
    </label>
@endif
<textarea
    @if ($name) id="{{ $name }}" name="{{ $name }}" @endif
    rows="{{ $rows }}"
    @if ($placeholder) placeholder="{{ $placeholder }}" @endif
    @if ($required) required @endif
    {{ $attributes->merge(['class' => 'app-textarea']) }}
>{{ $value }}</textarea>
@if ($error)
    <x-input-error :messages="$error" class="mt-1.5" />
@endif
