<?php

namespace Tests\Feature;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Livewire\Transactions\GoodsReceiptShow;
use App\Models\Category;
use App\Models\GoodsIssue;
use App\Models\GoodsIssueItem;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\Item;
use App\Models\Location;
use App\Models\Rack;
use App\Models\StockBalance;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Zone;
use App\Services\Inventory\InventoryService;
use App\Services\Inventory\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class ReversalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Warehouse $warehouse;

    private Location $location;

    private Item $item;

    private Unit $unit;

    private Supplier $supplier;

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
            'sku' => 'BRG-INV-1', 'name' => 'Test Item', 'category_id' => $cat->id,
            'unit_id' => $this->unit->id, 'minimum_stock' => 30, 'maximum_stock' => 300, 'status' => 'active',
        ]);
        $this->supplier = Supplier::create(['code' => 'SUP-1', 'name' => 'Supplier 1', 'status' => 'active']);
    }

    private function makePostedReceipt(int $qty = 10): GoodsReceipt
    {
        $receipt = GoodsReceipt::create([
            'number' => 'GR-RV-'.time().'-'.mt_rand(1000, 9999),
            'transaction_date' => today(),
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => TransactionStatus::Approved->value,
            'created_by' => $this->admin->id,
            'approved_by' => $this->admin->id,
            'approved_at' => now(),
        ]);
        GoodsReceiptItem::create([
            'goods_receipt_id' => $receipt->id,
            'item_id' => $this->item->id,
            'quantity' => $qty,
            'unit_id' => $this->unit->id,
            'location_id' => $this->location->id,
        ]);
        InventoryService::postGoodsReceipt($receipt->refresh());

        return $receipt->fresh('receiptItems');
    }

    private function makePostedIssue(int $openingQty, int $issueQty): GoodsIssue
    {
        LedgerService::record($this->item->id, $this->warehouse->id, $this->location->id, TransactionType::Opening, 'opening', 0, $openingQty, 0);
        $issue = GoodsIssue::create([
            'number' => 'GI-RV-'.time().'-'.mt_rand(1000, 9999),
            'transaction_date' => today(),
            'destination' => 'Dept X',
            'warehouse_id' => $this->warehouse->id,
            'status' => TransactionStatus::Approved->value,
            'created_by' => $this->admin->id,
            'approved_by' => $this->admin->id,
            'approved_at' => now(),
        ]);
        GoodsIssueItem::create([
            'goods_issue_id' => $issue->id,
            'item_id' => $this->item->id,
            'quantity' => $issueQty,
            'unit_id' => $this->unit->id,
            'location_id' => $this->location->id,
        ]);
        InventoryService::postGoodsIssue($issue->refresh());

        return $issue->fresh('issueItems');
    }

    public function test_reverse_posted_receipt_restores_stock(): void
    {
        $receipt = $this->makePostedReceipt(10);
        $this->assertEquals(10, (int) StockBalance::value('quantity_on_hand'));

        InventoryService::reverseGoodsReceipt($receipt, 'Wrong quantity input');

        $this->assertEquals(0, (int) StockBalance::value('quantity_on_hand'));
        $this->assertNotNull($receipt->fresh()->reversed_at);
        $this->assertStringContainsString('Wrong quantity', $receipt->fresh()->reversal_reason);
    }

    public function test_reverse_posted_issue_restores_stock(): void
    {
        $issue = $this->makePostedIssue(100, 20);
        $this->assertEquals(80, (int) StockBalance::value('quantity_on_hand'));

        InventoryService::reverseGoodsIssue($issue, 'Return after issue');

        $this->assertEquals(100, (int) StockBalance::value('quantity_on_hand'));
        $this->assertNotNull($issue->fresh()->reversed_at);
    }

    public function test_second_reversal_is_blocked(): void
    {
        $receipt = $this->makePostedReceipt(5);
        InventoryService::reverseGoodsReceipt($receipt, 'First reason');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/already reversed/i');
        InventoryService::reverseGoodsReceipt($receipt->fresh(), 'Second reason');
    }

    public function test_reversal_reason_validation_blank_fails(): void
    {
        $receipt = $this->makePostedReceipt(5);

        Livewire::test(GoodsReceiptShow::class, ['receipt' => $receipt->fresh()])
            ->set('reversalReason', '')
            ->call('reverse')
            ->assertHasErrors(['reversalReason']);
    }
}
