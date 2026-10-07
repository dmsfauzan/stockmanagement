<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Item;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ItemImageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('public');
    }

    public function test_item_image_stored_on_create(): void
    {
        $admin = User::where('email', 'admin@stock.test')->firstOrFail();

        $category = Category::firstOrFail();
        $unit = Unit::firstOrFail();

        Livewire::actingAs($admin)->test('master-data.item-form', ['item' => null])
            ->set('sku', 'BRG-IMG-1')
            ->set('name', 'Item Berfoto')
            ->set('category_id', (string) $category->id)
            ->set('unit_id', (string) $unit->id)
            ->set('minimum_stock', 0)
            ->set('maximum_stock', 10)
            ->set('status', 'active')
            ->set('image', UploadedFile::fake()->image('foto.jpg', 600, 600))
            ->call('save');

        $item = Item::where('sku', 'BRG-IMG-1')->firstOrFail();
        $this->assertNotNull($item->image_path);
        Storage::disk('public')->assertExists($item->image_path);
    }

    public function test_avatar_can_be_saved_and_removed(): void
    {
        $admin = User::where('email', 'admin@stock.test')->firstOrFail();

        Livewire::actingAs($admin)->test('profile.avatar-form')
            ->set('avatar', UploadedFile::fake()->image('me.jpg', 300, 300))
            ->call('save');

        $admin->refresh();
        $this->assertNotNull($admin->avatar_path);
        Storage::disk('public')->assertExists($admin->avatar_path);

        Livewire::actingAs($admin)->test('profile.avatar-form')->call('remove');
        $this->assertNull($admin->fresh()->avatar_path);
    }
}
