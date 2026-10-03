@props(['name' => null, 'label' => null, 'error' => null, 'required' => false])
@if ($label)
    <label for="{{ $name }}" class="app-label mb-1.5">
        {{ $label }}@if ($required)<span class="text-rose-500"> *</span>@endif
    </label>
@endif
<select
    @if ($name) id="{{ $name }}" name="{{ $name }}" @endif
    @if ($required) required @endif
    {{ $attributes->merge(['class' => 'app-select']) }}
>
    {{ $slot }}
</select>
@if ($error)
    <x-input-error :messages="$error" class="mt-1.5" />
@endif
