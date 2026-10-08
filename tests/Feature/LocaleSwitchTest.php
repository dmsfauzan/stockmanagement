<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleSwitchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_switch_persists_to_session(): void
    {
        $this->post(route('locale.update'), ['locale' => 'en'])
            ->assertRedirect();

        $this->assertSame('en', session('locale'));
    }

    public function test_switch_persists_to_user_when_authenticated(): void
    {
        $admin = User::where('email', 'admin@stock.test')->firstOrFail();

        $this->actingAs($admin)->post(route('locale.update'), ['locale' => 'en'])->assertRedirect();

        $this->assertSame('en', $admin->fresh()->locale);
        $this->assertSame('en', session('locale'));
    }

    public function test_invalid_locale_is_rejected(): void
    {
        $this->post(route('locale.update'), ['locale' => 'fr'])
            ->assertSessionHasErrors('locale');
    }

    public function test_app_locale_follows_user_preference_on_dashboard(): void
    {
        $admin = User::where('email', 'admin@stock.test')->firstOrFail();

        $admin->forceFill(['locale' => 'en'])->save();

        $this->actingAs($admin)->get(route('dashboard'))->assertOk();
        $this->assertSame('en', app()->getLocale());
    }

    public function test_sidebar_labels_are_translated(): void
    {
        $admin = User::where('email', 'admin@stock.test')->firstOrFail();

        $admin->forceFill(['locale' => 'en'])->save();
        $this->withSession(['locale' => 'en'])->actingAs($admin)->get(route('dashboard'))->assertOk()->assertSee('>Items</span>', false);

        $admin->forceFill(['locale' => null])->save();
        $this->withSession(['locale' => 'id'])->actingAs($admin)->get(route('dashboard'))->assertOk()->assertSee('>Barang</span>', false);
    }
}
