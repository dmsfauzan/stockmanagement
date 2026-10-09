<?php

namespace Tests\Feature;

use App\Http\Controllers\LabelController;
use App\Models\Category;
use App\Models\Item;
use App\Models\Location;
use App\Models\Setting;
use App\Models\StockBalance;
use App\Models\StockReservation;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PolishTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->actingAs(User::where('email', 'admin@stock.test')->firstOrFail());
    }

    public function test_stale_reservations_are_released(): void
    {
        $item = Item::create([
            'sku' => 'POL-1',
            'name' => 'Polish Item',
            'category_id' => Category::firstOrFail()->id,
            'unit_id' => Unit::firstOrFail()->id,
            'minimum_stock' => 0,
            'maximum_stock' => 0,
            'status' => 'active',
        ]);

        $warehouse = Warehouse::where('code', 'WH-JKT')->firstOrFail();
        $location = Location::where('code', 'A01-01')->firstOrFail();

        $balance = StockBalance::create([
            'item_id' => $item->id,
            'warehouse_id' => $warehouse->id,
            'location_id' => $location->id,
            'quantity_on_hand' => 10,
            'quantity_reserved' => 4,
            'quantity_quarantine' => 0,
        ]);

        $reservation = StockReservation::create([
            'item_id' => $item->id,
            'warehouse_id' => $warehouse->id,
            'location_id' => $location->id,
            'quantity' => 4,
            'reference_type' => 'App\\Models\\GoodsIssue',
            'reference_id' => 999,
            'status' => 'active',
            'created_by' => auth()->id(),
        ]);
        $reservation->created_at = Carbon::now()->subDays(30);
        $reservation->save();

        $this->artisan('inventory:release-stale-reservations', ['--days' => 7])->assertSuccessful();

        $this->assertSame('expired', $reservation->fresh()->status);
        $this->assertSame(0, (int) $balance->fresh()->quantity_reserved);
    }

    public function test_dry_run_does_not_release(): void
    {
        $item = Item::create([
            'sku' => 'POL-2',
            'name' => 'Polish Item 2',
            'category_id' => Category::firstOrFail()->id,
            'unit_id' => Unit::firstOrFail()->id,
            'minimum_stock' => 0,
            'maximum_stock' => 0,
            'status' => 'active',
        ]);

        $reservation = StockReservation::create([
            'item_id' => $item->id,
            'warehouse_id' => Warehouse::where('code', 'WH-JKT')->firstOrFail()->id,
            'location_id' => Location::where('code', 'A01-01')->firstOrFail()->id,
            'quantity' => 1,
            'reference_type' => 'App\\Models\\GoodsIssue',
            'reference_id' => 998,
            'status' => 'active',
            'created_by' => auth()->id(),
        ]);
        $reservation->created_at = Carbon::now()->subDays(30);
        $reservation->save();

        $this->artisan('inventory:release-stale-reservations', ['--days' => 7, '--dry-run' => true])->assertSuccessful();

        $this->assertSame('active', $reservation->fresh()->status);
    }

    public function test_label_options_follow_settings(): void
    {
        Setting::set('label.show_price', '1', 'label');
        Setting::set('label.company_text', 'PT Contoh', 'label');
        Setting::set('label.default_size', '70x40', 'label');

        $opts = LabelController::labelOptions();

        $this->assertTrue($opts['show_price']);
        $this->assertSame('PT Contoh', $opts['company_text']);

        $item = Item::create([
            'sku' => 'POL-LBL',
            'name' => 'Label Item',
            'category_id' => Category::firstOrFail()->id,
            'unit_id' => Unit::firstOrFail()->id,
            'minimum_stock' => 0,
            'maximum_stock' => 0,
            'status' => 'active',
            'price' => 25000,
        ]);

        $this->get(route('labels.item', $item))
            ->assertOk()
            ->assertSee('PT Contoh')
            ->assertSee('25.000');
    }
}
