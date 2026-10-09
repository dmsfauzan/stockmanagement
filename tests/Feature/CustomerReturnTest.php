<?php

namespace Tests\Feature;

use App\Enums\TransactionType;
use App\Models\CustomerReturn;
use App\Models\GoodsIssue;
use App\Models\GoodsIssueItem;
use App\Models\Item;
use App\Models\Location;
use App\Models\StockBalance;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\ReturnService;
use App\Services\Workflow\DocumentWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerReturnTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Item $item;

    private Warehouse $warehouse;

    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('email', 'admin@stock.test')->firstOrFail();
        $this->item = Item::query()->firstOrFail();
        $this->warehouse = Warehouse::query()->firstOrFail();
        $this->location = Location::query()->firstOrFail();
        $this->actingAs($this->admin);
    }

    private function makeReturn(int $qty = 5, ?int $issueId = null): CustomerReturn
    {
        $return = CustomerReturn::create([
            'number' => 'CRT-'.uniqid(),
            'transaction_date' => today(),
            'goods_issue_id' => $issueId,
            'warehouse_id' => $this->warehouse->id,
            'location_id' => $this->location->id,
            'status' => 'draft',
            'created_by' => $this->admin->id,
        ]);

        $return->items()->create([
            'item_id' => $this->item->id,
            'quantity' => $qty,
            'unit_id' => $this->item->unit_id,
            'location_id' => $this->location->id,
            'unit_cost' => 1000,
        ]);

        return $return;
    }

    public function test_post_customer_return_increases_stock(): void
    {
        $baseline = (int) (StockBalance::where('item_id', $this->item->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->where('location_id', $this->location->id)
            ->value('quantity_on_hand') ?? 0);

        $return = $this->makeReturn(5);

        DocumentWorkflow::submitCustomerReturn($return->id);
        DocumentWorkflow::approveCustomerReturn($return->id);
        DocumentWorkflow::postCustomerReturn($return->id);

        $this->assertSame('posted', $return->fresh()->status);
        $this->assertDatabaseHas('stock_movements', [
            'reference_type' => CustomerReturn::class,
            'reference_id' => $return->id,
            'transaction_type' => TransactionType::ReturnIn->value,
            'quantity_in' => 5,
        ]);

        $afterPost = (int) StockBalance::where('item_id', $this->item->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->where('location_id', $this->location->id)
            ->value('quantity_on_hand');
        $this->assertSame($baseline + 5, $afterPost);
    }

    public function test_reverse_customer_return_decreases_stock(): void
    {
        $baseline = (int) (StockBalance::where('item_id', $this->item->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->where('location_id', $this->location->id)
            ->value('quantity_on_hand') ?? 0);

        $return = $this->makeReturn(5);

        DocumentWorkflow::submitCustomerReturn($return->id);
        DocumentWorkflow::approveCustomerReturn($return->id);
        DocumentWorkflow::postCustomerReturn($return->id);

        $afterPost = (int) StockBalance::where('item_id', $this->item->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->where('location_id', $this->location->id)
            ->value('quantity_on_hand');
        $this->assertSame($baseline + 5, $afterPost);

        DocumentWorkflow::reverseCustomerReturn($return->id, 'salah input');

        $this->assertNotNull($return->fresh()->reversed_at);
        $this->assertDatabaseHas('stock_movements', [
            'reference_type' => CustomerReturn::class,
            'reference_id' => $return->id,
            'transaction_type' => TransactionType::ReturnOut->value,
        ]);

        $afterReverse = (int) StockBalance::where('item_id', $this->item->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->where('location_id', $this->location->id)
            ->value('quantity_on_hand');

        $this->assertSame($baseline, $afterReverse);
    }

    public function test_remaining_is_reduced_by_returns(): void
    {
        $issue = GoodsIssue::create([
            'number' => 'GI-'.uniqid(),
            'transaction_date' => today(),
            'destination' => 'Test',
            'warehouse_id' => $this->warehouse->id,
            'status' => 'posted',
            'created_by' => $this->admin->id,
        ]);

        GoodsIssueItem::create([
            'goods_issue_id' => $issue->id,
            'item_id' => $this->item->id,
            'quantity' => 10,
            'unit_id' => $this->item->unit_id,
            'location_id' => $this->location->id,
        ]);

        $remaining = ReturnService::remainingForIssue($issue->id);
        $key = $this->item->id.':'.$this->location->id;
        $this->assertSame(10, $remaining[$key]['remaining']);

        $return = $this->makeReturn(4, $issue->id);
        DocumentWorkflow::submitCustomerReturn($return->id);

        $remaining = ReturnService::remainingForIssue($issue->id);
        $this->assertSame(6, $remaining[$key]['remaining']);

        $this->assertTrue(ReturnService::exceedsRemaining($remaining, $this->item->id, $this->location->id, 7));
        $this->assertFalse(ReturnService::exceedsRemaining($remaining, $this->item->id, $this->location->id, 6));
    }

    public function test_load_level_aware(): void
    {
        config()->set('approval.enabled', true);
        config()->set('approval.flows.customer_return', [
            ['level' => 1, 'role' => 'supervisor', 'min_total' => 0],
            ['level' => 2, 'role' => 'manager', 'min_total' => 1_000_000],
        ]);

        $return = $this->makeReturn(2000);
        DocumentWorkflow::submitCustomerReturn($return->id);
        $this->assertSame(2, (int) $return->fresh()->required_levels);

        $supervisor = User::where('email', 'supervisor@stock.test')->firstOrFail();
        $this->actingAs($supervisor);
        DocumentWorkflow::approveCustomerReturn($return->id);
        $this->assertSame('submitted', $return->fresh()->status);
    }

    public function test_remaining_via_api_returns(): void
    {
        $token = $this->admin->createToken('test', ['*'])->plainTextToken;

        $this->withToken($token)->getJson('/api/customer-returns')->assertOk();
        $this->withToken($token)->postJson('/api/customer-returns', [
            'transaction_date' => today()->toDateString(),
            'warehouse_id' => $this->warehouse->id,
            'location_id' => $this->location->id,
            'items' => [[
                'item_id' => $this->item->id,
                'quantity' => 1,
                'unit_id' => $this->item->unit_id,
                'location_id' => $this->location->id,
            ]],
        ])->assertStatus(201);

        $this->assertDatabaseHas('customer_returns', ['warehouse_id' => $this->warehouse->id]);
    }
}
