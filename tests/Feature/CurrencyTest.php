<?php

namespace Tests\Feature;

use App\Livewire\Admin\CurrencyIndex;
use App\Livewire\Transactions\PurchaseOrderForm;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Support\CurrencyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->actingAs(User::where('email', 'admin@stock.test')->firstOrFail());
    }

    public function test_base_currency_and_conversion(): void
    {
        $usd = Currency::where('code', 'USD')->firstOrFail();
        ExchangeRate::create(['currency_id' => $usd->id, 'effective_date' => today(), 'rate' => 16000]);

        $this->assertSame('IDR', CurrencyService::baseCode());
        $this->assertSame(16000.0, CurrencyService::rate('USD'));
        $this->assertSame(160000.0, CurrencyService::toBase(10, 'USD'));
        $this->assertSame(10.0, CurrencyService::convert(160000, 'IDR', 'USD'));
    }

    public function test_admin_can_save_currency_and_rate(): void
    {
        Livewire::test(CurrencyIndex::class)
            ->call('openCreate')
            ->set('code', 'EUR')
            ->set('name', 'Euro')
            ->set('symbol', '€')
            ->set('rate', '17500')
            ->call('save');

        $eur = Currency::where('code', 'EUR')->firstOrFail();
        $this->assertSame(17500.0, CurrencyService::rate('EUR'));
    }

    public function test_purchase_order_stores_currency(): void
    {
        $usd = Currency::where('code', 'USD')->firstOrFail();
        ExchangeRate::create(['currency_id' => $usd->id, 'effective_date' => today(), 'rate' => 16000]);

        $item = Item::where('sku', 'BRG-001')->firstOrFail();

        Livewire::test(PurchaseOrderForm::class)
            ->set('supplier_id', (string) Supplier::firstOrFail()->id)
            ->set('warehouse_id', (string) Warehouse::where('code', 'WH-JKT')->value('id'))
            ->set('currency_code', 'USD')
            ->set('items.0.item_id', (string) $item->id)
            ->set('items.0.quantity', 2)
            ->set('items.0.unit_id', (string) $item->unit_id)
            ->set('items.0.unit_price', '5')
            ->call('save');

        $po = PurchaseOrder::latest('id')->first();
        $this->assertSame('USD', $po->currency_code);
        $this->assertSame(16000.0, (float) $po->exchange_rate);
    }

    public function test_api_currencies_and_convert(): void
    {
        $usd = Currency::where('code', 'USD')->firstOrFail();
        ExchangeRate::create(['currency_id' => $usd->id, 'effective_date' => today(), 'rate' => 16000]);

        $this->getJson('/api/currencies')->assertOk()->assertJsonPath('data.base', 'IDR');

        $this->getJson('/api/currency/convert?amount=10&from=USD&to=IDR')
            ->assertOk()
            ->assertJsonPath('data.converted', 160000);
    }
}
