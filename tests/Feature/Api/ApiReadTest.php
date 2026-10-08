<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiReadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function tokenFor(string $email): string
    {
        $user = User::where('email', $email)->firstOrFail();

        return $user->createToken('test')->plainTextToken;
    }

    public function test_items_requires_authentication(): void
    {
        $this->getJson('/api/items')->assertStatus(401);
    }

    public function test_admin_can_list_items_and_stock(): void
    {
        $token = $this->tokenFor('admin@stock.test');

        $this->withToken($token)->getJson('/api/items')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'data', 'meta' => ['current_page', 'per_page', 'total']]);

        $this->withToken($token)->getJson('/api/stock')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_user_without_goods_receipt_view_cannot_list_receipts(): void
    {
        $limited = User::factory()->create(['name' => 'No Perms', 'email' => 'noperms@stock.test', 'status' => 'active']);

        $this->assertFalse($limited->hasPermission('goods_receipt.view'));

        $token = $limited->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson('/api/goods-receipts')->assertStatus(403);
    }

    public function test_token_with_limited_abilities_is_enforced(): void
    {
        $user = User::where('email', 'admin@stock.test')->firstOrFail();

        $limited = $user->createToken('limited', ['items.view'])->plainTextToken;
        $full = $user->createToken('full', ['*'])->plainTextToken;

        $this->withToken($limited)->getJson('/api/items')->assertOk();
        $this->withToken($limited)->getJson('/api/goods-receipts')->assertStatus(403);

        // The auth guard caches the resolved user within a single test process.
        app('auth')->forgetGuards();

        $this->withToken($full)->getJson('/api/goods-receipts')->assertOk();
    }

    public function test_paginated_response_has_meta(): void
    {
        $token = $this->tokenFor('admin@stock.test');

        $this->withToken($token)->getJson('/api/items?per_page=5')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonStructure(['meta' => ['current_page', 'per_page', 'total', 'last_page']]);
    }
}
