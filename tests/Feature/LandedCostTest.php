<?php

namespace Tests\Feature;

use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\GoodsReceipt;
use App\Models\Item;
use App\Models\Location;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandedCostTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->actingAs(User::where('email', 'admin@stock.test')->firstOrFail());
    }

    private function ctx(): array
    {
        $item = Item::create([
            'sku' => 'BRG-LC-'.uniqid(),
            'name' => 'Landed Cost Item',
            'category_id' => Category::firstOrFail()->id,
            'unit_id' => Unit::firstOrFail()->id,
            'minimum_stock' => 0,
            'maximum_stock' => 0,
            'status' => 'active',
        ]);

        $warehouse = Warehouse::where('code', 'WH-JKT')->firstOrFail();
        $location = Location::where('code', 'A01-01')->firstOrFail();

        return [$item, $warehouse, $location];
    }

    public function test_landed_cost_by_value_allocates_proportionally(): void
    {
        [$itemA, $warehouse, $location] = $this->ctx();
        [$itemB] = [$this->ctx()[0]];

        // Use two items in one receipt
        $receipt = GoodsReceipt::create([
            'number' => 'GR-LC-1', 'transaction_date' => today(),
            'supplier_id' => Supplier::firstOrFail()->id,
            'warehouse_id' => $warehouse->id, 'status' => 'approved',
            'freight_cost' => 10000, 'other_cost' => 2000, 'landed_cost_method' => 'value',
            'created_by' => auth()->id(), 'approved_by' => auth()->id(), 'approved_at' => now(),
        ]);

        $receipt->receiptItems()->createMany([
            ['item_id' => $itemA->id, 'quantity' => 10, 'unit_cost' => 10000, 'unit_id' => $itemA->unit_id, 'location_id' => $location->id],
            ['item_id' => $itemB->id, 'quantity' => 10, 'unit_cost' => 20000, 'unit_id' => $itemB->unit_id, 'location_id' => $location->id],
        ]);

        InventoryService::postGoodsReceipt($receipt->fresh('receiptItems'));

        // total value 100k + 200k = 300k; landed 12k allocated
        // A gets 12k * 100k/300k = 4000 => effective unit 10400
        // B gets 8000 => effective unit 20800
        $movA = StockMovement::where('item_id', $itemA->id)->where('transaction_type', TransactionType::Incoming->value)->first();
        $movB = StockMovement::where('item_id', $itemB->id)->where('transaction_type', TransactionType::Incoming->value)->first();

        $this->assertEquals(10400, round((float) $movA->unit_cost, 0));
        $this->assertEquals(20800, round((float) $movB->unit_cost, 0));
    }

    public function test_landed_cost_by_quantity_allocates_equally(): void
    {
        [$itemA, $warehouse, $location] = $this->ctx();
        [$itemB] = [$this->ctx()[0]];

        $receipt = GoodsReceipt::create([
            'number' => 'GR-LC-2', 'transaction_date' => today(),
            'supplier_id' => Supplier::firstOrFail()->id,
            'warehouse_id' => $warehouse->id, 'status' => 'approved',
            'freight_cost' => 10000, 'other_cost' => 0, 'landed_cost_method' => 'quantity',
            'created_by' => auth()->id(), 'approved_by' => auth()->id(), 'approved_at' => now(),
        ]);

        $receipt->receiptItems()->createMany([
            ['item_id' => $itemA->id, 'quantity' => 5, 'unit_cost' => 5000, 'unit_id' => $itemA->unit_id, 'location_id' => $location->id],
            ['item_id' => $itemB->id, 'quantity' => 5, 'unit_cost' => 10000, 'unit_id' => $itemB->unit_id, 'location_id' => $location->id],
        ]);

        InventoryService::postGoodsReceipt($receipt->fresh('receiptItems'));

        $movA = StockMovement::where('item_id', $itemA->id)->latest('id')->first();
        $movB = StockMovement::where('item_id', $itemB->id)->latest('id')->first();

        // 10k landed / 10 qty = 1k per unit
        $this->assertEquals(6000, round((float) $movA->unit_cost, 0));
        $this->assertEquals(11000, round((float) $movB->unit_cost, 0));
    }
}
