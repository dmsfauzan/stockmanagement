<?php

namespace Tests\Feature;

use App\Livewire\Transactions\GoodsReceiptIndex;
use App\Models\Category;
use App\Models\GoodsReceipt;
use App\Models\Item;
use App\Models\Location;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BulkActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->actingAs(User::where('email', 'admin@stock.test')->firstOrFail());
    }

    private function makeReceipt(string $status): GoodsReceipt
    {
        $item = Item::create([
            'sku' => 'BULK-'.uniqid(),
            'name' => 'Bulk Item',
            'category_id' => Category::firstOrFail()->id,
            'unit_id' => Unit::firstOrFail()->id,
            'minimum_stock' => 0,
            'maximum_stock' => 0,
            'status' => 'active',
        ]);

        $warehouse = Warehouse::where('code', 'WH-JKT')->firstOrFail();
        $location = Location::where('code', 'A01-01')->firstOrFail();

        $receipt = GoodsReceipt::create([
            'number' => 'GR-B-'.uniqid(), 'transaction_date' => today(),
            'supplier_id' => Supplier::firstOrFail()->id,
            'warehouse_id' => $warehouse->id, 'status' => $status,
            'created_by' => auth()->id(),
        ]);

        $receipt->receiptItems()->create([
            'item_id' => $item->id, 'quantity' => 2, 'unit_cost' => 100,
            'unit_id' => $item->unit_id, 'location_id' => $location->id,
        ]);

        return $receipt;
    }

    public function test_bulk_approve_submitted_receipts(): void
    {
        $a = $this->makeReceipt('submitted');
        $b = $this->makeReceipt('submitted');

        Livewire::test(GoodsReceiptIndex::class)
            ->set('selectedIds', [$a->id, $b->id])
            ->call('bulkApprove');

        $this->assertSame('approved', $a->fresh()->status);
        $this->assertSame('approved', $b->fresh()->status);
    }

    public function test_bulk_post_skips_ineligible(): void
    {
        $pending = $this->makeReceipt('submitted');
        $ready = $this->makeReceipt('approved');

        Livewire::test(GoodsReceiptIndex::class)
            ->set('selectedIds', [$pending->id, $ready->id])
            ->call('bulkPost');

        $this->assertSame('submitted', $pending->fresh()->status);
        $this->assertSame('posted', $ready->fresh()->status);
    }
}
