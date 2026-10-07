<?php

namespace Tests\Feature;

use App\Livewire\Transactions\SalesOrderForm;
use App\Livewire\Transactions\SalesOrderShow;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Item;
use App\Models\SalesOrder;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Support\DocumentNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class SalesOrderTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Warehouse $warehouse;

    private Customer $customer;

    private Item $item1;

    private Item $item2;

    private Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $roleId = DB::table('roles')->insertGetId(['name' => 'Administrator', 'slug' => 'admin', 'is_system' => true, 'created_at' => now(), 'updated_at' => now()]);
        $permId = DB::table('permissions')->insertGetId(['slug' => 'admin', 'name' => 'All', 'group' => 'system', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('role_permission')->insert(['role_id' => $roleId, 'permission_id' => $permId]);

        $this->admin = User::factory()->create();
        DB::table('user_role')->insert(['user_id' => $this->admin->id, 'role_id' => $roleId]);
        $this->actingAs($this->admin);

        $this->unit = Unit::create(['code' => 'PCS', 'name' => 'Pieces']);
        $cat = Category::create(['code' => 'ELEC', 'name' => 'Electronics', 'status' => 'active']);
        $this->customer = Customer::create(['code' => 'CUST001', 'name' => 'PT Maju Jaya', 'type' => 'company', 'status' => 'active']);
        $this->warehouse = Warehouse::create(['code' => 'WH-TST', 'name' => 'Test Warehouse', 'status' => 'active']);
        $this->item1 = Item::create([
            'sku' => 'BRG-001', 'name' => 'Wireless Mouse', 'category_id' => $cat->id,
            'unit_id' => $this->unit->id, 'minimum_stock' => 5, 'maximum_stock' => 100, 'status' => 'active',
        ]);
        $this->item2 = Item::create([
            'sku' => 'BRG-002', 'name' => 'Mechanical Keyboard', 'category_id' => $cat->id,
            'unit_id' => $this->unit->id, 'minimum_stock' => 5, 'maximum_stock' => 100, 'status' => 'active',
        ]);
    }

    private function formPayload(): array
    {
        return [
            'order_date' => now()->format('Y-m-d'),
            'expected_date' => now()->addDays(7)->format('Y-m-d'),
            'customer_id' => (string) $this->customer->id,
            'warehouse_id' => (string) $this->warehouse->id,
            'notes' => 'Test SO',
            'items' => [
                ['item_id' => (string) $this->item1->id, 'quantity' => 10, 'unit_id' => (string) $this->unit->id, 'unit_price' => '15000', 'notes' => ''],
                ['item_id' => (string) $this->item2->id, 'quantity' => 5, 'unit_id' => (string) $this->unit->id, 'unit_price' => '50000', 'notes' => ''],
            ],
        ];
    }

    private function makeSalesOrder(string $status = 'draft'): SalesOrder
    {
        $order = SalesOrder::create([
            'number' => DocumentNumberService::generate('SO'),
            'order_date' => today(),
            'expected_date' => today()->addDays(7),
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => $status,
            'created_by' => $this->admin->id,
        ]);

        $order->items()->create([
            'item_id' => $this->item1->id,
            'quantity' => 10,
            'fulfilled_quantity' => 0,
            'unit_id' => $this->unit->id,
            'unit_price' => 15000,
        ]);

        $order->items()->create([
            'item_id' => $this->item2->id,
            'quantity' => 5,
            'fulfilled_quantity' => 0,
            'unit_id' => $this->unit->id,
            'unit_price' => 50000,
        ]);

        return $order;
    }

    private function makeStaff(array $slugs): User
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'SO Staff '.uniqid(), 'slug' => 'so-staff-'.uniqid(), 'is_system' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach ($slugs as $slug) {
            $permId = DB::table('permissions')->where('slug', $slug)->value('id');

            if (! $permId) {
                $permId = DB::table('permissions')->insertGetId([
                    'slug' => $slug, 'name' => $slug, 'group' => 'sales_order',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            DB::table('role_permission')->insert(['role_id' => $roleId, 'permission_id' => $permId]);
        }

        $user = User::factory()->create();
        DB::table('user_role')->insert(['user_id' => $user->id, 'role_id' => $roleId]);

        return $user;
    }

    public function test_create_so_via_livewire_then_submit_and_approve(): void
    {
        $payload = $this->formPayload();

        $component = Livewire::test(SalesOrderForm::class)
            ->set('order_date', $payload['order_date'])
            ->set('expected_date', $payload['expected_date'])
            ->set('customer_id', $payload['customer_id'])
            ->set('warehouse_id', $payload['warehouse_id'])
            ->set('notes', $payload['notes'])
            ->set('items', $payload['items'])
            ->call('save');

        $order = SalesOrder::first();
        $this->assertNotNull($order);
        $this->assertEquals('draft', $order->status);
        $this->assertStringStartsWith('SO-', $order->number);
        $this->assertCount(2, $order->items);
        $component->assertRedirect(route('sales-orders.show', $order));

        $this->assertTrue(AuditLog::where('auditable_type', SalesOrder::class)->where('auditable_id', $order->id)->exists());

        Livewire::test(SalesOrderShow::class, ['salesOrder' => $order->id])->call('submit');
        $this->assertEquals('submitted', $order->fresh()->status);

        Livewire::test(SalesOrderShow::class, ['salesOrder' => $order->id])->call('approve');
        $this->assertEquals('approved', $order->fresh()->status);

        $this->assertTrue(AuditLog::where('action', 'SUBMIT')->where('auditable_id', $order->id)->exists());
        $this->assertTrue(AuditLog::where('action', 'APPROVE')->where('auditable_id', $order->id)->exists());
    }

    public function test_reject_flow_with_reason(): void
    {
        $order = $this->makeSalesOrder('submitted');

        Livewire::test(SalesOrderShow::class, ['salesOrder' => $order->id])
            ->set('rejectionReason', 'Stok tidak tersedia')
            ->call('reject');

        $fresh = $order->fresh();
        $this->assertEquals('rejected', $fresh->status);
        $this->assertEquals('Stok tidak tersedia', $fresh->rejection_reason);
        $this->assertFalse($fresh->statusEnum()->isOpen());
    }

    public function test_permission_gate_staff_cannot_approve(): void
    {
        $staff = $this->makeStaff(['sales_order.view', 'sales_order.create', 'sales_order.update', 'sales_order.submit']);
        $this->actingAs($staff);

        $payload = $this->formPayload();

        Livewire::test(SalesOrderForm::class)
            ->set('order_date', $payload['order_date'])
            ->set('expected_date', $payload['expected_date'])
            ->set('customer_id', $payload['customer_id'])
            ->set('warehouse_id', $payload['warehouse_id'])
            ->set('notes', $payload['notes'])
            ->set('items', $payload['items'])
            ->call('save');

        $order = SalesOrder::first();
        $this->assertNotNull($order);
        $this->assertEquals('draft', $order->status);

        Livewire::test(SalesOrderShow::class, ['salesOrder' => $order->id])->call('submit');
        $this->assertEquals('submitted', $order->fresh()->status);

        Livewire::test(SalesOrderShow::class, ['salesOrder' => $order->id])->call('approve');
        $this->assertEquals('submitted', $order->fresh()->status);
    }

    public function test_document_number_service_generates_so(): void
    {
        $num = DocumentNumberService::generate('SO');
        $this->assertStringStartsWith('SO-', $num);
    }
}
