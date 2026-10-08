<?php

namespace Tests\Feature;

use App\Livewire\Transactions\StockAdjustmentForm;
use App\Models\StockAdjustment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StaleEditGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_stale_edit_is_rejected_with_toast(): void
    {
        $admin = User::where('email', 'admin@stock.test')->firstOrFail();
        $adjustment = StockAdjustment::where('status', 'draft')->firstOrFail();

        $component = Livewire::actingAs($admin)->test(StockAdjustmentForm::class, ['adjustment' => $adjustment]);
        $this->assertNotNull($component->get('editUpdatedAt'));

        $adjustment->touch();

        $component->call('save')->assertDispatched('toast', type: 'error');
    }

    public function test_fresh_edit_is_accepted(): void
    {
        $admin = User::where('email', 'admin@stock.test')->firstOrFail();
        $adjustment = StockAdjustment::where('status', 'draft')->firstOrFail();

        Livewire::actingAs($admin)->test(StockAdjustmentForm::class, ['adjustment' => $adjustment])
            ->set('notes', 'Perbarui')
            ->call('save')
            ->assertHasNoErrors();
    }
}
