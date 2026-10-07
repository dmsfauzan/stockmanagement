<?php

namespace Tests\Feature;

use App\Models\SavedFilter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SavedFiltersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_report_can_save_and_apply_filter(): void
    {
        $admin = User::where('email', 'admin@stock.test')->firstOrFail();

        Livewire::actingAs($admin)->test('reports.stock-report')
            ->set('search', 'BRG-001')
            ->set('savedFilterName', 'Mouse saja')
            ->call('saveCurrentFilter');

        $this->assertDatabaseHas('saved_filters', [
            'user_id' => $admin->id,
            'report' => 'StockReport',
            'name' => 'Mouse saja',
        ]);

        $filter = SavedFilter::where('user_id', $admin->id)->where('name', 'Mouse saja')->first();

        Livewire::actingAs($admin)->test('reports.stock-report')
            ->set('search', '')
            ->call('applySavedFilter', $filter->id)
            ->assertSet('search', 'BRG-001');
    }

    public function test_saved_filters_are_scoped_per_user(): void
    {
        $admin = User::where('email', 'admin@stock.test')->firstOrFail();
        $manager = User::where('email', 'manager@stock.test')->firstOrFail();

        Livewire::actingAs($admin)->test('reports.stock-report')
            ->set('search', 'BRG-001')
            ->set('savedFilterName', 'Pribadi')
            ->call('saveCurrentFilter');

        $this->assertDatabaseHas('saved_filters', ['user_id' => $admin->id, 'name' => 'Pribadi']);
        $this->assertDatabaseMissing('saved_filters', ['user_id' => $manager->id, 'name' => 'Pribadi']);
    }
}
