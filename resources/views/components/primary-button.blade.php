<button {{ $attributes->merge(['type' => 'submit', 'class' => 'app-btn app-btn-primary']) }}>
    {{ $slot }}
</button>
