<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TransactionExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function admin(): User
    {
        return User::where('email', 'admin@stock.test')->firstOrFail();
    }

    public function test_goods_receipt_export(): void
    {
        Livewire::actingAs($this->admin())->test('transactions.goods-receipt-index')
            ->call('export')
            ->assertHasNoErrors();
    }

    public function test_goods_issue_export(): void
    {
        Livewire::actingAs($this->admin())->test('transactions.goods-issue-index')
            ->call('export')
            ->assertHasNoErrors();
    }

    public function test_stock_adjustment_export(): void
    {
        Livewire::actingAs($this->admin())->test('transactions.stock-adjustment-index')
            ->call('export')
            ->assertHasNoErrors();
    }

    public function test_stock_opname_export(): void
    {
        Livewire::actingAs($this->admin())->test('transactions.stock-opname-index')
            ->call('export')
            ->assertHasNoErrors();
    }

    public function test_stock_transfer_export(): void
    {
        Livewire::actingAs($this->admin())->test('transactions.stock-transfer-index')
            ->call('export')
            ->assertHasNoErrors();
    }

    public function test_purchase_order_export(): void
    {
        Livewire::actingAs($this->admin())->test('transactions.purchase-order-index')
            ->call('export')
            ->assertHasNoErrors();
    }
}
