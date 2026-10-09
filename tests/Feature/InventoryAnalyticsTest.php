<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\GoodsIssue;
use App\Models\GoodsReceipt;
use App\Models\Item;
use App\Models\Location;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryAnalyticsService;
use App\Services\Inventory\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->actingAs(User::where('email', 'admin@stock.test')->firstOrFail());
    }

    private function postReceipt(Item $item, int $qty, float $cost = 1000): GoodsReceipt
    {
        $warehouse = Warehouse::where('code', 'WH-JKT')->firstOrFail();
        $location = Location::where('code', 'A01-01')->firstOrFail();

        $receipt = GoodsReceipt::create([
            'number' => 'GR-A-'.uniqid(), 'transaction_date' => today(),
            'supplier_id' => Supplier::firstOrFail()->id,
            'warehouse_id' => $warehouse->id, 'status' => 'approved',
            'created_by' => auth()->id(), 'approved_by' => auth()->id(), 'approved_at' => now(),
        ]);

        $receipt->receiptItems()->create([
            'item_id' => $item->id, 'quantity' => $qty, 'unit_cost' => $cost,
            'unit_id' => $item->unit_id, 'location_id' => $location->id,
        ]);

        InventoryService::postGoodsReceipt($receipt->fresh('receiptItems'));

        return $receipt;
    }

    private function issueGoods(Item $item, int $qty): void
    {
        $warehouse = Warehouse::where('code', 'WH-JKT')->firstOrFail();
        $location = Location::where('code', 'A01-01')->firstOrFail();

        $issue = GoodsIssue::create([
            'number' => 'GI-A-'.uniqid(), 'transaction_date' => today(),
            'warehouse_id' => $warehouse->id, 'status' => 'approved',
            'created_by' => auth()->id(), 'approved_by' => auth()->id(), 'approved_at' => now(),
            'destination' => 'Test',
        ]);

        $issue->issueItems()->create([
            'item_id' => $item->id, 'quantity' => $qty,
            'unit_id' => $item->unit_id, 'location_id' => $location->id,
        ]);

        InventoryService::postGoodsIssue($issue->fresh('issueItems'));
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

    public function test_aging_reports_recent_receipts(): void
    {
        $item = $this->makeItem('ANA-AGE');
        $this->postReceipt($item, 10);

        $aging = InventoryAnalyticsService::aging();

        $this->assertTrue($aging->firstWhere('sku', 'ANA-AGE') !== null);
        $this->assertSame('0_30', $aging->firstWhere('sku', 'ANA-AGE')['bucket']);
    }

    public function test_abc_and_turnover_api(): void
    {
        $item = $this->makeItem('ANA-ABC');
        $this->postReceipt($item, 20);
        $this->issueGoods($item, 5);

        $this->getJson('/api/reports/abc')->assertOk()->assertJsonStructure(['data' => [['sku', 'class']]]);
        $this->getJson('/api/reports/aging')->assertOk()->assertJsonStructure(['data' => [['sku', 'bucket', 'age_days']]]);
        $this->getJson('/api/reports/turnover')->assertOk()->assertJsonStructure(['data' => ['turnover', 'cogs']]);
        $this->getJson('/api/reports/slow-moving')->assertOk();
    }

    public function test_slow_moving_does_not_include_recently_issued_items(): void
    {
        $item = $this->makeItem('ANA-SLOW');
        $this->postReceipt($item, 10);
        $this->issueGoods($item, 2);

        $slow = InventoryAnalyticsService::slowMoving(days: 30);

        $this->assertFalse($slow->firstWhere('sku', 'ANA-SLOW') !== null);
    }

    public function test_report_page_renders_all_tabs(): void
    {
        $this->get('/reports/inventory-analytics')
            ->assertOk()
            ->assertSee('Analitik Inventori')
            ->assertSee('Aging')
            ->assertSee('ABC')
            ->assertSee('Perputaran');
    }
}
