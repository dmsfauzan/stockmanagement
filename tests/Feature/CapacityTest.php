<?php

namespace Tests\Feature;

use App\Livewire\Reports\CapacityReport;
use App\Models\Category;
use App\Models\Item;
use App\Models\Location;
use App\Models\StockBalance;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\CapacityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CapacityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->actingAs(User::where('email', 'admin@stock.test')->firstOrFail());
    }

    private function item(string $sku): Item
    {
        return Item::create([
            'sku' => $sku,
            'name' => $sku,
            'category_id' => Category::firstOrFail()->id,
            'unit_id' => Unit::firstOrFail()->id,
            'minimum_stock' => 0,
            'maximum_stock' => 0,
            'status' => 'active',
        ]);
    }

    private function location(string $code, int $capacity): Location
    {
        $rackId = Location::where('code', 'A01-01')->value('rack_id');

        return Location::create([
            'rack_id' => $rackId,
            'code' => $code,
            'name' => 'Cap '.$code,
            'capacity' => $capacity,
        ]);
    }

    private function putStock(Item $item, Location $location, int $qty): void
    {
        StockBalance::create([
            'item_id' => $item->id,
            'warehouse_id' => Warehouse::where('code', 'WH-JKT')->value('id'),
            'location_id' => $location->id,
            'quantity_on_hand' => $qty,
            'quantity_reserved' => 0,
            'quantity_quarantine' => 0,
        ]);
    }

    public function test_utilization_and_status(): void
    {
        $location = $this->location('CAP-T1', 10);
        $item = $this->item('CAP-1');
        $this->putStock($item, $location, 9);

        $row = CapacityService::utilization()->firstWhere('id', $location->id);

        $this->assertNotNull($row);
        $this->assertSame(90.0, $row['percent']);
        $this->assertSame('high', $row['status']);
    }

    public function test_suggest_location_prefers_stocked_bin(): void
    {
        $warehouse = Warehouse::where('code', 'WH-JKT')->firstOrFail();
        $target = $this->location('CAP-TGT', 100);
        $this->location('CAP-SMALL', 1);

        $item = $this->item('CAP-SUG');
        $this->putStock($item, $target, 1);

        $suggestion = CapacityService::suggestLocation($warehouse->id, $item->id, 5);

        $this->assertNotNull($suggestion);
        $this->assertSame('CAP-TGT', $suggestion['code']);
    }

    public function test_report_page_and_api(): void
    {
        $this->location('CAP-PAGE', 50);

        $this->get('/reports/capacity')->assertOk()->assertSee('Kapasitas Gudang');

        Livewire::test(CapacityReport::class)->assertSee('Utilisasi');

        $this->getJson('/api/reports/capacity')->assertOk();

        $item = $this->item('CAP-API');
        $this->getJson('/api/put-away-suggestion?warehouse_id='.Warehouse::where('code', 'WH-JKT')->value('id').'&item_id='.$item->id.'&quantity=1')->assertOk();
    }
}
