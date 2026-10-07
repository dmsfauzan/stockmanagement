<?php

namespace Tests\Feature;

use App\Models\ReportPreference;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReportColumnsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_columns_can_be_hidden_and_persisted(): void
    {
        $admin = User::where('email', 'admin@stock.test')->firstOrFail();

        Livewire::actingAs($admin)->test('reports.stock-report')
            ->call('toggleColumn', 'location')
            ->assertSet('hiddenColumns', ['location']);

        $this->assertDatabaseHas('report_preferences', [
            'user_id' => $admin->id,
            'report' => 'StockReport',
        ]);
    }

    public function test_hidden_columns_hydrated_on_mount(): void
    {
        $admin = User::where('email', 'admin@stock.test')->firstOrFail();

        ReportPreference::updateOrCreate(
            ['user_id' => $admin->id, 'report' => 'StockReport'],
            ['hidden_columns' => ['location']],
        );

        Livewire::actingAs($admin)->test('reports.stock-report')
            ->assertSet('hiddenColumns', ['location']);
    }

    public function test_reset_restores_all_columns(): void
    {
        $admin = User::where('email', 'admin@stock.test')->firstOrFail();

        Livewire::actingAs($admin)->test('reports.stock-report')
            ->call('toggleColumn', 'location')
            ->call('resetColumns')
            ->assertSet('hiddenColumns', []);

        $this->assertDatabaseMissing('report_preferences', [
            'user_id' => $admin->id,
            'report' => 'StockReport',
        ]);
    }
}
