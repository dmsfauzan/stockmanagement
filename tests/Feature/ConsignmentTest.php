<?php

namespace Tests\Feature;

use App\Livewire\MasterData\ItemForm;
use App\Livewire\MasterData\ItemIndex;
use App\Models\Category;
use App\Models\Item;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ConsignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->actingAs(User::where('email', 'admin@stock.test')->firstOrFail());
    }

    public function test_item_form_saves_consignment(): void
    {
        $supplier = Supplier::firstOrFail();

        Livewire::test(ItemForm::class)
            ->set('sku', 'CONS-1')
            ->set('name', 'Consignment Item')
            ->set('category_id', (string) Category::firstOrFail()->id)
            ->set('unit_id', (string) Unit::firstOrFail()->id)
            ->set('ownership', 'consignment')
            ->set('consignor_id', (string) $supplier->id)
            ->call('save');

        $item = Item::where('sku', 'CONS-1')->firstOrFail();
        $this->assertSame('consignment', $item->ownership);
        $this->assertSame($supplier->id, $item->consignor_id);
        $this->assertTrue($item->isConsignment());
    }

    public function test_owned_item_clears_consignor(): void
    {
        $item = Item::create([
            'sku' => 'CONS-2',
            'name' => 'Owned Item',
            'category_id' => Category::firstOrFail()->id,
            'unit_id' => Unit::firstOrFail()->id,
            'minimum_stock' => 0,
            'maximum_stock' => 0,
            'status' => 'active',
            'ownership' => 'consignment',
            'consignor_id' => Supplier::firstOrFail()->id,
        ]);

        Livewire::test(ItemForm::class, ['item' => $item->id])
            ->set('ownership', 'owned')
            ->call('save');

        $fresh = $item->fresh();
        $this->assertSame('owned', $fresh->ownership);
        $this->assertNull($fresh->consignor_id);
    }

    public function test_api_item_show_exposes_ownership(): void
    {
        $item = Item::create([
            'sku' => 'CONS-3',
            'name' => 'Consignment API',
            'category_id' => Category::firstOrFail()->id,
            'unit_id' => Unit::firstOrFail()->id,
            'minimum_stock' => 0,
            'maximum_stock' => 0,
            'status' => 'active',
            'ownership' => 'consignment',
            'consignor_id' => Supplier::firstOrFail()->id,
        ]);

        $this->getJson("/api/items/{$item->id}")
            ->assertOk()
            ->assertJsonPath('data.ownership', 'consignment');
    }

    public function test_item_index_filters_by_ownership(): void
    {
        Item::create([
            'sku' => 'CONS-FILTER',
            'name' => 'Consignment Filter',
            'category_id' => Category::firstOrFail()->id,
            'unit_id' => Unit::firstOrFail()->id,
            'minimum_stock' => 0,
            'maximum_stock' => 0,
            'status' => 'active',
            'ownership' => 'consignment',
        ]);

        Livewire::test(ItemIndex::class)
            ->set('ownershipFilter', 'consignment')
            ->assertSee('CONS-FILTER');
    }
}
