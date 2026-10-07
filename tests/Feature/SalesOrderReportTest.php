<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SalesOrderReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_admin_can_open_sales_order_report_page(): void
    {
        $admin = User::where('email', 'admin@stock.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('reports.sales-order'))
            ->assertOk()
            ->assertSee('Laporan Sales Order');
    }

    public function test_sales_order_report_component_renders(): void
    {
        $admin = User::where('email', 'admin@stock.test')->firstOrFail();

        Livewire::actingAs($admin)
            ->test('reports.sales-order-report')
            ->assertSee('SO-DEMO-001');
    }

    public function test_sales_order_report_can_save_filter(): void
    {
        $admin = User::where('email', 'admin@stock.test')->firstOrFail();

        Livewire::actingAs($admin)
            ->test('reports.sales-order-report')
            ->set('search', 'SO-DEMO-001')
            ->set('savedFilterName', 'SO Demo')
            ->call('saveCurrentFilter');

        $this->assertDatabaseHas('saved_filters', [
            'user_id' => $admin->id,
            'report' => 'SalesOrderReport',
            'name' => 'SO Demo',
        ]);
    }

    public function test_admin_can_list_sales_orders_via_api(): void
    {
        $admin = User::where('email', 'admin@stock.test')->firstOrFail();
        $token = $admin->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/sales-orders')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'data', 'meta' => ['current_page', 'per_page', 'total']]);
    }
}
