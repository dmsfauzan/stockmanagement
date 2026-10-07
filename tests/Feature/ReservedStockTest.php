<?php

namespace Tests\Feature;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\GoodsIssue;
use App\Models\GoodsIssueItem;
use App\Models\Item;
use App\Models\Location;
use App\Models\Rack;
use App\Models\StockBalance;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Zone;
use App\Services\Inventory\InventoryService;
use App\Services\Inventory\LedgerService;
use App\Services\Inventory\ReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class ReservedStockTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Warehouse $warehouse;

    private Location $location;

    private Item $item;

    private Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $roleId = DB::table('roles')->insertGetId(['name' => 'Administrator', 'slug' => 'admin', 'is_system' => true, 'created_at' => now(), 'updated_at' => now()]);
        $permId = DB::table('permissions')->insertGetId(['slug' => 'admin', 'name' => 'All', 'group' => 'system', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('role_permission')->insert(['role_id' => $roleId, 'permission_id' => $permId]);

        $this->admin = User::factory()->create();
        DB::table('user_role')->insert(['user_id' => $this->admin->id, 'role_id' => $roleId]);
        $this->actingAs($this->admin);

        $this->unit = Unit::create(['code' => 'PCS', 'name' => 'Pieces']);
        $cat = Category::create(['code' => 'ELEC', 'name' => 'Electronics', 'status' => 'active']);
        $this->warehouse = Warehouse::create(['code' => 'WH-TST', 'name' => 'Test Warehouse', 'status' => 'active']);
        $zone = Zone::create(['warehouse_id' => $this->warehouse->id, 'code' => 'Z-A', 'name' => 'Zone A']);
        $rack = Rack::create(['zone_id' => $zone->id, 'code' => 'A01', 'name' => 'Rack A01']);
        $this->location = Location::create(['rack_id' => $rack->id, 'code' => 'A01-01', 'name' => 'Bin 01']);
        $this->item = Item::create([
            'sku' => 'BRG-RSV-1', 'name' => 'Reserved Item', 'category_id' => $cat->id,
            'unit_id' => $this->unit->id, 'minimum_stock' => 5, 'maximum_stock' => 500, 'status' => 'active',
        ]);

        LedgerService::record($this->item->id, $this->warehouse->id, $this->location->id, TransactionType::Opening, 'opening', 0, 100, 0);
    }

    private function rows(int $qty): array
    {
        return [[
            'item_id' => $this->item->id,
            'warehouse_id' => $this->warehouse->id,
            'location_id' => $this->location->id,
            'quantity' => $qty,
        ]];
    }

    public function test_reserve_reduces_available(): void
    {
        ReservationService::reserve(GoodsIssue::class, 1, $this->rows(30));

        $balance = StockBalance::first();
        $this->assertEquals(30, $balance->quantity_reserved);
        $this->assertEquals(70, $balance->quantity_available);
    }

    public function test_over_reserve_is_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        ReservationService::reserve(GoodsIssue::class, 1, $this->rows(150));
    }

    public function test_release_restores_available(): void
    {
        ReservationService::reserve(GoodsIssue::class, 1, $this->rows(40));
        ReservationService::release(GoodsIssue::class, 1);

        $this->assertEquals(0, StockBalance::first()->quantity_reserved);
        $this->assertEquals(100, StockBalance::first()->quantity_available);
    }

    public function test_posting_goods_issue_consumes_reservation(): void
    {
        $issue = GoodsIssue::create([
            'number' => 'GI-TEST-1', 'transaction_date' => today(), 'destination' => 'Dept',
            'warehouse_id' => $this->warehouse->id, 'status' => 'approved',
            'created_by' => $this->admin->id, 'approved_by' => $this->admin->id, 'approved_at' => now(),
        ]);
        GoodsIssueItem::create([
            'goods_issue_id' => $issue->id, 'item_id' => $this->item->id,
            'quantity' => 25, 'unit_id' => $this->unit->id, 'location_id' => $this->location->id,
        ]);

        ReservationService::reserve(GoodsIssue::class, $issue->id, $this->rows(25));
        $this->assertEquals(75, StockBalance::first()->quantity_available);

        InventoryService::postGoodsIssue($issue->fresh('issueItems'));

        $balance = StockBalance::first();
        $this->assertEquals(75, $balance->quantity_on_hand);
        $this->assertEquals(0, $balance->quantity_reserved);
        $this->assertEquals(75, $balance->quantity_available);
        $this->assertEquals('posted', $issue->fresh()->status);
    }

    public function test_status_flow_submit_reserves_and_approve_keeps(): void
    {
        $issue = GoodsIssue::create([
            'number' => 'GI-TEST-2', 'transaction_date' => today(), 'destination' => 'Dept',
            'warehouse_id' => $this->warehouse->id, 'status' => 'draft', 'created_by' => $this->admin->id,
        ]);
        GoodsIssueItem::create([
            'goods_issue_id' => $issue->id, 'item_id' => $this->item->id,
            'quantity' => 10, 'unit_id' => $this->unit->id, 'location_id' => $this->location->id,
        ]);

        $comp = Livewire::actingAs($this->admin)->test('transactions.goods-issue-show', ['issue' => $issue->id]);
        $comp->call('submit');
        $this->assertEquals(10, StockBalance::first()->quantity_reserved);
        $this->assertSame(TransactionStatus::Submitted, $issue->fresh()->statusEnum());

        $comp->set('rejectionReason', 'Dibatalkan');
        $comp->call('reject');
        $this->assertEquals(0, StockBalance::first()->quantity_reserved);
    }
}
