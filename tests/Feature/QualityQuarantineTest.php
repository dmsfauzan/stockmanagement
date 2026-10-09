<?php

namespace Tests\Feature;

use App\Enums\QualityStatus;
use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\GoodsReceipt;
use App\Models\Item;
use App\Models\Location;
use App\Models\StockBalance;
use App\Models\StockLot;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryService;
use App\Services\Inventory\LedgerService;
use App\Services\Inventory\QualityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QualityQuarantineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->actingAs(User::where('email', 'admin@stock.test')->firstOrFail());
    }

    private function makeItem(string $tracking = 'none'): Item
    {
        return Item::create([
            'sku' => 'BRG-QC-'.uniqid(),
            'name' => 'QC Item',
            'category_id' => Category::firstOrFail()->id,
            'unit_id' => Unit::where('code', 'PCS')->firstOrFail()->id,
            'minimum_stock' => 0,
            'maximum_stock' => 0,
            'status' => 'active',
            'tracking_type' => $tracking,
        ]);
    }

    private function postReceipt(Item $item, int $qty, bool $inspection, ?string $batch = null): GoodsReceipt
    {
        $warehouse = Warehouse::where('code', 'WH-JKT')->firstOrFail();
        $location = Location::where('code', 'A01-01')->firstOrFail();

        $receipt = GoodsReceipt::create([
            'number' => 'GR-QC-'.uniqid(), 'transaction_date' => today(),
            'supplier_id' => Supplier::firstOrFail()->id,
            'warehouse_id' => $warehouse->id, 'status' => 'approved',
            'requires_inspection' => $inspection,
            'created_by' => auth()->id(), 'approved_by' => auth()->id(), 'approved_at' => now(),
        ]);

        $receipt->receiptItems()->create([
            'item_id' => $item->id, 'quantity' => $qty, 'unit_cost' => 1000,
            'unit_id' => $item->unit_id, 'location_id' => $location->id,
            'batch_number' => $batch,
        ]);

        InventoryService::postGoodsReceipt($receipt->fresh('receiptItems'));

        return $receipt;
    }

    private function balance(Item $item): StockBalance
    {
        return StockBalance::where('item_id', $item->id)->firstOrFail();
    }

    public function test_receipt_requiring_inspection_posts_into_quarantine(): void
    {
        $item = $this->makeItem();
        $this->postReceipt($item, 10, true);

        $balance = $this->balance($item);

        $this->assertSame(10, (int) $balance->quantity_on_hand);
        $this->assertSame(10, (int) $balance->quantity_quarantine);
        $this->assertSame(0, (int) $balance->quantity_available);
    }

    public function test_release_moves_quarantine_back_to_available(): void
    {
        $item = $this->makeItem();
        $this->postReceipt($item, 10, true);

        $balance = $this->balance($item);

        QualityService::release((int) $balance->item_id, (int) $balance->warehouse_id, (int) $balance->location_id, 4);

        $balance->refresh();
        $this->assertSame(10, (int) $balance->quantity_on_hand);
        $this->assertSame(6, (int) $balance->quantity_quarantine);
        $this->assertSame(4, (int) $balance->quantity_available);
    }

    public function test_reject_writes_off_quarantined_stock(): void
    {
        $item = $this->makeItem();
        $this->postReceipt($item, 10, true);

        $balance = $this->balance($item);

        QualityService::reject((int) $balance->item_id, (int) $balance->warehouse_id, (int) $balance->location_id, 3, 'Rusak');

        $balance->refresh();
        $this->assertSame(7, (int) $balance->quantity_on_hand);
        $this->assertSame(7, (int) $balance->quantity_quarantine);
        $this->assertDatabaseHas('stock_movements', [
            'item_id' => $item->id,
            'quantity_out' => 3,
            'quality_status' => QualityStatus::Rejected->value,
        ]);
    }

    public function test_quarantined_stock_cannot_be_issued(): void
    {
        $item = $this->makeItem();
        $this->postReceipt($item, 10, true);

        $balance = $this->balance($item);

        $this->expectException(\RuntimeException::class);

        LedgerService::record(
            (int) $item->id,
            (int) $balance->warehouse_id,
            (int) $balance->location_id,
            TransactionType::Outgoing,
            GoodsReceipt::class,
            0,
            0,
            5,
        );
    }

    public function test_lot_tracked_item_marks_quarantine_lot(): void
    {
        $item = $this->makeItem('batch');
        $this->postReceipt($item, 10, true, 'BATCH-QC-1');

        $lot = StockLot::where('item_id', $item->id)->where('batch_number', 'BATCH-QC-1')->firstOrFail();
        $this->assertSame(QualityStatus::Quarantine, $lot->quality_status);

        $balance = $this->balance($item);
        QualityService::release((int) $balance->item_id, (int) $balance->warehouse_id, (int) $balance->location_id, 10);

        $lot->refresh();
        $this->assertSame(QualityStatus::Good, $lot->quality_status);
    }

    public function test_api_lists_and_releases_quarantine(): void
    {
        $item = $this->makeItem();
        $this->postReceipt($item, 8, true);

        $balance = $this->balance($item);

        $this->getJson('/api/quarantine')
            ->assertOk()
            ->assertJsonPath('data.0.quantity_quarantine', 8);

        $this->postJson("/api/quarantine/{$balance->id}/release", ['quantity' => 8])
            ->assertOk();

        $this->assertSame(0, (int) $this->balance($item)->quantity_quarantine);
    }
}
