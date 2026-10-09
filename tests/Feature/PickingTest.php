<?php

namespace Tests\Feature;

use App\Livewire\Picking\PickListShow;
use App\Models\Category;
use App\Models\GoodsIssue;
use App\Models\Item;
use App\Models\Location;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\PickService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PickingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->actingAs(User::where('email', 'admin@stock.test')->firstOrFail());
    }

    private function approvedIssue(): GoodsIssue
    {
        $item = Item::create([
            'sku' => 'PICK-1',
            'name' => 'Pick Item',
            'category_id' => Category::firstOrFail()->id,
            'unit_id' => Unit::firstOrFail()->id,
            'minimum_stock' => 0,
            'maximum_stock' => 0,
            'status' => 'active',
        ]);

        $warehouse = Warehouse::where('code', 'WH-JKT')->firstOrFail();
        $location = Location::where('code', 'A01-01')->firstOrFail();

        $issue = GoodsIssue::create([
            'number' => 'GI-PICK-'.uniqid(), 'transaction_date' => today(),
            'warehouse_id' => $warehouse->id, 'status' => 'approved',
            'destination' => 'Test',
            'created_by' => auth()->id(), 'approved_by' => auth()->id(), 'approved_at' => now(),
        ]);

        $issue->issueItems()->create([
            'item_id' => $item->id, 'quantity' => 3,
            'unit_id' => $item->unit_id, 'location_id' => $location->id,
        ]);

        return $issue->fresh('issueItems');
    }

    public function test_generate_pick_list_from_approved_issue(): void
    {
        $pickList = PickService::generateFromIssue($issue = $this->approvedIssue());

        $this->assertSame('pending', $pickList->status->value);
        $this->assertCount(1, $pickList->items);
        $this->assertDatabaseHas('pick_lists', ['number' => $pickList->number]);
    }

    public function test_confirm_and_complete_picking(): void
    {
        $pickList = PickService::generateFromIssue($this->approvedIssue());
        $itemId = $pickList->items->first()->id;

        PickService::confirmItem($pickList->fresh('items'), $itemId, 3);
        PickService::complete($pickList->fresh('items'));

        $this->assertSame('picked', $pickList->fresh()->status->value);
    }

    public function test_packing_after_picked(): void
    {
        $pickList = PickService::generateFromIssue($this->approvedIssue());
        $itemId = $pickList->items->first()->id;

        PickService::confirmItem($pickList->fresh('items'), $itemId, 3);
        PickService::complete($pickList->fresh('items'));
        PickService::pack($pickList->fresh('items'));

        $this->assertSame('packed', $pickList->fresh()->status->value);
    }

    public function test_livewire_show_page_progresses_picking(): void
    {
        $pickList = PickService::generateFromIssue($this->approvedIssue());
        $itemId = $pickList->items->first()->id;

        Livewire::test(PickListShow::class, ['pickList' => $pickList->id])
            ->set('picks.'.$itemId.'.picked_quantity', 3)
            ->call('confirmItem', $itemId)
            ->call('complete')
            ->call('pack')
            ->assertSee(__('Dikemas'));
    }

    public function test_api_picking_endpoints(): void
    {
        $pickList = PickService::generateFromIssue($this->approvedIssue());
        $itemId = $pickList->items->first()->id;

        $this->getJson('/api/pick-lists')->assertOk()->assertJsonPath('data.0.number', $pickList->number);
        $this->postJson("/api/pick-lists/{$pickList->id}/items/{$itemId}/confirm", ['picked_quantity' => 3])->assertOk();
        $this->postJson("/api/pick-lists/{$pickList->id}/complete")->assertOk();
        $this->postJson("/api/pick-lists/{$pickList->id}/pack")->assertOk()->assertJsonPath('data', null);
    }
}
