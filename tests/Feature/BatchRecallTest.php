<?php

namespace Tests\Feature;

use App\Livewire\Inventory\BatchRecallIndex;
use App\Models\BatchRecall;
use App\Models\Category;
use App\Models\GoodsReceipt;
use App\Models\Item;
use App\Models\Location;
use App\Models\StockLot;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryService;
use App\Services\Inventory\RecallService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BatchRecallTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->actingAs(User::where('email', 'admin@stock.test')->firstOrFail());
    }

    private function postBatch(): void
    {
        $item = Item::create([
            'sku' => 'RECALL-1',
            'name' => 'Recall Item',
            'category_id' => Category::firstOrFail()->id,
            'unit_id' => Unit::firstOrFail()->id,
            'minimum_stock' => 0,
            'maximum_stock' => 0,
            'status' => 'active',
            'tracking_type' => 'batch',
        ]);

        $warehouse = Warehouse::where('code', 'WH-JKT')->firstOrFail();
        $location = Location::where('code', 'A01-01')->firstOrFail();

        $receipt = GoodsReceipt::create([
            'number' => 'GR-RC-1', 'transaction_date' => today(),
            'supplier_id' => Supplier::firstOrFail()->id,
            'warehouse_id' => $warehouse->id, 'status' => 'approved',
            'created_by' => auth()->id(), 'approved_by' => auth()->id(), 'approved_at' => now(),
        ]);

        $receipt->receiptItems()->create([
            'item_id' => $item->id, 'quantity' => 5, 'unit_cost' => 1000,
            'unit_id' => $item->unit_id, 'location_id' => $location->id,
            'batch_number' => 'BATCH-RC-1',
        ]);

        InventoryService::postGoodsReceipt($receipt->fresh('receiptItems'));
    }

    public function test_impact_lists_stock_and_documents(): void
    {
        $this->postBatch();

        $impact = RecallService::impact('batch', 'BATCH-RC-1');

        $this->assertSame(5, $impact['on_hand']);
        $this->assertTrue($impact['stock']->isNotEmpty());
        $this->assertTrue($impact['documents']->isNotEmpty());
    }

    public function test_recall_marks_lots_and_lift_restores(): void
    {
        $this->postBatch();

        RecallService::recall('batch', 'BATCH-RC-1', 'Kontaminasi');

        $this->assertSame('recalled', StockLot::where('batch_number', 'BATCH-RC-1')->first()?->quality_status->value);
        $this->assertDatabaseHas('batch_recalls', ['batch_number' => 'BATCH-RC-1', 'status' => 'active']);

        $recall = BatchRecall::where('batch_number', 'BATCH-RC-1')->firstOrFail();
        RecallService::lift($recall);

        $this->assertSame('good', StockLot::where('batch_number', 'BATCH-RC-1')->first()?->quality_status->value);
        $this->assertSame('lifted', $recall->fresh()->status);
    }

    public function test_livewire_recall_flow(): void
    {
        $this->postBatch();

        Livewire::test(BatchRecallIndex::class)
            ->set('query', 'BATCH-RC-1')
            ->call('search')
            ->set('reason', 'Testing recall')
            ->call('recall')
            ->assertSee('Tarik Batch');
    }

    public function test_api_recall_flow(): void
    {
        $this->postBatch();

        $this->postJson('/api/recalls/impact', ['type' => 'batch', 'value' => 'BATCH-RC-1'])
            ->assertOk()
            ->assertJsonPath('data.on_hand', 5);

        $response = $this->postJson('/api/recalls', ['type' => 'batch', 'value' => 'BATCH-RC-1', 'reason' => 'Uji recall'])->assertCreated();

        $this->postJson("/api/recalls/{$response->json('data.id')}/lift")->assertOk();
    }
}
