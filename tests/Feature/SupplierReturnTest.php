<?php

namespace Tests\Feature;

use App\Enums\TransactionType;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\Item;
use App\Models\Location;
use App\Models\StockBalance;
use App\Models\Supplier;
use App\Models\SupplierReturn;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\ReturnService;
use App\Services\Workflow\DocumentWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierReturnTest extends TestCase
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

    private function makeReturn(int $qty = 5, ?int $receiptId = null): SupplierReturn
    {
        $return = SupplierReturn::create([
            'number' => 'SRT-'.uniqid(),
            'transaction_date' => today(),
            'goods_receipt_id' => $receiptId,
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
        ]);

        return $return;
    }

    public function test_post_supplier_return_decreases_stock(): void
    {
        // Seed stock so supplier return has something to take.
        StockBalance::firstOrCreate(
            ['item_id' => $this->item->id, 'warehouse_id' => $this->warehouse->id, 'location_id' => $this->location->id],
            ['quantity_on_hand' => 0]
        );

        $return = $this->makeReturn(5);
        DocumentWorkflow::submitSupplierReturn($return->id);
        DocumentWorkflow::approveSupplierReturn($return->id);

        // Give it initial stock (no reservations) so the return can withdraw 5.
        StockBalance::where('item_id', $this->item->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->where('location_id', $this->location->id)
            ->update(['quantity_on_hand' => 10, 'quantity_reserved' => 0]);

        DocumentWorkflow::postSupplierReturn($return->id);

        $this->assertSame('posted', $return->fresh()->status);
        $this->assertDatabaseHas('stock_movements', [
            'reference_type' => SupplierReturn::class,
            'reference_id' => $return->id,
            'transaction_type' => TransactionType::ReturnOut->value,
            'quantity_out' => 5,
        ]);

        $balance = StockBalance::where('item_id', $this->item->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->where('location_id', $this->location->id)
            ->firstOrFail();

        $this->assertSame(5, (int) $balance->quantity_on_hand);
    }

    public function test_remaining_for_receipt(): void
    {
        $receipt = GoodsReceipt::create([
            'number' => 'GR-'.uniqid(),
            'transaction_date' => today(),
            'supplier_id' => Supplier::query()->firstOrFail()->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => 'posted',
            'created_by' => $this->admin->id,
        ]);

        GoodsReceiptItem::create([
            'goods_receipt_id' => $receipt->id,
            'item_id' => $this->item->id,
            'quantity' => 10,
            'unit_id' => $this->item->unit_id,
            'location_id' => $this->location->id,
        ]);

        $remaining = ReturnService::remainingForReceipt($receipt->id);
        $key = $this->item->id.':'.$this->location->id;
        $this->assertSame(10, $remaining[$key]['remaining']);

        $return = $this->makeReturn(3, $receipt->id);
        DocumentWorkflow::submitSupplierReturn($return->id);

        $remaining = ReturnService::remainingForReceipt($receipt->id);
        $this->assertSame(7, $remaining[$key]['remaining']);
    }

    public function test_reverse_supplier_return_increases_stock(): void
    {
        $return = $this->makeReturn(5);

        StockBalance::updateOrCreate(
            ['item_id' => $this->item->id, 'warehouse_id' => $this->warehouse->id, 'location_id' => $this->location->id],
            ['quantity_on_hand' => 20]
        );

        DocumentWorkflow::submitSupplierReturn($return->id);
        DocumentWorkflow::approveSupplierReturn($return->id);
        DocumentWorkflow::postSupplierReturn($return->id);
        DocumentWorkflow::reverseSupplierReturn($return->id, 'salah retur');

        $this->assertNotNull($return->fresh()->reversed_at);
        $this->assertDatabaseHas('stock_movements', [
            'reference_type' => SupplierReturn::class,
            'reference_id' => $return->id,
            'transaction_type' => TransactionType::ReturnIn->value,
        ]);
    }

    public function test_api_write_creates_supplier_return(): void
    {
        $token = $this->admin->createToken('test', ['*'])->plainTextToken;

        $payload = [
            'transaction_date' => today()->toDateString(),
            'warehouse_id' => $this->warehouse->id,
            'location_id' => $this->location->id,
            'items' => [[
                'item_id' => $this->item->id,
                'quantity' => 2,
                'unit_id' => $this->item->unit_id,
                'location_id' => $this->location->id,
            ]],
        ];

        $this->withToken($token)->postJson('/api/supplier-returns', $payload)->assertStatus(201);

        $this->assertDatabaseHas('supplier_returns', ['warehouse_id' => $this->warehouse->id]);
    }
}
