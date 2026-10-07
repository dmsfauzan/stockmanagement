<?php

namespace Tests\Feature;

use App\Enums\TransactionType;
use App\Models\Customer;
use App\Models\GoodsIssue;
use App\Models\GoodsIssueItem;
use App\Models\Item;
use App\Models\Location;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryService;
use App\Services\Inventory\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesOrderFulfillmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function makeIssue(SalesOrder $order, Item $item, Location $location, int $qty): GoodsIssue
    {
        $issue = GoodsIssue::create([
            'number' => 'GI-SO-'.uniqid(),
            'transaction_date' => today(),
            'destination' => 'SO '.$order->number,
            'sales_order_id' => $order->id,
            'warehouse_id' => $order->warehouse_id,
            'status' => 'approved',
            'created_by' => auth()->id(),
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        GoodsIssueItem::create([
            'goods_issue_id' => $issue->id,
            'item_id' => $item->id,
            'quantity' => $qty,
            'unit_id' => $item->unit_id,
            'location_id' => $location->id,
        ]);

        return $issue->fresh('issueItems');
    }

    public function test_goods_issue_post_updates_fulfilled_and_status(): void
    {
        $admin = User::where('email', 'admin@stock.test')->firstOrFail();
        $this->actingAs($admin);

        $item = Item::firstOrFail();
        $warehouse = Warehouse::where('code', 'WH-JKT')->firstOrFail();
        $location = Location::where('code', 'A01-01')->firstOrFail();

        LedgerService::record($item->id, $warehouse->id, $location->id, TransactionType::Opening, 'opening', 0, 100, 0);

        $order = SalesOrder::create([
            'number' => 'SO-TEST-1',
            'order_date' => today(),
            'customer_id' => Customer::firstOrFail()->id,
            'warehouse_id' => $warehouse->id,
            'status' => 'approved',
            'created_by' => $admin->id,
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);

        $soItem = SalesOrderItem::create([
            'sales_order_id' => $order->id,
            'item_id' => $item->id,
            'quantity' => 10,
            'unit_id' => $item->unit_id,
            'unit_price' => 1000,
        ]);

        $issueFirst = $this->makeIssue($order, $item, $location, 4);
        InventoryService::postGoodsIssue($issueFirst);

        $this->assertEquals(4, $soItem->fresh()->fulfilled_quantity);
        $this->assertEquals('partial', $order->fresh()->status);

        $issueSecond = $this->makeIssue($order->fresh(), $item, $location, 6);
        InventoryService::postGoodsIssue($issueSecond);

        $this->assertEquals(10, $soItem->fresh()->fulfilled_quantity);
        $this->assertEquals('fulfilled', $order->fresh()->status);
    }

    public function test_reversal_decrements_fulfilled(): void
    {
        $admin = User::where('email', 'admin@stock.test')->firstOrFail();
        $this->actingAs($admin);

        $item = Item::firstOrFail();
        $warehouse = Warehouse::where('code', 'WH-JKT')->firstOrFail();
        $location = Location::where('code', 'A01-01')->firstOrFail();

        LedgerService::record($item->id, $warehouse->id, $location->id, TransactionType::Opening, 'opening', 0, 100, 0);

        $order = SalesOrder::create([
            'number' => 'SO-TEST-2',
            'order_date' => today(),
            'customer_id' => Customer::firstOrFail()->id,
            'warehouse_id' => $warehouse->id,
            'status' => 'approved',
            'created_by' => $admin->id,
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);

        $soItem = SalesOrderItem::create([
            'sales_order_id' => $order->id,
            'item_id' => $item->id,
            'quantity' => 5,
            'unit_id' => $item->unit_id,
            'unit_price' => 1000,
        ]);

        $issue = $this->makeIssue($order, $item, $location, 5);
        InventoryService::postGoodsIssue($issue);
        $this->assertEquals(5, $soItem->fresh()->fulfilled_quantity);

        InventoryService::reverseGoodsIssue($issue->fresh('issueItems'), 'correction');
        $this->assertEquals(0, $soItem->fresh()->fulfilled_quantity);
        $this->assertEquals('approved', $order->fresh()->status);
    }
}
