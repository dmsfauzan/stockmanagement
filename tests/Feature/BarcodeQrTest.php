<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Item;
use App\Models\Location;
use App\Models\Rack;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Zone;
use App\Services\Barcode\LabelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BarcodeQrTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_scan_lookup_by_barcode(): void
    {
        $admin = User::where('email', 'admin@stock.test')->firstOrFail();
        $item = Item::whereNotNull('barcode')->firstOrFail();

        Livewire::actingAs($admin)->test('scanning.scan-index')
            ->set('code', $item->barcode)
            ->call('lookup')
            ->assertSet('result.type', 'item')
            ->assertSet('result.item.id', $item->id);
    }

    public function test_scan_lookup_location(): void
    {
        $admin = User::where('email', 'admin@stock.test')->firstOrFail();
        $loc = Location::firstOrFail();

        Livewire::actingAs($admin)->test('scanning.scan-index')
            ->set('code', $loc->code)
            ->call('lookup')
            ->assertSet('locationResult.location.code', $loc->code)
            ->assertSet('result', null);
    }

    public function test_labels_require_auth(): void
    {
        $item = Item::firstOrFail();

        $this->get(route('labels.item', $item))->assertRedirect(route('login'));
        $this->get(route('labels.bulk'))->assertRedirect(route('login'));
    }

    public function test_label_service_generates_svgs(): void
    {
        $service = app(LabelService::class);
        $qr = $service->qrSvg('TEST123', 150);
        $barcode = $service->barcodeSvg('TEST123');

        $this->assertStringContainsString('<svg', $qr);
        $this->assertStringContainsString('<svg', $barcode);
    }

    public function test_goods_receipt_prefills_from_scan_query(): void
    {
        $admin = User::where('email', 'admin@stock.test')->firstOrFail();
        $item = Item::whereNotNull('barcode')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('goods-receipts.create', ['scan' => $item->sku]))
            ->assertOk();

        Livewire::actingAs($admin)->test('transactions.goods-receipt-form', ['receipt' => null])
            ->set('barcodeInput', $item->sku)
            ->call('addByBarcode')
            ->assertCount('items', 2);
    }
}
