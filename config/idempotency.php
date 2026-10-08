<?php

return [
    /*
    |--------------------------------------------------------------------------
    | API Idempotency
    |--------------------------------------------------------------------------
    | When a client sends the configured header on a write request, the
    | response is stored and replayed for repeated requests with the same key,
    | preventing duplicate documents on network retries.
    */

    'enabled' => (bool) env('IDEMPOTENCY_ENABLED', true),

    'header' => env('IDEMPOTENCY_HEADER', 'Idempotency-Key'),

    // How long a key & its response are retained.
    'ttl_hours' => (int) env('IDEMPOTENCY_TTL_HOURS', 24),
];
