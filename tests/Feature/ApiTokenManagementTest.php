<?php

namespace Tests\Feature;

use App\Livewire\Admin\ApiTokensIndex;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Livewire;
use Tests\TestCase;

class ApiTokenManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_open_create_defaults_expiry(): void
    {
        $admin = User::where('email', 'admin@stock.test')->firstOrFail();

        Livewire::actingAs($admin)->test(ApiTokensIndex::class)
            ->call('openCreate')
            ->assertSet('showCreateModal', true)
            ->assertSet('expiresAt', now()->addMonth()->toDateString());
    }

    public function test_rotate_token_replaces_existing(): void
    {
        $admin = User::where('email', 'admin@stock.test')->firstOrFail();

        $created = $admin->createToken('integration', ['items.view'], now()->addDays(30));
        $oldId = $created->accessToken->id;

        Livewire::actingAs($admin)->test(ApiTokensIndex::class)
            ->call('rotateToken', $oldId)
            ->assertSet('plainTokenUserId', $admin->id)
            ->assertSet('plainToken', fn ($v) => is_string($v) && $v !== '');

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $oldId]);
        $this->assertSame(1, PersonalAccessToken::where('tokenable_id', $admin->id)->where('name', 'integration')->count());
    }
}
