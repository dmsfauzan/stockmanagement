<?php

namespace Tests\Feature;

use App\Livewire\Transactions\PurchaseRequisitionForm;
use App\Livewire\Transactions\PurchaseRequisitionIndex;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequisition;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Workflow\RequisitionWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RequisitionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->actingAs(User::where('email', 'admin@stock.test')->firstOrFail());
    }

    private function item(): Item
    {
        return Item::where('sku', 'BRG-001')->firstOrFail();
    }

    private function createRequisition(): PurchaseRequisition
    {
        $item = $this->item();

        $req = PurchaseRequisition::create([
            'number' => 'PR-T-1',
            'request_date' => today(),
            'warehouse_id' => Warehouse::where('code', 'WH-JKT')->value('id'),
            'requester_id' => auth()->id(),
            'status' => 'draft',
            'created_by' => auth()->id(),
        ]);

        $req->items()->create([
            'item_id' => $item->id,
            'unit_id' => $item->unit_id,
            'quantity' => 5,
            'estimated_price' => 1000,
        ]);

        return $req;
    }

    public function test_workflow_submit_approve_convert(): void
    {
        $req = $this->createRequisition();

        RequisitionWorkflow::submit($req->id);
        $this->assertSame('submitted', $req->fresh()->status);

        RequisitionWorkflow::approve($req->id);
        $this->assertSame('approved', $req->fresh()->status);

        $order = RequisitionWorkflow::convertToPurchaseOrder($req->id, Supplier::firstOrFail()->id);

        $this->assertInstanceOf(PurchaseOrder::class, $order);
        $this->assertSame($order->id, $req->fresh()->converted_purchase_order_id);
        $this->assertCount(1, $order->items);
    }

    public function test_cannot_convert_twice(): void
    {
        $req = $this->createRequisition();
        RequisitionWorkflow::submit($req->id);
        RequisitionWorkflow::approve($req->id);
        RequisitionWorkflow::convertToPurchaseOrder($req->id, Supplier::firstOrFail()->id);

        $this->expectException(\RuntimeException::class);
        RequisitionWorkflow::convertToPurchaseOrder($req->id, Supplier::firstOrFail()->id);
    }

    public function test_livewire_form_creates_and_submits(): void
    {
        $item = $this->item();

        Livewire::test(PurchaseRequisitionForm::class)
            ->set('warehouse_id', (string) Warehouse::where('code', 'WH-JKT')->value('id'))
            ->set('items.0.item_id', (string) $item->id)
            ->set('items.0.quantity', 3)
            ->set('items.0.unit_id', (string) $item->unit_id)
            ->set('items.0.estimated_price', '500')
            ->call('save', true);

        $req = PurchaseRequisition::latest('id')->first();
        $this->assertSame('submitted', $req->status);
        $this->assertSame(1, $req->items()->count());
    }

    public function test_index_approve_and_convert(): void
    {
        $req = $this->createRequisition();

        Livewire::test(PurchaseRequisitionIndex::class)
            ->call('submit', $req->id)
            ->call('approve', $req->id)
            ->call('convert', $req->id);

        $this->assertNotNull($req->fresh()->converted_purchase_order_id);
    }

    public function test_api_requisition_flow(): void
    {
        $item = $this->item();

        $response = $this->postJson('/api/requisitions', [
            'request_date' => today()->toDateString(),
            'warehouse_id' => Warehouse::where('code', 'WH-JKT')->value('id'),
            'submit' => true,
            'items' => [
                ['item_id' => $item->id, 'quantity' => 2, 'unit_id' => $item->unit_id, 'estimated_price' => 100],
            ],
        ])->assertCreated();

        $id = $response->json('data.id');

        $this->postJson("/api/requisitions/{$id}/approve")->assertOk();
        $this->postJson("/api/requisitions/{$id}/convert", ['supplier_id' => Supplier::firstOrFail()->id])->assertOk();

        $this->getJson('/api/requisitions')->assertOk();
    }
}
