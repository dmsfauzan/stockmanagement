<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Workflow\DocumentWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApprovalFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private User $supervisor;

    private User $manager;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->staff = User::where('email', 'staff@stock.test')->firstOrFail();
        $this->supervisor = User::where('email', 'supervisor@stock.test')->firstOrFail();
        $this->manager = User::where('email', 'manager@stock.test')->firstOrFail();
        $this->admin = User::where('email', 'admin@stock.test')->firstOrFail();
    }

    private function configureFlows(): void
    {
        config()->set('approval.enabled', true);
        config()->set('approval.maker_checker', true);
        config()->set('approval.flows', [
            'purchase_order' => [
                ['level' => 1, 'role' => 'supervisor', 'min_total' => 0],
                ['level' => 2, 'role' => 'manager', 'min_total' => 10_000_000],
                ['level' => 3, 'role' => 'admin', 'min_total' => 100_000_000],
            ],
            'goods_receipt' => [
                ['level' => 1, 'role' => 'supervisor', 'min_total' => 0],
                ['level' => 2, 'role' => 'manager', 'min_total' => 50_000_000],
            ],
            'stock_adjustment' => [
                ['level' => 1, 'role' => 'supervisor', 'min_total' => 0],
                ['level' => 2, 'role' => 'manager', 'min_total' => 10_000_000],
            ],
            'sales_order' => [
                ['level' => 1, 'role' => 'supervisor', 'min_total' => 0],
                ['level' => 2, 'role' => 'manager', 'min_total' => 10_000_000],
            ],
            'stock_transfer' => [
                ['level' => 1, 'role' => 'supervisor', 'min_total' => 0],
            ],
        ]);
        Setting::query()->where('key', 'like', 'approval.%')->delete();
    }

    private function makePo(float $total, ?int $creatorId = null): PurchaseOrder
    {
        $po = PurchaseOrder::create([
            'number' => 'PO-'.uniqid(),
            'order_date' => today(),
            'expected_date' => today()->addDays(7),
            'supplier_id' => Supplier::query()->firstOrFail()->id,
            'warehouse_id' => Warehouse::query()->firstOrFail()->id,
            'status' => 'draft',
            'created_by' => $creatorId ?? $this->staff->id,
        ]);

        $item = Item::query()->firstOrFail();

        $unitPrice = max(1, (int) ($total / 10));

        $po->items()->create([
            'item_id' => $item->id,
            'quantity' => 10,
            'received_quantity' => 0,
            'unit_id' => $item->unit_id,
            'unit_price' => $unitPrice,
        ]);

        return $po->fresh('items');
    }

    public function test_single_level_approval_for_low_value(): void
    {
        $this->configureFlows();

        $po = $this->makePo(5_000);
        $this->actingAs($this->staff);
        DocumentWorkflow::submitPo($po->id);
        $po->refresh();
        $this->assertSame(1, (int) $po->required_levels);

        $this->actingAs($this->supervisor);
        DocumentWorkflow::approvePo($po->id);
        $this->assertSame('approved', $po->fresh()->status);
        $this->assertDatabaseHas('approval_histories', [
            'approvable_type' => PurchaseOrder::class,
            'approvable_id' => $po->id,
            'level' => 1,
            'action' => 'approved',
        ]);
    }

    public function test_multi_level_approval_requires_all_levels(): void
    {
        $this->configureFlows();

        $po = $this->makePo(20_000_000);
        $this->actingAs($this->staff);
        DocumentWorkflow::submitPo($po->id);
        $po->refresh();
        $this->assertSame(2, (int) $po->required_levels);

        $this->actingAs($this->supervisor);
        DocumentWorkflow::approvePo($po->id);
        $po->refresh();
        $this->assertSame('submitted', $po->status);
        $this->assertSame(1, (int) $po->current_level);

        $this->actingAs($this->manager);
        DocumentWorkflow::approvePo($po->id);
        $this->assertSame('approved', $po->fresh()->status);
        $this->assertSame(2, (int) $po->fresh()->current_level);
    }

    public function test_wrong_role_cannot_approve(): void
    {
        $this->configureFlows();

        $po = $this->makePo(20_000_000);
        $this->actingAs($this->staff);
        DocumentWorkflow::submitPo($po->id);

        $this->actingAs($this->staff);
        try {
            DocumentWorkflow::approvePo($po->id);
            $this->fail('Expected maker-checker.');
        } catch (\Throwable $e) {
            $this->assertStringContainsString('role', $e->getMessage());
        }
    }

    public function test_maker_checker_blocks_author(): void
    {
        $this->configureFlows();

        $po = $this->makePo(5_000, $this->supervisor->id);
        $this->actingAs($this->supervisor);
        DocumentWorkflow::submitPo($po->id);

        $this->actingAs($this->supervisor);
        $this->expectException(\RuntimeException::class);
        DocumentWorkflow::approvePo($po->id);
    }

    public function test_maker_checker_blocks_same_approver_second_level(): void
    {
        $this->configureFlows();

        $po = $this->makePo(20_000_000);
        $this->actingAs($this->staff);
        DocumentWorkflow::submitPo($po->id);

        // Give supervisor also manager role so they could attempt both levels.
        $managerRole = Role::where('slug', 'manager')->firstOrFail();
        $this->supervisor->roles()->syncWithoutDetaching([$managerRole->id]);

        $this->actingAs($this->supervisor);
        DocumentWorkflow::approvePo($po->id);
        $this->assertSame('submitted', $po->fresh()->status);

        $this->expectException(\RuntimeException::class);
        DocumentWorkflow::approvePo($po->id);
    }

    public function test_three_level_approval(): void
    {
        $this->configureFlows();

        $po = $this->makePo(150_000_000);
        $this->actingAs($this->staff);
        DocumentWorkflow::submitPo($po->id);
        $this->assertSame(3, (int) $po->fresh()->required_levels);

        $this->actingAs($this->supervisor);
        DocumentWorkflow::approvePo($po->id);
        $this->assertSame('submitted', $po->fresh()->status);

        $this->actingAs($this->manager);
        DocumentWorkflow::approvePo($po->id);
        $this->assertSame('submitted', $po->fresh()->status);

        $this->actingAs($this->admin);
        DocumentWorkflow::approvePo($po->id);
        $this->assertSame('approved', $po->fresh()->status);
    }

    public function test_reject_records_history(): void
    {
        $this->configureFlows();

        $po = $this->makePo(5_000);
        $this->actingAs($this->staff);
        DocumentWorkflow::submitPo($po->id);

        $this->actingAs($this->supervisor);
        DocumentWorkflow::rejectPo($po->id, 'Tidak sesuai');

        $this->assertSame('rejected', $po->fresh()->status);
        $this->assertDatabaseHas('approval_histories', [
            'approvable_type' => PurchaseOrder::class,
            'approvable_id' => $po->id,
            'action' => 'rejected',
        ]);
    }

    public function test_api_approve_respects_levels(): void
    {
        $this->configureFlows();

        $po = $this->makePo(20_000_000);
        $this->actingAs($this->staff);
        DocumentWorkflow::submitPo($po->id);

        app('auth')->forgetGuards();

        $tokenSup = $this->supervisor->createToken('sup', ['*'])->plainTextToken;
        $tokenAdmin = $this->admin->createToken('adm', ['*'])->plainTextToken;

        $this->withToken($tokenSup)->postJson("/api/purchase-orders/{$po->id}/approve")->assertOk();
        $this->assertSame('submitted', $po->fresh()->status);

        app('auth')->forgetGuards();

        $this->withToken($tokenAdmin)->postJson("/api/purchase-orders/{$po->id}/approve")->assertOk();
        $this->assertSame('approved', $po->fresh()->status);
    }

    public function test_disabled_multi_level_behaves_as_single(): void
    {
        config()->set('approval.enabled', false);
        Setting::where('key', 'approval.enabled')->delete();

        $po = $this->makePo(100_000_000);
        $this->actingAs($this->staff);
        DocumentWorkflow::submitPo($po->id);
        $this->assertSame(1, (int) $po->fresh()->required_levels);

        $this->actingAs($this->supervisor);
        DocumentWorkflow::approvePo($po->id);
        $this->assertSame('approved', $po->fresh()->status);
    }
}
