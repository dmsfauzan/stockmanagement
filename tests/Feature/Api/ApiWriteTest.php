<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\GoodsIssue;
use App\Models\GoodsReceipt;
use App\Models\Item;
use App\Models\Location;
use App\Models\StockBalance;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiWriteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function adminToken(): string
    {
        return User::where('email', 'admin@stock.test')->firstOrFail()
            ->createToken('test', ['*'])->plainTextToken;
    }

    private function receiptPayload(array $overrides = []): array
    {
        return array_merge([
            'transaction_date' => now()->toDateString(),
            'supplier_id' => Supplier::where('code', 'SUP001')->value('id'),
            'warehouse_id' => Warehouse::where('code', 'WH-JKT')->value('id'),
            'items' => [[
                'item_id' => Item::where('sku', 'BRG-002')->value('id'),
                'quantity' => 7,
                'unit_cost' => 1000,
                'unit_id' => Unit::where('code', 'PCS')->value('id'),
                'location_id' => Location::where('code', 'A01-02')->value('id'),
            ]],
        ], $overrides);
    }

    private function issuePayload(array $overrides = []): array
    {
        return array_merge([
            'transaction_date' => now()->toDateString(),
            'customer_id' => Customer::where('code', 'CUST001')->value('id'),
            'destination' => 'Test Destination',
            'warehouse_id' => Warehouse::where('code', 'WH-JKT')->value('id'),
            'items' => [[
                'item_id' => Item::where('sku', 'BRG-001')->value('id'),
                'quantity' => 5,
                'unit_id' => Unit::where('code', 'PCS')->value('id'),
                'location_id' => Location::where('code', 'A01-01')->value('id'),
            ]],
        ], $overrides);
    }

    public function test_create_goods_receipt_stores_header_items_and_draft_status(): void
    {
        $response = $this->withToken($this->adminToken())
            ->postJson('/api/goods-receipts', $this->receiptPayload())
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $receipt = GoodsReceipt::findOrFail($response->json('data.id'));

        $this->assertSame('draft', $receipt->status);
        $this->assertDatabaseHas('goods_receipts', ['id' => $receipt->id, 'status' => 'draft']);
        $this->assertDatabaseHas('goods_receipt_items', [
            'goods_receipt_id' => $receipt->id,
            'quantity' => 7,
        ]);
    }

    public function test_create_goods_receipt_with_empty_items_is_rejected(): void
    {
        $this->withToken($this->adminToken())
            ->postJson('/api/goods-receipts', $this->receiptPayload(['items' => []]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items']);
    }

    public function test_goods_receipt_full_flow_posts_and_writes_stock_movements(): void
    {
        $token = $this->adminToken();

        $id = $this->withToken($token)
            ->postJson('/api/goods-receipts', $this->receiptPayload())
            ->assertStatus(201)
            ->json('data.id');

        $this->withToken($token)->postJson("/api/goods-receipts/{$id}/submit")->assertOk();
        $this->withToken($token)->postJson("/api/goods-receipts/{$id}/approve")->assertOk();
        $this->withToken($token)->postJson("/api/goods-receipts/{$id}/post")->assertOk();

        $receipt = GoodsReceipt::findOrFail($id);
        $this->assertSame('posted', $receipt->status);

        $this->assertDatabaseHas('stock_movements', [
            'reference_type' => GoodsReceipt::class,
            'reference_id' => $id,
            'quantity_in' => 7,
        ]);
    }

    public function test_goods_issue_reserves_then_consumes_and_decreases_stock(): void
    {
        $token = $this->adminToken();

        $item = Item::where('sku', 'BRG-001')->firstOrFail();
        $warehouse = Warehouse::where('code', 'WH-JKT')->firstOrFail();
        $location = Location::where('code', 'A01-01')->firstOrFail();

        $balance = StockBalance::updateOrCreate(
            ['item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'location_id' => $location->id],
            ['quantity_on_hand' => 50, 'quantity_reserved' => 0]
        );

        $id = $this->withToken($token)
            ->postJson('/api/goods-issues', $this->issuePayload())
            ->assertStatus(201)
            ->json('data.id');

        $this->withToken($token)->postJson("/api/goods-issues/{$id}/submit")->assertOk();

        $balance->refresh();
        $this->assertSame(5, (int) $balance->quantity_reserved);
        $this->assertSame(45, (int) $balance->quantity_on_hand - (int) $balance->quantity_reserved);
        $this->assertDatabaseHas('stock_reservations', [
            'reference_type' => GoodsIssue::class,
            'reference_id' => $id,
            'status' => 'active',
        ]);

        $this->withToken($token)->postJson("/api/goods-issues/{$id}/approve")->assertOk();
        $this->withToken($token)->postJson("/api/goods-issues/{$id}/post")->assertOk();

        $balance->refresh();
        $this->assertSame(45, (int) $balance->quantity_on_hand);
        $this->assertSame(0, (int) $balance->quantity_reserved);
        $this->assertDatabaseHas('stock_reservations', [
            'reference_type' => GoodsIssue::class,
            'reference_id' => $id,
            'status' => 'consumed',
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'reference_type' => GoodsIssue::class,
            'reference_id' => $id,
            'quantity_out' => 5,
        ]);
    }

    public function test_goods_issue_insufficient_stock_returns_422_at_post(): void
    {
        $token = $this->adminToken();

        $item = Item::where('sku', 'BRG-001')->firstOrFail();
        $warehouse = Warehouse::where('code', 'WH-JKT')->firstOrFail();
        $location = Location::where('code', 'A01-01')->firstOrFail();

        StockBalance::updateOrCreate(
            ['item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'location_id' => $location->id],
            ['quantity_on_hand' => 50, 'quantity_reserved' => 0]
        );

        $id = $this->withToken($token)
            ->postJson('/api/goods-issues', $this->issuePayload())
            ->assertStatus(201)
            ->json('data.id');

        $this->withToken($token)->postJson("/api/goods-issues/{$id}/submit")->assertOk();
        $this->withToken($token)->postJson("/api/goods-issues/{$id}/approve")->assertOk();

        StockBalance::where('item_id', $item->id)
            ->where('warehouse_id', $warehouse->id)
            ->where('location_id', $location->id)
            ->update(['quantity_on_hand' => 1]);

        $this->withToken($token)
            ->postJson("/api/goods-issues/{$id}/post")
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_posting_a_draft_goods_receipt_returns_422(): void
    {
        $token = $this->adminToken();

        $id = $this->withToken($token)
            ->postJson('/api/goods-receipts', $this->receiptPayload())
            ->assertStatus(201)
            ->json('data.id');

        $this->withToken($token)
            ->postJson("/api/goods-receipts/{$id}/post")
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_limited_permission_token_cannot_create_goods_receipt(): void
    {
        $limited = User::factory()->create(['status' => 'active']);
        $token = $limited->createToken('test', ['items.view'])->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/goods-receipts', $this->receiptPayload())
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }
}
