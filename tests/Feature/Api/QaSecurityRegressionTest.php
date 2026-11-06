<?php

namespace Tests\Feature\Api;

use App\Livewire\Admin\SettingIndex;
use App\Models\Item;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regression coverage for the security findings that are not covered by the
 * warehouse-scope suite: token lifecycle, 2FA enforcement for admins,
 * middleware reliability and data exposure.
 */
class QaSecurityRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function adminToken(string $name = 'qa'): array
    {
        $admin = User::where('email', 'admin@stock.test')->firstOrFail();

        return [$admin, $admin->createToken($name, ['*'])->plainTextToken];
    }

    public function test_inactive_account_returns_forbidden_and_revokes_token(): void
    {
        [$admin, $token] = $this->adminToken();

        $admin->forceFill(['status' => 'inactive'])->save();

        $this->withToken($token)->getJson('/api/me')->assertStatus(403);
    }

    public function test_oversized_idempotency_key_is_rejected_gracefully(): void
    {
        [, $token] = $this->adminToken();

        $this->withToken($token)->withHeaders([
            'Idempotency-Key' => str_repeat('k', 300),
        ])->postJson('/api/logout')->assertStatus(422);
    }

    public function test_token_without_abilities_is_not_granted_full_access(): void
    {
        [$admin, $token] = $this->adminToken();

        $this->withToken($token)->getJson('/api/items')->assertOk();

        $unscoped = $admin->createToken('qa-empty', [])->plainTextToken;

        $this->app['auth']->forgetGuards();

        $this->withToken($unscoped)->getJson('/api/items')->assertStatus(403);
    }

    public function test_token_expires_after_sanctum_expiration(): void
    {
        [, $token] = $this->adminToken('qa-exp');

        $this->travel(31)->days();

        $this->withToken($token)->getJson('/api/me')->assertStatus(401);
    }

    public function test_login_token_abilities_are_taken_from_user_permissions(): void
    {
        $this->postJson('/api/login', [
            'email' => 'admin@stock.test',
            'password' => 'password',
        ])->assertCreated();

        $token = PersonalAccessToken::query()->latest('id')->firstOrFail();
        $admin = User::where('email', 'admin@stock.test')->firstOrFail();

        $expected = $admin->permissionSlugs();
        $actual = array_values((array) $token->abilities);
        sort($expected);
        sort($actual);

        $this->assertSame($expected, $actual);
        $this->assertNotContains('*', $actual);
    }

    public function test_login_ignores_client_chosen_abilities(): void
    {
        $this->postJson('/api/login', [
            'email' => 'admin@stock.test',
            'password' => 'password',
            'abilities' => ['items.view'],
        ])->assertCreated();

        $token = PersonalAccessToken::query()->latest('id')->firstOrFail();

        $this->assertContains('purchase_order.create', array_values((array) $token->abilities));
    }

    public function test_admin_without_two_factor_cannot_login_when_required(): void
    {
        Setting::set('security.require_2fa_admin', '1', 'security');

        // Route-level middleware enforces existing tokens.
        [$admin, $token] = $this->adminToken();
        $this->withToken($token)->getJson('/api/items')->assertStatus(403);

        // Login must refuse to mint a password-only token for such an admin.
        $this->postJson('/api/login', [
            'email' => 'admin@stock.test',
            'password' => 'password',
        ])->assertStatus(403);

        $this->assertFalse($admin->hasTwoFactorEnabled());
    }

    public function test_item_cost_is_hidden_without_reports_permission(): void
    {
        $role = Role::create([
            'name' => 'QA Viewer '.uniqid(),
            'slug' => 'qa-viewer-'.uniqid(),
            'is_system' => false,
        ]);
        $role->permissions()->sync([Permission::where('slug', 'items.view')->value('id')]);

        $viewer = User::factory()->create(['status' => 'active', 'all_warehouses' => true]);
        $viewer->roles()->sync([$role->id]);

        $item = Item::query()->firstOrFail();

        $this->withToken($viewer->createToken('qa', ['items.view'])->plainTextToken)
            ->getJson('/api/items/'.$item->id)
            ->assertOk()
            ->assertJsonMissingPath('data.cost');

        [$admin, $adminToken] = $this->adminToken();

        $this->app['auth']->forgetGuards();

        $this->withToken($adminToken)
            ->getJson('/api/items/'.$item->id)
            ->assertOk()
            ->assertJsonStructure(['data' => ['cost']]);
    }

    public function test_webhook_url_must_be_public_https(): void
    {
        [$admin] = $this->adminToken();

        $this->actingAs($admin);

        Livewire::test(SettingIndex::class)
            ->set('values.integration_webhook_url', 'http://127.0.0.1/hook')
            ->call('save')
            ->assertHasErrors('values.integration_webhook_url');

        Livewire::test(SettingIndex::class)
            ->set('values.integration_webhook_url', 'http://localhost:9000/hook')
            ->call('save')
            ->assertHasErrors('values.integration_webhook_url');

        Livewire::test(SettingIndex::class)
            ->set('values.integration_webhook_url', 'https://example.test/hook')
            ->call('save')
            ->assertHasNoErrors('values.integration_webhook_url');
    }
}
