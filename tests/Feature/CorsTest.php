<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CorsTest extends TestCase
{
    use RefreshDatabase;

    public function test_preflight_allows_configured_origin(): void
    {
        config(['cors.allowed_origins' => ['https://app.example.test']]);

        $this->call('OPTIONS', '/api/me', [], [], [], ['HTTP_ORIGIN' => 'https://app.example.test'])
            ->assertHeader('Access-Control-Allow-Origin', 'https://app.example.test');
    }

    public function test_allowed_origin_request_echoes_origin(): void
    {
        config(['cors.allowed_origins' => ['https://app.example.test']]);

        $this->withHeaders(['Origin' => 'https://app.example.test'])
            ->getJson('/api/me')
            ->assertHeader('Access-Control-Allow-Origin', 'https://app.example.test');
    }

    public function test_config_orders_wildcard_last_when_unrestricted(): void
    {
        config(['cors.allowed_origins' => ['*']]);

        $this->withHeaders(['Origin' => 'https://anywhere.test'])
            ->getJson('/api/me')
            ->assertHeader('Access-Control-Allow-Origin', '*');
    }
}
