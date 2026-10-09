<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'dashboard.view' => 'dashboard',
            'items.view' => 'items',
            'items.create' => 'items',
            'items.update' => 'items',
            'items.delete' => 'items',
            'warehouse.view' => 'warehouse',
            'warehouse.create' => 'warehouse',
            'warehouse.update' => 'warehouse',
            'warehouse.delete' => 'warehouse',
            'location.view' => 'location',
            'location.create' => 'location',
            'location.update' => 'location',
            'location.delete' => 'location',
            'goods_receipt.view' => 'goods_receipt',
            'goods_receipt.create' => 'goods_receipt',
            'goods_receipt.update' => 'goods_receipt',
            'goods_receipt.submit' => 'goods_receipt',
            'goods_receipt.approve' => 'goods_receipt',
            'goods_receipt.post' => 'goods_receipt',
            'goods_issue.view' => 'goods_issue',
            'goods_issue.create' => 'goods_issue',
            'goods_issue.update' => 'goods_issue',
            'goods_issue.submit' => 'goods_issue',
            'goods_issue.approve' => 'goods_issue',
            'goods_issue.post' => 'goods_issue',
            'stock.view' => 'stock',
            'stock.movement' => 'stock',
            'stock.adjustment' => 'stock',
            'stock.adjustment.approve' => 'stock',
            'stock.quarantine' => 'stock',
            'picking.view' => 'picking',
            'picking.create' => 'picking',
            'picking.pick' => 'picking',
            'picking.pack' => 'picking',
            'assembly.view' => 'assembly',
            'assembly.create' => 'assembly',
            'assembly.update' => 'assembly',
            'assembly.post' => 'assembly',
            'requisition.view' => 'requisition',
            'requisition.create' => 'requisition',
            'requisition.update' => 'requisition',
            'requisition.submit' => 'requisition',
            'requisition.approve' => 'requisition',
            'requisition.convert' => 'requisition',
            'stock_opname.view' => 'stock_opname',
            'stock_opname.create' => 'stock_opname',
            'stock_opname.submit' => 'stock_opname',
            'stock_opname.approve' => 'stock_opname',
            'transfer.view' => 'transfer',
            'transfer.create' => 'transfer',
            'transfer.approve' => 'transfer',
            'transfer.receive' => 'transfer',
            'purchase_order.view' => 'purchase_order',
            'purchase_order.create' => 'purchase_order',
            'purchase_order.update' => 'purchase_order',
            'purchase_order.submit' => 'purchase_order',
            'purchase_order.approve' => 'purchase_order',
            'purchase_order.receive' => 'purchase_order',
            'purchase_order.close' => 'purchase_order',
            'sales_order.view' => 'sales_order',
            'sales_order.create' => 'sales_order',
            'sales_order.update' => 'sales_order',
            'sales_order.submit' => 'sales_order',
            'sales_order.approve' => 'sales_order',
            'sales_order.fulfill' => 'sales_order',
            'sales_order.close' => 'sales_order',
            'customer_return.view' => 'customer_return',
            'customer_return.create' => 'customer_return',
            'customer_return.update' => 'customer_return',
            'customer_return.submit' => 'customer_return',
            'customer_return.approve' => 'customer_return',
            'customer_return.post' => 'customer_return',
            'supplier_return.view' => 'supplier_return',
            'supplier_return.create' => 'supplier_return',
            'supplier_return.update' => 'supplier_return',
            'supplier_return.submit' => 'supplier_return',
            'supplier_return.approve' => 'supplier_return',
            'supplier_return.post' => 'supplier_return',
            'reports.view' => 'reports',
            'reports.export' => 'reports',
            'users.manage' => 'users',
            'roles.manage' => 'roles',
            'settings.manage' => 'settings',
            'audit_logs.view' => 'audit_logs',
            'security.manage' => 'security',
        ];

        $permissionIds = [];
        foreach ($permissions as $slug => $group) {
            $name = Str::title(str_replace(['.', '_'], ' ', $slug));
            $perm = Permission::updateOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'group' => $group]
            );
            $permissionIds[$slug] = $perm->id;
        }

        $roles = [
            'admin' => 'Administrator',
            'warehouse_staff' => 'Warehouse Staff',
            'supervisor' => 'Supervisor',
            'manager' => 'Manager',
        ];

        $roleModels = [];
        foreach ($roles as $slug => $name) {
            $roleModels[$slug] = Role::updateOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'is_system' => true]
            );
        }

        $this->syncPermissions($roleModels['admin']->id, array_values($permissionIds));

        $warehouseStaffPerms = [
            'dashboard.view', 'items.view', 'stock.view', 'stock.movement',
            'goods_receipt.view', 'goods_receipt.create', 'goods_receipt.update', 'goods_receipt.submit',
            'goods_issue.view', 'goods_issue.create', 'goods_issue.update', 'goods_issue.submit',
            'location.view', 'warehouse.view',
            'stock.adjustment', 'stock_opname.view', 'stock_opname.create', 'stock_opname.submit',
            'picking.view', 'picking.create', 'picking.pick',
            'assembly.view', 'assembly.create',
            'requisition.view', 'requisition.create', 'requisition.update', 'requisition.submit',
            'transfer.view', 'transfer.create',
            'purchase_order.view', 'purchase_order.create', 'purchase_order.update', 'purchase_order.submit',
            'sales_order.view', 'sales_order.create', 'sales_order.update', 'sales_order.submit',
            'customer_return.view', 'customer_return.create', 'customer_return.update', 'customer_return.submit',
            'supplier_return.view', 'supplier_return.create', 'supplier_return.update', 'supplier_return.submit',
        ];
        $this->syncPermissions(
            $roleModels['warehouse_staff']->id,
            collect($warehouseStaffPerms)->map(fn ($s) => $permissionIds[$s])->values()->all()
        );

        $supervisorPerms = [
            'dashboard.view', 'stock.view', 'stock.movement',
            'goods_receipt.view', 'goods_receipt.approve', 'goods_receipt.post',
            'goods_issue.view', 'goods_issue.approve', 'goods_issue.post',
            'reports.view', 'reports.export',
            'stock.adjustment.approve', 'stock.quarantine',
            'picking.view', 'picking.pick', 'picking.pack',
            'assembly.view', 'assembly.post',
            'requisition.view', 'requisition.approve', 'requisition.convert',
            'stock_opname.view', 'stock_opname.approve',
            'transfer.view', 'transfer.approve', 'transfer.receive',
            'purchase_order.view', 'purchase_order.approve', 'purchase_order.receive', 'purchase_order.close',
            'sales_order.view', 'sales_order.approve', 'sales_order.fulfill', 'sales_order.close',
            'customer_return.view', 'customer_return.approve', 'customer_return.post',
            'supplier_return.view', 'supplier_return.approve', 'supplier_return.post',
            'items.view', 'warehouse.view', 'location.view',
        ];
        $this->syncPermissions(
            $roleModels['supervisor']->id,
            collect($supervisorPerms)->map(fn ($s) => $permissionIds[$s])->values()->all()
        );

        $managerPerms = [
            'dashboard.view', 'stock.view', 'items.view',
            'reports.view', 'reports.export',
            'goods_receipt.view', 'goods_issue.view',
            'warehouse.view', 'location.view',
            'transfer.view',
            'purchase_order.view',
            'sales_order.view',
        ];
        $this->syncPermissions(
            $roleModels['manager']->id,
            collect($managerPerms)->map(fn ($s) => $permissionIds[$s])->values()->all()
        );
    }

    private function syncPermissions(int $roleId, array $permissionIds): void
    {
        DB::table('role_permission')->where('role_id', $roleId)->delete();

        $rows = collect($permissionIds)
            ->map(fn ($id) => ['role_id' => $roleId, 'permission_id' => $id])
            ->all();

        if ($rows !== []) {
            DB::table('role_permission')->insert($rows);
        }
    }
}
