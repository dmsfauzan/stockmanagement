<?php

namespace Tests\Feature;

use App\Enums\TrackingType;
use App\Enums\TransactionType;
use App\Models\Item;
use App\Models\Location;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LotSerialTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->actingAs(User::where('email', 'admin@stock.test')->firstOrFail());
    }

    private function context(): array
    {
        $base = Item::firstOrFail();

        $item = Item::create([
            'sku' => 'BRG-LOT-'.uniqid(),
            'name' => 'Lot Test Item',
            'category_id' => $base->category_id,
            'unit_id' => $base->unit_id,
            'minimum_stock' => 0,
            'maximum_stock' => 0,
            'status' => 'active',
            'tracking_type' => TrackingType::Batch->value,
        ]);

        $warehouse = Warehouse::where('code', 'WH-JKT')->firstOrFail();
        $location = Location::where('code', 'A01-01')->firstOrFail();

        return [$item, $warehouse, $location];
    }

    public function test_batch_receipt_creates_lots(): void
    {
        [$item, $warehouse, $location] = $this->context();
        $item->update(['tracking_type' => TrackingType::Batch]);

        LedgerService::record($item->id, $warehouse->id, $location->id, TransactionType::Incoming, 'test', 1, 10, 0, 'B1', now()->addDays(5)->toDateString(), null, 100);
        LedgerService::record($item->id, $warehouse->id, $location->id, TransactionType::Incoming, 'test', 2, 5, 0, 'B2', now()->addDays(2)->toDateString(), null, 120);

        $this->assertEquals(2, StockLot::where('item_id', $item->id)->count());
        $this->assertEquals(15, StockLot::where('item_id', $item->id)->sum('quantity'));
    }

    public function test_fefo_consumes_earliest_expiry_first(): void
    {
        [$item, $warehouse, $location] = $this->context();
        $item->update(['tracking_type' => TrackingType::Batch]);

        LedgerService::record($item->id, $warehouse->id, $location->id, TransactionType::Incoming, 'test', 1, 10, 0, 'B1', now()->addDays(30)->toDateString(), null, 100);
        LedgerService::record($item->id, $warehouse->id, $location->id, TransactionType::Incoming, 'test', 2, 5, 0, 'B2', now()->addDays(2)->toDateString(), null, 100);

        LedgerService::record($item->id, $warehouse->id, $location->id, TransactionType::Outgoing, 'test', 3, 0, 3);

        $this->assertEquals(2, StockLot::where('batch_number', 'B2')->first()->quantity);
        $this->assertEquals(10, StockLot::where('batch_number', 'B1')->first()->quantity);
        $this->assertNotNull(StockMovement::where('transaction_type', TransactionType::Outgoing->value)->first());
    }

    public function test_serial_tracking_stores_serial(): void
    {
        [$item, $warehouse, $location] = $this->context();
        $item->update(['tracking_type' => TrackingType::Serial]);

        LedgerService::record($item->id, $warehouse->id, $location->id, TransactionType::Incoming, 'test', 1, 1, 0, null, null, null, 500, 'SN-0001');

        $lot = StockLot::where('serial_number', 'SN-0001')->firstOrFail();
        $this->assertEquals(1, $lot->quantity);
    }

    public function test_insufficient_lot_stock_throws(): void
    {
        [$item, $warehouse, $location] = $this->context();
        $item->update(['tracking_type' => TrackingType::Batch]);

        LedgerService::record($item->id, $warehouse->id, $location->id, TransactionType::Incoming, 'test', 1, 3, 0, 'B1', null, null, 100);

        $this->expectException(\RuntimeException::class);
        LedgerService::record($item->id, $warehouse->id, $location->id, TransactionType::Outgoing, 'test', 2, 0, 5);
    }
}
