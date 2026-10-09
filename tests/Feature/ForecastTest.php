<?php

namespace Tests\Feature;

use App\Livewire\Reports\ForecastReport;
use App\Models\Category;
use App\Models\GoodsIssue;
use App\Models\GoodsReceipt;
use App\Models\Item;
use App\Models\Location;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryForecastService;
use App\Services\Inventory\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ForecastTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->actingAs(User::where('email', 'admin@stock.test')->firstOrFail());
    }

    private function makeItem(string $sku): Item
    {
        return Item::create([
            'sku' => $sku,
            'name' => $sku,
            'category_id' => Category::firstOrFail()->id,
            'unit_id' => Unit::firstOrFail()->id,
            'minimum_stock' => 5,
            'maximum_stock' => 100,
            'status' => 'active',
        ]);
    }

    private function stockUp(Item $item, int $qty): void
    {
        $warehouse = Warehouse::where('code', 'WH-JKT')->firstOrFail();
        $location = Location::where('code', 'A01-01')->firstOrFail();

        $receipt = GoodsReceipt::create([
            'number' => 'GR-F-'.uniqid(), 'transaction_date' => today(),
            'supplier_id' => Supplier::firstOrFail()->id,
            'warehouse_id' => $warehouse->id, 'status' => 'approved',
            'created_by' => auth()->id(), 'approved_by' => auth()->id(), 'approved_at' => now(),
        ]);

        $receipt->receiptItems()->create([
            'item_id' => $item->id, 'quantity' => $qty, 'unit_cost' => 1000,
            'unit_id' => $item->unit_id, 'location_id' => $location->id,
        ]);

        InventoryService::postGoodsReceipt($receipt->fresh('receiptItems'));
    }

    private function issueDaysAgo(Item $item, int $daysAgo, int $qty, int $repeat = 5): void
    {
        $warehouse = Warehouse::where('code', 'WH-JKT')->firstOrFail();
        $location = Location::where('code', 'A01-01')->firstOrFail();

        for ($day = 0; $day < $repeat; $day++) {
            $issue = GoodsIssue::create([
                'number' => 'GI-F-'.uniqid(), 'transaction_date' => today()->subDays($daysAgo + $day),
                'warehouse_id' => $warehouse->id, 'status' => 'approved',
                'destination' => 'Test',
                'created_by' => auth()->id(), 'approved_by' => auth()->id(), 'approved_at' => now(),
            ]);

            $issue->issueItems()->create([
                'item_id' => $item->id, 'quantity' => $qty,
                'unit_id' => $item->unit_id, 'location_id' => $location->id,
            ]);

            InventoryService::postGoodsIssue($issue->fresh('issueItems'));
        }
    }

    public function test_forecast_computes_mean_and_safety_stock(): void
    {
        $item = $this->makeItem('FC-1');
        $this->stockUp($item, 100);
        $this->issueDaysAgo($item, 0, 2);

        $result = InventoryForecastService::forecast($item->id, 30);

        $this->assertSame(30, $result['history_days']);
        $this->assertGreaterThan(0, $result['daily_mean']);
        $this->assertGreaterThanOrEqual(0, $result['safety_stock']);
        $this->assertGreaterThan(0, $result['reorder_point']);
        $this->assertCount(30, $result['projected']);
    }

    public function test_top_needs_sorts_by_cover(): void
    {
        $item = $this->makeItem('FC-2');
        $this->stockUp($item, 100);
        $this->issueDaysAgo($item, 0, 2, repeat: 3);

        $rows = InventoryForecastService::topNeeds(limit: 5);

        $this->assertTrue($rows->firstWhere('sku', 'FC-2') !== null);
    }

    public function test_report_page_and_api(): void
    {
        $this->get('/reports/forecast')->assertOk()->assertSee('Prakiraan Permintaan');

        $item = $this->makeItem('FCAPI');
        $this->stockUp($item, 50);

        $this->getJson('/api/reports/forecast')->assertOk();
        $this->getJson("/api/reports/forecast/{$item->id}")->assertOk()->assertJsonStructure(['data' => ['reorder_point', 'safety_stock']]);

        Livewire::test(ForecastReport::class)
            ->set('warehouseFilter', (string) Warehouse::where('code', 'WH-JKT')->value('id'))
            ->assertSee('Kebutuhan Tertinggi');
    }
}
