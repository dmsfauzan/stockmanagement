<button {{ $attributes->merge(['type' => 'button', 'class' => 'app-btn app-btn-secondary']) }}>
    {{ $slot }}
</button>
