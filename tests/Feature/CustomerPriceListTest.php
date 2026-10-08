<?php

namespace Tests\Feature;

use App\Livewire\MasterData\CustomerShow;
use App\Livewire\MasterData\ItemForm;
use App\Livewire\Transactions\SalesOrderForm;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerItemPrice;
use App\Models\Item;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerPriceListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_price_for_resolves_best_tier(): void
    {
        $customer = Customer::where('code', 'CUST001')->firstOrFail();
        $item = Item::where('sku', 'BRG-001')->firstOrFail();

        CustomerItemPrice::create(['customer_id' => $customer->id, 'item_id' => $item->id, 'min_quantity' => 1, 'price' => 20000]);
        CustomerItemPrice::create(['customer_id' => $customer->id, 'item_id' => $item->id, 'min_quantity' => 10, 'price' => 18000]);

        $this->assertSame('20000.00', (string) $customer->priceFor($item->id, 1)->price);
        $this->assertSame('20000.00', (string) $customer->priceFor($item->id, 9)->price);
        $this->assertSame('18000.00', (string) $customer->priceFor($item->id, 10)->price);
        $this->assertSame('18000.00', (string) $customer->priceFor($item->id, 100)->price);
        $this->assertNull($customer->priceFor(Item::where('sku', 'BRG-002')->value('id'), 1));
    }

    public function test_duplicate_tier_is_rejected(): void
    {
        $this->expectException(QueryException::class);

        $customer = Customer::where('code', 'CUST001')->firstOrFail();
        $item = Item::where('sku', 'BRG-001')->firstOrFail();

        CustomerItemPrice::create(['customer_id' => $customer->id, 'item_id' => $item->id, 'min_quantity' => 1, 'price' => 20000]);
        CustomerItemPrice::create(['customer_id' => $customer->id, 'item_id' => $item->id, 'min_quantity' => 1, 'price' => 19000]);
    }

    public function test_customer_show_add_and_delete_price(): void
    {
        $admin = User::where('email', 'admin@stock.test')->firstOrFail();
        $customer = Customer::where('code', 'CUST001')->firstOrFail();
        $item = Item::where('sku', 'BRG-001')->firstOrFail();

        Livewire::actingAs($admin)->test(CustomerShow::class, ['customer' => $customer])
            ->set('priceItemId', (string) $item->id)
            ->set('priceMinQty', '5')
            ->set('pricePrice', '17500')
            ->set('priceNotes', 'promo')
            ->call('addPrice')
            ->assertDispatched('toast');

        $this->assertDatabaseHas('customer_item_prices', [
            'customer_id' => $customer->id,
            'item_id' => $item->id,
            'min_quantity' => 5,
        ]);

        $price = CustomerItemPrice::where('customer_id', $customer->id)->where('item_id', $item->id)->firstOrFail();

        Livewire::actingAs($admin)->test(CustomerShow::class, ['customer' => $customer])
            ->call('deletePrice', $price->id)
            ->assertDispatched('toast');

        $this->assertDatabaseMissing('customer_item_prices', ['id' => $price->id]);
    }

    public function test_so_form_prefills_customer_tier_then_item_price(): void
    {
        $admin = User::where('email', 'admin@stock.test')->firstOrFail();
        $customer = Customer::where('code', 'CUST001')->firstOrFail();
        $warehouse = Warehouse::where('code', 'WH-JKT')->firstOrFail();
        $item = Item::where('sku', 'BRG-001')->firstOrFail();

        $item->forceFill(['price' => 25000])->save();
        CustomerItemPrice::create(['customer_id' => $customer->id, 'item_id' => $item->id, 'min_quantity' => 10, 'price' => 22000]);

        $component = Livewire::actingAs($admin)->test(SalesOrderForm::class);
        $component->set('customer_id', (string) $customer->id);
        $component->set('items.0.item_id', (string) $item->id);
        $component->call('selectItem', 0);

        // qty=1: no tier matched -> falls back to item selling price.
        $this->assertSame('25000', (string) $component->get('items.0.unit_price'));

        // qty=10: tier matched.
        $component->set('items.0.quantity', 10);
        $this->assertSame('22000', (string) $component->get('items.0.unit_price'));
    }

    public function test_customer_prices_api(): void
    {
        $admin = User::where('email', 'admin@stock.test')->firstOrFail();
        $customer = Customer::where('code', 'CUST001')->firstOrFail();
        $item = Item::where('sku', 'BRG-001')->firstOrFail();

        CustomerItemPrice::create(['customer_id' => $customer->id, 'item_id' => $item->id, 'min_quantity' => 10, 'price' => 22000]);

        $token = $admin->createToken('test', ['items.view'])->plainTextToken;

        $this->withToken($token)
            ->getJson("/api/customers/{$customer->id}/prices")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['min_quantity' => 10]);
    }

    public function test_item_selling_price_is_persisted(): void
    {
        $admin = User::where('email', 'admin@stock.test')->firstOrFail();

        $this->assertDatabaseMissing('items', ['sku' => 'BRG-PL-1']);

        Livewire::actingAs($admin)->test(ItemForm::class)
            ->set('sku', 'BRG-PL-1')
            ->set('barcode', 'pl1')
            ->set('name', 'Price List Item')
            ->set('category_id', (string) Category::firstOrFail()->id)
            ->set('unit_id', (string) Unit::firstOrFail()->id)
            ->set('minimum_stock', 0)
            ->set('maximum_stock', 10)
            ->set('cost', 10000)
            ->set('price', 12500)
            ->set('status', 'active')
            ->set('tracking_type', 'none')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('items', ['sku' => 'BRG-PL-1', 'price' => 12500]);
    }
}
