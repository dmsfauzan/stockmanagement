<?php

namespace Tests\Feature;

use App\Livewire\MasterData\SupplierIndex;
use App\Livewire\MasterData\SupplierShow;
use App\Models\Category;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\SupplierItemPrice;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Models\Zone;
use App\Models\Rack;
use App\Models\Location;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class SupplierAdvancedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function admin(): \App\Models\User
    {
        return \App\Models\User::where('email', 'admin@stock.test')->firstOrFail();
    }

    public function test_create_supplier_via_livewire_index_with_advanced_fields(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)->test(SupplierIndex::class)
            ->call('openCreate')
            ->set('code', 'SUP-NEW')
            ->set('name', 'Supplier Baru')
            ->set('lead_time_days', 12)
            ->set('payment_terms', 'NET 45')
            ->set('region', 'Medan')
            ->set('status', 'active')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('suppliers', [
            'code' => 'SUP-NEW',
            'lead_time_days' => 12,
            'payment_terms' => 'NET 45',
            'region' => 'Medan',
        ]);
    }

    public function test_add_price_list_entry_via_supplier_show(): void
    {
        $admin = $this->admin();
        $supplier = \App\Models\Supplier::where('code', 'SUP001')->firstOrFail();
        $item = Item::where('sku', 'BRG-002')->firstOrFail();

        SupplierItemPrice::where('supplier_id', $supplier->id)->where('item_id', $item->id)->delete();

        Livewire::actingAs($admin)->test(SupplierShow::class, ['supplier' => $supplier->id])
            ->set('priceItemId', (string) $item->id)
            ->set('pricePrice', '27500')
            ->set('priceLeadTime', '9')
            ->set('priceNotes', 'Harga khusus')
            ->call('addPrice')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('supplier_item_prices', [
            'supplier_id' => $supplier->id,
            'item_id' => $item->id,
            'price' => 27500,
            'lead_time_days' => 9,
        ]);
    }

    public function test_duplicate_price_entry_validation_fails_unique(): void
    {
        $admin = $this->admin();
        $supplier = \App\Models\Supplier::where('code', 'SUP001')->firstOrFail();
        $item = Item::where('sku', 'BRG-001')->firstOrFail();

        SupplierItemPrice::updateOrCreate(
            ['supplier_id' => $supplier->id, 'item_id' => $item->id],
            ['price' => 15000, 'lead_time_days' => 7]
        );

        Livewire::actingAs($admin)->test(SupplierShow::class, ['supplier' => $supplier->id])
            ->set('priceItemId', (string) $item->id)
            ->set('pricePrice', '16000')
            ->set('priceLeadTime', '7')
            ->call('addPrice')
            ->assertHasErrors(['priceItemId']);
    }

    public function test_performance_score_on_time_vs_late_between_0_and_100(): void
    {
        $admin = $this->admin();
        $supplier = \App\Models\Supplier::create([
            'code' => 'SUP-PERF',
            'name' => 'Supplier Perf',
            'status' => 'active',
            'lead_time_days' => 7,
            'payment_terms' => 'NET 30',
            'region' => 'Jakarta',
        ]);

        $unit = Unit::firstOrFail();
        $cat = Category::where('code', 'ELEC')->first() ?? Category::first();
        if (! $cat) {
            $cat = Category::create(['code' => 'ELEC', 'name' => 'Electronics', 'status' => 'active']);
        }

        $warehouse = Warehouse::firstOrFail();
        if (! $warehouse) {
            $warehouse = Warehouse::create(['code' => 'WH-TST', 'name' => 'Wh Test', 'status' => 'active']);
        }

        $item = Item::where('sku', 'BRG-001')->first();
        if (! $item) {
            $item = Item::create([
                'sku' => 'BRG-PERF', 'name' => 'Perf Item', 'category_id' => $cat->id,
                'unit_id' => $unit->id, 'minimum_stock' => 1, 'maximum_stock' => 100, 'cost' => 10000, 'status' => 'active',
            ]);
        }

        SupplierItemPrice::updateOrCreate(
            ['supplier_id' => $supplier->id, 'item_id' => $item->id],
            ['price' => 10000, 'lead_time_days' => 7]
        );

        $onTime = PurchaseOrder::create([
            'number' => 'PO-PERF-ON-'.time(),
            'order_date' => Carbon::now()->subDays(10)->toDateString(),
            'expected_date' => Carbon::now()->addDays(5)->toDateString(),
            'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'status' => 'approved',
            'created_by' => $admin->id,
        ]);
        $onTime->items()->create([
            'item_id' => $item->id, 'quantity' => 10, 'received_quantity' => 0, 'unit_id' => $unit->id, 'unit_price' => 10000,
        ]);
        $grOn = \App\Models\GoodsReceipt::create([
            'number' => 'GR-PERF-ON-'.time(),
            'transaction_date' => Carbon::now()->toDateString(),
            'supplier_id' => $supplier->id,
            'purchase_order_id' => $onTime->id,
            'warehouse_id' => $warehouse->id,
            'status' => 'posted',
            'posted_at' => Carbon::now(),
            'posted_by' => $admin->id,
            'created_by' => $admin->id,
        ]);
        $loc = Location::first();
        if ($loc) {
            $grOn->receiptItems()->create(['item_id' => $item->id, 'quantity' => 10, 'unit_id' => $unit->id, 'location_id' => $loc->id]);
        }

        $late = PurchaseOrder::create([
            'number' => 'PO-PERF-LA-'.time(),
            'order_date' => Carbon::now()->subDays(20)->toDateString(),
            'expected_date' => Carbon::now()->subDays(5)->toDateString(),
            'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'status' => 'approved',
            'created_by' => $admin->id,
        ]);
        $late->items()->create([
            'item_id' => $item->id, 'quantity' => 5, 'received_quantity' => 0, 'unit_id' => $unit->id, 'unit_price' => 11000,
        ]);
        $grLate = \App\Models\GoodsReceipt::create([
            'number' => 'GR-PERF-LA-'.time(),
            'transaction_date' => Carbon::now()->toDateString(),
            'supplier_id' => $supplier->id,
            'purchase_order_id' => $late->id,
            'warehouse_id' => $warehouse->id,
            'status' => 'posted',
            'posted_at' => Carbon::now(),
            'posted_by' => $admin->id,
            'created_by' => $admin->id,
        ]);
        if ($loc) {
            $grLate->receiptItems()->create(['item_id' => $item->id, 'quantity' => 5, 'unit_id' => $unit->id, 'location_id' => $loc->id]);
        }

        $component = Livewire::actingAs($admin)->test(SupplierShow::class, ['supplier' => $supplier->id]);
        $viewData = $component->viewData('performance');
        if ($viewData === null) {
            $componentInstance = $component->instance();
            $supplierFresh = \App\Models\Supplier::withCount('primaryItems')->findOrFail($supplier->id);
            $orders = PurchaseOrder::where('supplier_id', $supplierFresh->id)->with(['goodsReceipts', 'items'])->get();
            $ref = new \ReflectionMethod($componentInstance, 'computePerformance');
            $ref->setAccessible(true);
            $viewData = $ref->invoke($componentInstance, $supplierFresh, $orders);
        }

        $this->assertGreaterThan(0, $viewData['on_time_rate']);
        $this->assertLessThan(100, $viewData['on_time_rate']);
        $this->assertGreaterThan(0, $viewData['score']);
        $this->assertLessThanOrEqual(100, $viewData['score']);
        $this->assertEquals(50.0, $viewData['on_time_rate']);
    }
}
