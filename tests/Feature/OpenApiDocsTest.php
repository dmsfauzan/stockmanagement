<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpenApiDocsTest extends TestCase
{
    use RefreshDatabase;

    public function test_openapi_json_is_served(): void
    {
        $response = $this->getJson('/docs/api.json');

        $response->assertOk();
        $response->assertJsonStructure(['openapi', 'info', 'paths']);
    }

    public function test_docs_ui_is_served(): void
    {
        $this->get('/docs/api')->assertOk();
    }
}
