<?php

namespace Tests\Feature\Api;

use App\Models\CustomerReturn;
use App\Models\GoodsReceipt;
use App\Models\Location;
use App\Models\Permission;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression coverage for cross-warehouse IDOR/authorization gaps found in
 * the QA review: a warehouse-scoped user must not be able to list, read or
 * mutate documents that belong to another warehouse.
 */
class ApiWarehouseScopeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $scoped;

    private Warehouse $jkt;

    private Warehouse $bdg;

    private Supplier $supplier;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('email', 'admin@stock.test')->firstOrFail();
        $this->jkt = Warehouse::where('code', 'WH-JKT')->firstOrFail();
        $this->bdg = Warehouse::where('code', 'WH-BDG')->firstOrFail();
        $this->supplier = Supplier::query()->firstOrFail();

        $this->scoped = $this->makeScopedUser($this->jkt->id);
        $this->token = $this->scoped->createToken('qa-scope', ['*'])->plainTextToken;
    }

    private function makeScopedUser(int $warehouseId): User
    {
        $role = Role::create([
            'name' => 'QA Scoped '.uniqid(),
            'slug' => 'qa-scoped-'.uniqid(),
            'is_system' => false,
        ]);
        $role->permissions()->sync(Permission::query()->pluck('id'));

        $user = User::factory()->create([
            'status' => 'active',
            'all_warehouses' => false,
        ]);
        $user->roles()->sync([$role->id]);
        $user->warehouses()->sync([$warehouseId]);

        return $user;
    }

    private function makePurchaseOrder(Warehouse $warehouse, string $number): PurchaseOrder
    {
        return PurchaseOrder::create([
            'number' => $number,
            'order_date' => today(),
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $warehouse->id,
            'status' => 'draft',
            'created_by' => $this->admin->id,
        ]);
    }

    public function test_list_only_contains_accessible_warehouses(): void
    {
        $jkt = $this->makePurchaseOrder($this->jkt, 'PO-QA-JKT');
        $bdg = $this->makePurchaseOrder($this->bdg, 'PO-QA-BDG');

        $numbers = collect($this->withToken($this->token)->getJson('/api/purchase-orders')->assertOk()->json('data'))
            ->pluck('number')
            ->all();

        $this->assertContains($jkt->number, $numbers);
        $this->assertNotContains($bdg->number, $numbers);
    }

    public function test_detail_of_other_warehouse_is_forbidden(): void
    {
        $bdg = $this->makePurchaseOrder($this->bdg, 'PO-QA-BDG-SHOW');

        $this->withToken($this->token)
            ->getJson('/api/purchase-orders/'.$bdg->id)
            ->assertStatus(403);
    }

    public function test_workflow_transition_on_other_warehouse_is_forbidden(): void
    {
        $bdg = $this->makePurchaseOrder($this->bdg, 'PO-QA-BDG-SUBMIT');

        $this->withToken($this->token)
            ->postJson('/api/purchase-orders/'.$bdg->id.'/submit')
            ->assertStatus(403);

        $this->assertSame('draft', $bdg->fresh()->status);
    }

    public function test_goods_receipt_detail_of_other_warehouse_is_forbidden(): void
    {
        $bdg = GoodsReceipt::create([
            'number' => 'GR-QA-BDG',
            'transaction_date' => today(),
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->bdg->id,
            'status' => 'draft',
            'created_by' => $this->admin->id,
        ]);

        $this->withToken($this->token)
            ->getJson('/api/goods-receipts/'.$bdg->id)
            ->assertStatus(403);
    }

    public function test_return_detail_of_other_warehouse_is_not_found(): void
    {
        $location = Location::where('code', 'C01-01')->firstOrFail();

        $return = CustomerReturn::create([
            'number' => 'CRT-QA-BDG',
            'transaction_date' => today(),
            'warehouse_id' => $this->bdg->id,
            'location_id' => $location->id,
            'status' => 'draft',
            'created_by' => $this->admin->id,
        ]);

        $this->withToken($this->token)
            ->getJson('/api/customer-returns/'.$return->id)
            ->assertStatus(404);
    }

    public function test_warehouse_comparison_report_is_scoped(): void
    {
        $rows = collect($this->withToken($this->token)->getJson('/api/reports/warehouse-comparison')->assertOk()->json('data'));

        $this->assertTrue($rows->isNotEmpty());
        $this->assertTrue($rows->every(fn ($row) => $row['name'] === 'Warehouse Jakarta'));
    }
}
