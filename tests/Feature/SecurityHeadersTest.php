<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_csp_header_is_present(): void
    {
        $response = $this->get('/login');

        $response->assertHeader('Content-Security-Policy');
        $this->assertStringContainsString('default-src', (string) $response->headers->get('Content-Security-Policy'));
    }

    public function test_basic_security_headers_present(): void
    {
        $response = $this->get('/login');

        $this->assertTrue($response->headers->has('X-Content-Type-Options'));
        $this->assertTrue($response->headers->has('X-Frame-Options'));
        $this->assertTrue($response->headers->has('Referrer-Policy'));
        $this->assertTrue($response->headers->has('Permissions-Policy'));
    }

    public function test_csp_can_be_disabled_via_config(): void
    {
        config(['security.csp' => null]);

        $response = $this->get('/login');

        $this->assertFalse($response->headers->has('Content-Security-Policy'));
    }
}
