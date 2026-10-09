<?php

namespace Tests\Feature;

use App\Livewire\Dashboard\DashboardIndex;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardKpiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->actingAs(User::where('email', 'admin@stock.test')->firstOrFail());
    }

    public function test_dashboard_shows_advanced_kpi_widget(): void
    {
        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('Perputaran Stok')
            ->assertSee('Dead Stock')
            ->assertSee('Fill Rate SO');
    }

    public function test_advanced_kpi_widget_is_toggleable(): void
    {
        Livewire::test(DashboardIndex::class)
            ->call('toggleWidget', 'kpi_advanced')
            ->assertDontSee('Perputaran Stok');
    }
}
