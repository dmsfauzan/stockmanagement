<?php

namespace Tests\Feature;

use App\Enums\TrackingType;
use App\Enums\TransactionType;
use App\Models\Item;
use App\Models\Location;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FefoPickingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->actingAs(User::where('email', 'admin@stock.test')->firstOrFail());
    }

    public function test_apply_fefo_picks_earliest_expiry(): void
    {
        $base = Item::firstOrFail();
        $item = Item::create([
            'sku' => 'BRG-FEFO-'.uniqid(),
            'name' => 'FEFO Item',
            'category_id' => $base->category_id,
            'unit_id' => $base->unit_id,
            'minimum_stock' => 0,
            'maximum_stock' => 0,
            'status' => 'active',
            'tracking_type' => TrackingType::Batch->value,
        ]);

        $warehouse = Warehouse::where('code', 'WH-JKT')->firstOrFail();
        $location = Location::where('code', 'A01-01')->firstOrFail();

        LedgerService::record($item->id, $warehouse->id, $location->id, TransactionType::Incoming, 'test', 1, 5, 0, 'LATE', now()->addDays(60)->toDateString(), null, 100);
        LedgerService::record($item->id, $warehouse->id, $location->id, TransactionType::Incoming, 'test', 2, 5, 0, 'SOON', now()->addDays(3)->toDateString(), null, 100);

        Livewire::actingAs(auth()->user())->test('transactions.goods-issue-form')
            ->set('warehouse_id', (string) $warehouse->id)
            ->set('items.0.item_id', (string) $item->id)
            ->set('items.0.location_id', (string) $location->id)
            ->call('applyFefo', 0)
            ->assertSet('items.0.batch_number', 'SOON');
    }
}
