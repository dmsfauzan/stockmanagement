<?php

namespace Tests\Feature;

use App\Imports\ItemsImport;
use App\Models\Category;
use App\Models\Item;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ItemImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_import_creates_and_updates_items(): void
    {
        Category::updateOrCreate(['code' => 'ELEC'], ['name' => 'Electronics', 'status' => 'active']);
        Unit::updateOrCreate(['code' => 'PCS'], ['name' => 'Pieces']);

        $import = new ItemsImport;
        $import->collection(new Collection([
            ['sku' => 'BRG-NEW', 'name' => 'Item Baru', 'category_code' => 'ELEC', 'unit_code' => 'PCS', 'minimum_stock' => 5, 'maximum_stock' => 50, 'status' => 'active'],
            ['sku' => 'BRG-001', 'name' => 'Nama Diperbarui', 'category_code' => 'ELEC', 'unit_code' => 'PCS', 'minimum_stock' => 10, 'maximum_stock' => 500, 'status' => 'active'],
            ['sku' => '', 'name' => '', 'category_code' => 'ELEC', 'unit_code' => 'PCS'],
            ['sku' => 'BRG-BAD', 'name' => 'Item Salah', 'category_code' => 'NOPE', 'unit_code' => 'PCS'],
        ]));

        $this->assertEquals(1, $import->imported);
        $this->assertEquals(1, $import->updated);
        $this->assertCount(2, $import->errors);
        $this->assertTrue(Item::where('sku', 'BRG-NEW')->exists());
        $this->assertEquals('Nama Diperbarui', Item::where('sku', 'BRG-001')->first()?->name);
    }

    public function test_import_modal_requires_create_permission_for_staff(): void
    {
        $staff = User::where('email', 'staff@stock.test')->firstOrFail();
        $forbidden = $staff->hasPermission('items.create');
        $this->assertFalse($forbidden);
    }
}
