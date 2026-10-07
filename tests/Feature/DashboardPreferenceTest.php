<?php

namespace Tests\Feature;

use App\Livewire\Dashboard\DashboardIndex;
use App\Models\DashboardPreference;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardPreferenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_default_layout_shows_all_widgets(): void
    {
        $admin = User::where('email', 'admin@stock.test')->firstOrFail();

        Livewire::actingAs($admin)->test(DashboardIndex::class)
            ->assertSet('enabledWidgets', DashboardIndex::WIDGETS);
    }

    public function test_user_can_hide_and_reorder_widgets(): void
    {
        $admin = User::where('email', 'admin@stock.test')->firstOrFail();

        Livewire::actingAs($admin)->test(DashboardIndex::class)
            ->call('toggleWidget', 'stats')
            ->call('moveWidget', 'recent_activities', 'up')
            ->call('saveLayout')
            ->assertDispatched('toast');

        $saved = DashboardPreference::where('user_id', $admin->id)->firstOrFail()->widgets;

        $this->assertNotContains('stats', $saved);
        $this->assertContains('recent_activities', $saved);
    }

    public function test_reset_layout_removes_preference(): void
    {
        $admin = User::where('email', 'admin@stock.test')->firstOrFail();

        DashboardPreference::create(['user_id' => $admin->id, 'widgets' => ['stats']]);

        Livewire::actingAs($admin)->test(DashboardIndex::class)
            ->call('resetLayout')
            ->assertDispatched('toast');

        $this->assertDatabaseMissing('dashboard_preferences', ['user_id' => $admin->id]);
    }

    public function test_cannot_save_empty_layout(): void
    {
        $admin = User::where('email', 'admin@stock.test')->firstOrFail();

        Livewire::actingAs($admin)->test(DashboardIndex::class)
            ->set('enabledWidgets', [])
            ->call('saveLayout')
            ->assertDispatched('toast');

        $this->assertDatabaseMissing('dashboard_preferences', ['user_id' => $admin->id]);
    }
}
