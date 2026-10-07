<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LabelPrintTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_item_label_supports_size_preset(): void
    {
        $admin = User::where('email', 'admin@stock.test')->firstOrFail();
        $item = Item::firstOrFail();

        $this->actingAs($admin)
            ->get(route('labels.item', ['item' => $item, 'size' => '50x30', 'format' => 'both']))
            ->assertOk()
            ->assertSee('50 × 30');
    }

    public function test_bulk_label_can_filter_by_category(): void
    {
        $admin = User::where('email', 'admin@stock.test')->firstOrFail();
        $category = Category::firstOrFail();

        $this->actingAs($admin)
            ->get(route('labels.bulk', ['category_id' => $category->id, 'size' => '70x40']))
            ->assertOk()
            ->assertSee('Label Massal');
    }

    public function test_print_endpoint_renders_sheet(): void
    {
        $admin = User::where('email', 'admin@stock.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('labels.print'))
            ->assertOk()
            ->assertSee('label-grid');
    }

    public function test_invalid_size_falls_back_to_default(): void
    {
        $admin = User::where('email', 'admin@stock.test')->firstOrFail();
        $item = Item::firstOrFail();

        $this->actingAs($admin)
            ->get(route('labels.item', ['item' => $item, 'size' => '999x999']))
            ->assertOk()
            ->assertSee('85 × 54');
    }
}
