<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PwaTest extends TestCase
{
    use RefreshDatabase;

    public function test_manifest_is_served(): void
    {
        $response = $this->get('/manifest.webmanifest');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/manifest+json; charset=UTF-8');

        $data = $response->json();

        $this->assertSame('Warehouse Stock Management', $data['name']);
        $this->assertSame('WSM', $data['short_name']);
        $this->assertSame('/dashboard', $data['start_url']);
        $this->assertNotEmpty($data['icons']);
    }

    public function test_service_worker_and_offline_page_are_served(): void
    {
        $this->get('/sw.js')->assertOk()->assertHeader('Content-Type', 'application/javascript; charset=UTF-8');
        $this->get(route('offline'))->assertOk()->assertSee('Anda sedang offline');
    }

    public function test_app_layout_includes_pwa_head_and_sw_registration(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@stock.test')->firstOrFail();

        $html = $this->actingAs($admin)->get(route('dashboard'))->getContent();

        $this->assertStringContainsString('/manifest.webmanifest', $html);
        $this->assertStringContainsString('/sw.js', $html);
        $this->assertStringContainsString('theme-color', $html);
    }
}
