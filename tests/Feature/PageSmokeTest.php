<?php

namespace Tests\Feature;

use App\Models\GoodsIssue;
use App\Models\GoodsReceipt;
use App\Models\Item;
use App\Models\Location;
use App\Models\StockAdjustment;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_admin_can_open_every_page(): void
    {
        $admin = User::where('email', 'admin@stock.test')->firstOrFail();
        $warehouse = Warehouse::firstOrFail();
        $location = Location::firstOrFail();
        $adjustment = StockAdjustment::firstOrCreate(
            ['number' => 'ADJ-19700101-9000'],
            ['transaction_date' => now(), 'warehouse_id' => $warehouse->id, 'location_id' => $location->id, 'reason' => 'Stock Count Error', 'status' => 'draft', 'created_by' => $admin->id]
        );
        $item = Item::firstOrFail();
        $receipt = GoodsReceipt::firstOrFail();
        $issue = GoodsIssue::firstOrFail();

        $routes = [
            'dashboard',
            'items.index',
            'items.show',
            'items.create',
            'categories.index',
            'units.index',
            'suppliers.index',
            'customers.index',
            'warehouses.index',
            'locations.index',
            'goods-receipts.index',
            'goods-receipts.show',
            'goods-issues.index',
            'goods-issues.show',
            'stock-adjustments.index',
            'stock-adjustments.create',
            'stock-adjustments.show',
            'stock-opnames.index',
            'stock-opnames.create',
            'stock-transfers.index',
            'stock-transfers.create',
            'purchase-orders.index',
            'purchase-orders.create',
            'sales-orders.index',
            'sales-orders.create',
            'scan',
            'stock.index',
            'stock.movements',
            'stock.low',
            'reports.stock',
            'reports.incoming',
            'reports.outgoing',
            'reports.movement',
            'reports.expiry',
            'reports.opname',
            'reports.adjustment',
            'reports.transfer',
            'reports.valuation',
            'reports.cogs',
            'reports.warehouse-comparison',
            'reports.journal',
            'reports.replenishment',
            'reports.sales-order',
            'notifications.index',
            'reports.expiry',
            'admin.users',
            'admin.roles',
            'admin.audit-logs',
            'admin.settings',
        ];

        $params = [
            'items.show' => ['item' => $item->id],
            'goods-receipts.show' => ['receipt' => $receipt->id],
            'goods-issues.show' => ['issue' => $issue->id],
            'stock-adjustments.show' => ['adjustment' => $adjustment->id],
        ];

        foreach ($routes as $name) {
            $response = $this->actingAs($admin)->get(route($name, $params[$name] ?? []));
            $this->assertTrue(
                in_array($response->getStatusCode(), [200, 302], true),
                "Route {$name} returned {$response->getStatusCode()}",
            );
        }
    }
}
