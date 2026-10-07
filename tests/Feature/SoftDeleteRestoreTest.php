<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Item;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SoftDeleteRestoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_category_delete_is_soft_and_restorable(): void
    {
        $admin = User::where('email', 'admin@stock.test')->firstOrFail();

        Livewire::actingAs($admin)->test('master-data.category-index')
            ->set('code', 'TMP-CAT')
            ->set('name', 'Tmp')
            ->set('status', 'active')
            ->call('save');

        $category = Category::where('code', 'TMP-CAT')->firstOrFail();

        Livewire::actingAs($admin)->test('master-data.category-index')
            ->call('delete', $category->id)
            ->assertDispatched('toast');

        $this->assertSoftDeleted('categories', ['id' => $category->id]);

        Livewire::actingAs($admin)->test('master-data.category-index')
            ->call('restore', $category->id)
            ->assertDispatched('toast');

        $this->assertNotSoftDeleted('categories', ['id' => $category->id]);
    }

    public function test_supplier_bulk_delete_and_restore(): void
    {
        $admin = User::where('email', 'admin@stock.test')->firstOrFail();

        $first = Supplier::create(['code' => 'SUP-TMP-1', 'name' => 'Tmp 1']);
        $second = Supplier::create(['code' => 'SUP-TMP-2', 'name' => 'Tmp 2']);

        Livewire::actingAs($admin)->test('master-data.supplier-index')
            ->set('selectedIds', [$first->id, $second->id])
            ->call('bulkDelete')
            ->assertDispatched('toast');

        $this->assertSoftDeleted('suppliers', ['id' => $first->id]);
        $this->assertSoftDeleted('suppliers', ['id' => $second->id]);

        Livewire::actingAs($admin)->test('master-data.supplier-index')
            ->set('selectedIds', [$first->id, $second->id])
            ->call('bulkRestore')
            ->assertDispatched('toast');

        $this->assertNotSoftDeleted('suppliers', ['id' => $first->id]);
        $this->assertNotSoftDeleted('suppliers', ['id' => $second->id]);
    }

    public function test_item_delete_is_soft_and_restorable(): void
    {
        $admin = User::where('email', 'admin@stock.test')->firstOrFail();
        $base = Item::query()->firstOrFail();

        $item = Item::create([
            'sku' => 'BRG-REST-'.uniqid(),
            'name' => 'Item Restore',
            'category_id' => $base->category_id,
            'unit_id' => $base->unit_id,
            'minimum_stock' => 0,
            'maximum_stock' => 0,
            'status' => 'active',
        ]);

        Livewire::actingAs($admin)->test('master-data.item-index')
            ->call('deleteItem', $item->id)
            ->assertDispatched('toast');

        $this->assertSoftDeleted('items', ['id' => $item->id]);

        Livewire::actingAs($admin)->test('master-data.item-index')
            ->call('restore', $item->id)
            ->assertDispatched('toast');

        $this->assertNotSoftDeleted('items', ['id' => $item->id]);
    }

    public function test_item_bulk_restore(): void
    {
        $admin = User::where('email', 'admin@stock.test')->firstOrFail();
        $base = Item::query()->firstOrFail();

        $first = Item::create(['sku' => 'BRG-BR-1', 'name' => 'Bulk 1', 'category_id' => $base->category_id, 'unit_id' => $base->unit_id, 'status' => 'active']);
        $second = Item::create(['sku' => 'BRG-BR-2', 'name' => 'Bulk 2', 'category_id' => $base->category_id, 'unit_id' => $base->unit_id, 'status' => 'active']);

        Livewire::actingAs($admin)->test('master-data.item-index')
            ->set('selectedIds', [$first->id, $second->id])
            ->call('bulkDelete')
            ->assertDispatched('toast');

        $this->assertSoftDeleted('items', ['id' => $first->id]);
        $this->assertSoftDeleted('items', ['id' => $second->id]);

        Livewire::actingAs($admin)->test('master-data.item-index')
            ->set('selectedIds', [$first->id, $second->id])
            ->call('bulkRestore')
            ->assertDispatched('toast');

        $this->assertNotSoftDeleted('items', ['id' => $first->id]);
        $this->assertNotSoftDeleted('items', ['id' => $second->id]);
    }

    public function test_create_conflicts_when_code_is_trashed(): void
    {
        $admin = User::where('email', 'admin@stock.test')->firstOrFail();

        Livewire::actingAs($admin)->test('master-data.category-index')
            ->set('code', 'TMP-DUP')
            ->set('name', 'Tmp Dup')
            ->set('status', 'active')
            ->call('save');

        $category = Category::where('code', 'TMP-DUP')->firstOrFail();

        Livewire::actingAs($admin)->test('master-data.category-index')
            ->call('delete', $category->id);

        $this->assertSoftDeleted('categories', ['id' => $category->id]);

        $component = Livewire::actingAs($admin)->test('master-data.category-index')
            ->set('code', 'TMP-DUP')
            ->set('name', 'Tmp Dup Baru')
            ->set('status', 'active')
            ->call('save');

        $this->assertEquals(1, Category::withTrashed()->where('code', 'TMP-DUP')->count());
        $component->assertDispatched('toast');
    }
}
