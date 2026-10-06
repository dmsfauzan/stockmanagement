<?php

namespace Tests\Feature;

use App\Livewire\Layout\GlobalSearch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_admin_search_finds_item_by_sku(): void
    {
        $admin = User::where('email', 'admin@stock.test')->firstOrFail();
        $this->actingAs($admin);

        Livewire::test(GlobalSearch::class)
            ->set('query', 'BRG-001')
            ->assertSet('open', true)
            ->assertSee('BRG-001')
            ->assertSee('Barang');
    }

    public function test_short_query_does_not_open_and_clear_resets(): void
    {
        $admin = User::where('email', 'admin@stock.test')->firstOrFail();
        $this->actingAs($admin);

        Livewire::test(GlobalSearch::class)
            ->set('query', 'A')
            ->assertSet('open', false)
            ->assertSet('results', []);

        Livewire::test(GlobalSearch::class)
            ->set('query', 'BRG-001')
            ->call('clear')
            ->assertSet('query', '')
            ->assertSet('open', false)
            ->assertSet('results', []);
    }

    public function test_permission_gating_manager_sees_items_but_not_adjustments(): void
    {
        $admin = User::where('email', 'admin@stock.test')->firstOrFail();
        $manager = User::where('email', 'manager@stock.test')->firstOrFail();

        $this->actingAs($admin);
        Livewire::test(GlobalSearch::class)
            ->set('query', 'ADJ-DEMO-001')
            ->assertSet('open', true)
            ->assertSee('ADJ-DEMO-001');

        $this->actingAs($manager);
        Livewire::test(GlobalSearch::class)
            ->set('query', 'BRG-001')
            ->assertSet('open', true)
            ->assertSee('BRG-001');

        Livewire::test(GlobalSearch::class)
            ->set('query', 'ADJ-DEMO-001')
            ->assertSet('open', true)
            ->assertSet('results', [])
            ->assertSee('Tidak ada hasil');
    }
}
