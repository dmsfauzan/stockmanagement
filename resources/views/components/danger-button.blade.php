<button {{ $attributes->merge(['type' => 'submit', 'class' => 'app-btn app-btn-danger']) }}>
    {{ $slot }}
</button>
