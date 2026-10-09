<?php

namespace Tests\Feature;

use App\Livewire\Inventory\TraceabilityIndex;
use App\Models\Category;
use App\Models\GoodsReceipt;
use App\Models\Item;
use App\Models\Location;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryService;
use App\Services\Inventory\TraceabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TraceabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->actingAs(User::where('email', 'admin@stock.test')->firstOrFail());
    }

    private function postBatchReceipt(): void
    {
        $item = Item::create([
            'sku' => 'TRACE-1',
            'name' => 'Trace Item',
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
            'number' => 'GR-TRACE-1', 'transaction_date' => today(),
            'supplier_id' => Supplier::firstOrFail()->id,
            'warehouse_id' => $warehouse->id, 'status' => 'approved',
            'created_by' => auth()->id(), 'approved_by' => auth()->id(), 'approved_at' => now(),
        ]);

        $receipt->receiptItems()->create([
            'item_id' => $item->id, 'quantity' => 5, 'unit_cost' => 1000,
            'unit_id' => $item->unit_id, 'location_id' => $location->id,
            'batch_number' => 'BATCH-TRACE-1',
        ]);

        InventoryService::postGoodsReceipt($receipt->fresh('receiptItems'));
    }

    public function test_service_traces_batch_history(): void
    {
        $this->postBatchReceipt();

        $result = TraceabilityService::forBatch('BATCH-TRACE-1');

        $this->assertSame('batch', $result['type']);
        $this->assertSame(1, $result['summary']['events']);
        $this->assertSame(5, $result['summary']['in']);
        $this->assertSame('GR-TRACE-1', $result['events'][0]['reference_number']);
    }

    public function test_livewire_trace_page(): void
    {
        $this->postBatchReceipt();

        Livewire::test(TraceabilityIndex::class)
            ->set('mode', 'batch')
            ->set('query', 'BATCH-TRACE-1')
            ->call('search')
            ->assertSee('GR-TRACE-1');
    }

    public function test_api_traceability_endpoint(): void
    {
        $this->postBatchReceipt();

        $this->getJson('/api/traceability/batch/BATCH-TRACE-1')
            ->assertOk()
            ->assertJsonPath('data.summary.in', 5)
            ->assertJsonPath('data.events.0.reference_number', 'GR-TRACE-1');
    }
}
