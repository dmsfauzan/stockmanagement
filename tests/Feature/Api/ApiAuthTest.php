<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/me')->assertStatus(401);
    }

    public function test_me_returns_user_with_valid_token(): void
    {
        $user = User::where('email', 'admin@stock.test')->firstOrFail();
        $token = $user->createToken('test', ['items.view'])->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.email', 'admin@stock.test');
    }

    public function test_token_command_creates_token(): void
    {
        $this->artisan('api:token', ['email' => 'admin@stock.test', '--abilities' => ['items.view']])
            ->assertSuccessful();

        $this->assertDatabaseHas('personal_access_tokens', ['name' => 'api-token']);
    }
}
