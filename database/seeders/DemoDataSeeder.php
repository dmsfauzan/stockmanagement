<?php

namespace Database\Seeders;

use App\Enums\TransactionType;
use App\Models\GoodsIssue;
use App\Models\GoodsReceipt;
use App\Models\Item;
use App\Models\Location;
use App\Models\Notification;
use App\Models\PurchaseOrder;
use App\Models\StockAdjustment;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\StockOpname;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryService;
use App\Services\Inventory\LedgerService;
use App\Services\Support\AuditLogger;
use App\Services\Support\DocumentNumberService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@stock.test')->first() ?? User::first();
        if (! $admin) {
            return;
        }
        auth()->loginUsingId($admin->id);

        $staff = User::where('email', 'staff@stock.test')->first() ?? $admin;
        $supervisor = User::where('email', 'supervisor@stock.test')->first() ?? $admin;

        $quantities = [
            'BRG-001' => 180,
            'BRG-002' => 95,
            'BRG-003' => 320,
            'BRG-004' => 240,
            'BRG-005' => 60,
            'BRG-006' => 40,
            'BRG-007' => 18,
            'BRG-008' => 150,
            'BRG-009' => 12,
            'BRG-010' => 0,
        ];

        $map = MasterDataSeeder::itemLocationMap();
        $warehouseJkt = Warehouse::where('code', 'WH-JKT')->first();

        $demoCosts = [
            'BRG-001' => 15000,
            'BRG-002' => 50000,
            'BRG-003' => 25000,
            'BRG-004' => 12000,
            'BRG-005' => 18000,
            'BRG-006' => 22000,
            'BRG-007' => 35000,
            'BRG-008' => 9000,
            'BRG-009' => 7500,
            'BRG-010' => 45000,
        ];

        foreach ($demoCosts as $sku => $cost) {
            $item = Item::where('sku', $sku)->first();
            if ($item) {
                $item->forceFill(['cost' => $cost])->save();
            }
        }

        foreach ($quantities as $sku => $qty) {
            $item = Item::where('sku', $sku)->first();
            if (! $item || ! $warehouseJkt) {
                continue;
            }

            $locationCode = $map[$sku] ?? null;
            $location = $locationCode ? Location::where('code', $locationCode)->first() : null;
            if (! $location) {
                continue;
            }

            $exists = StockMovement::where('item_id', $item->id)
                ->where('transaction_type', TransactionType::Opening->value)
                ->exists();

            if ($exists) {
                continue;
            }

            if ($qty === 0) {
                StockBalance::firstOrCreate(
                    ['item_id' => $item->id, 'warehouse_id' => $warehouseJkt->id, 'location_id' => $location->id],
                    ['quantity_on_hand' => 0, 'quantity_reserved' => 0]
                );
                continue;
            }

            try {
                DB::transaction(function () use ($item, $warehouseJkt, $location, $qty): void {
                    LedgerService::record(
                        (int) $item->id,
                        (int) $warehouseJkt->id,
                        (int) $location->id,
                        TransactionType::Opening,
                        'opening',
                        0,
                        (int) $qty,
                        0,
                        null,
                        null,
                        'Opening balance for '.$item->sku
                    );
                });
            } catch (\Throwable $e) {
            }
        }

        $supplierJkt = \App\Models\Supplier::where('code', 'SUP001')->first();
        $supplierMedia = \App\Models\Supplier::where('code', 'SUP002')->first();
        $supplierPackaging = \App\Models\Supplier::where('code', 'SUP003')->first();
        $customerRetail = \App\Models\Customer::where('code', 'CUST001')->first();

        $supplierAdvanced = [
            'SUP001' => ['contact_person' => 'Budi Santoso', 'phone' => '021-5550101', 'email' => 'sales@sumberelektronik.test', 'region' => 'Jakarta', 'lead_time_days' => 7, 'payment_terms' => 'NET 30'],
            'SUP002' => ['contact_person' => 'Rina Wijaya', 'phone' => '022-5550202', 'email' => 'order@mediacomputer.test', 'region' => 'Bandung', 'lead_time_days' => 5, 'payment_terms' => 'NET 14'],
            'SUP003' => ['contact_person' => 'Agus Pratama', 'phone' => '031-5550303', 'email' => 'cs@kemasannusantara.test', 'region' => 'Surabaya', 'lead_time_days' => 10, 'payment_terms' => 'COD'],
        ];

        foreach ($supplierAdvanced as $code => $fields) {
            DB::table('suppliers')->where('code', $code)->update($fields);
        }

        $priceSeed = [
            ['supplier' => 'SUP001', 'sku' => 'BRG-001', 'price' => 15000, 'lead' => 7, 'notes' => 'Harga kontrak 2026'],
            ['supplier' => 'SUP001', 'sku' => 'BRG-007', 'price' => 1800000, 'lead' => 9, 'notes' => 'Include garansi 1 tahun'],
            ['supplier' => 'SUP001', 'sku' => 'BRG-008', 'price' => 9000, 'lead' => 6, 'notes' => null],
            ['supplier' => 'SUP002', 'sku' => 'BRG-001', 'price' => 16500, 'lead' => 5, 'notes' => 'Harga alternatif lebih tinggi (demo variance)'],
            ['supplier' => 'SUP002', 'sku' => 'BRG-002', 'price' => 50000, 'lead' => 5, 'notes' => null],
            ['supplier' => 'SUP003', 'sku' => 'BRG-009', 'price' => 7500, 'lead' => 10, 'notes' => null],
        ];

        foreach ($priceSeed as $row) {
            $sup = \App\Models\Supplier::where('code', $row['supplier'])->first();
            $itm = Item::where('sku', $row['sku'])->first();

            if (! $sup || ! $itm) {
                continue;
            }

            DB::table('supplier_item_prices')->updateOrInsert(
                ['supplier_id' => $sup->id, 'item_id' => $itm->id],
                [
                    'price' => $row['price'],
                    'lead_time_days' => $row['lead'],
                    'notes' => $row['notes'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        if ($supplierJkt && $warehouseJkt && ! GoodsReceipt::where('notes', 'Demo GR POSTED')->exists()) {
            $number = DocumentNumberService::generate('GR');
            $receipt = GoodsReceipt::create([
                'number' => $number,
                'transaction_date' => Carbon::now()->subDays(3)->toDateString(),
                'supplier_id' => $supplierJkt->id,
                'warehouse_id' => $warehouseJkt->id,
                'status' => 'approved',
                'notes' => 'Demo GR POSTED',
                'created_by' => $staff->id,
                'submitted_by' => $staff->id,
                'submitted_at' => Carbon::now()->subDays(3),
                'approved_by' => $supervisor->id,
                'approved_at' => Carbon::now()->subDays(2),
            ]);

            $itemsForReceipt = [
                ['sku' => 'BRG-003', 'qty' => 25, 'loc' => 'A01-03', 'batch' => 'BATCH-DEMO-1', 'expiry' => Carbon::now()->addDays(5)->toDateString()],
                ['sku' => 'BRG-004', 'qty' => 15, 'loc' => 'A02-01', 'batch' => null, 'expiry' => null],
            ];

            foreach ($itemsForReceipt as $row) {
                $item = Item::where('sku', $row['sku'])->first();
                $loc = Location::where('code', $row['loc'])->first();
                if (! $item || ! $loc) {
                    continue;
                }
                $receipt->receiptItems()->create([
                    'item_id' => $item->id,
                    'quantity' => $row['qty'],
                    'unit_cost' => $item->cost,
                    'unit_id' => $item->unit_id,
                    'location_id' => $loc->id,
                    'batch_number' => $row['batch'] ?? null,
                    'expiry_date' => $row['expiry'] ?? null,
                ]);
            }

            try {
                InventoryService::postGoodsReceipt($receipt);
            } catch (\Throwable $e) {
            }
        }

        if ($supplierMedia && $warehouseJkt && ! GoodsReceipt::where('notes', 'Demo GR DRAFT')->exists()) {
            $number = DocumentNumberService::generate('GR');
            $receipt = GoodsReceipt::create([
                'number' => $number,
                'transaction_date' => Carbon::now()->subDays(1)->toDateString(),
                'supplier_id' => $supplierMedia->id,
                'warehouse_id' => $warehouseJkt->id,
                'status' => 'draft',
                'notes' => 'Demo GR DRAFT',
                'created_by' => $staff->id,
            ]);

            $itemsForDraft = [
                ['sku' => 'BRG-005', 'qty' => 10, 'loc' => 'A02-02'],
                ['sku' => 'BRG-006', 'qty' => 8, 'loc' => 'A02-03'],
            ];

            foreach ($itemsForDraft as $row) {
                $item = Item::where('sku', $row['sku'])->first();
                $loc = Location::where('code', $row['loc'])->first();
                if (! $item || ! $loc) {
                    continue;
                }
                $receipt->receiptItems()->create([
                    'item_id' => $item->id,
                    'quantity' => $row['qty'],
                    'unit_cost' => $item->cost ?? 0,
                    'unit_id' => $item->unit_id,
                    'location_id' => $loc->id,
                ]);
            }
        }

        if ($customerRetail && $warehouseJkt && ! GoodsIssue::where('notes', 'Demo GI POSTED')->exists()) {
            $number = DocumentNumberService::generate('GI');
            $issue = GoodsIssue::create([
                'number' => $number,
                'transaction_date' => Carbon::now()->subDays(2)->toDateString(),
                'customer_id' => $customerRetail->id,
                'destination' => 'Demo destination posted',
                'warehouse_id' => $warehouseJkt->id,
                'status' => 'approved',
                'notes' => 'Demo GI POSTED',
                'created_by' => $staff->id,
                'submitted_by' => $staff->id,
                'submitted_at' => Carbon::now()->subDays(2),
                'approved_by' => $supervisor->id,
                'approved_at' => Carbon::now()->subDays(1),
            ]);

            $itemsForIssue = [
                ['sku' => 'BRG-001', 'qty' => 10, 'loc' => 'A01-01'],
                ['sku' => 'BRG-002', 'qty' => 5, 'loc' => 'A01-02'],
            ];

            foreach ($itemsForIssue as $row) {
                $item = Item::where('sku', $row['sku'])->first();
                $loc = Location::where('code', $row['loc'])->first();
                if (! $item || ! $loc) {
                    continue;
                }
                $issue->issueItems()->create([
                    'item_id' => $item->id,
                    'quantity' => $row['qty'],
                    'unit_id' => $item->unit_id,
                    'location_id' => $loc->id,
                ]);
            }

            try {
                InventoryService::postGoodsIssue($issue);
            } catch (\Throwable $e) {
            }
        }

        if ($warehouseJkt && ! GoodsIssue::where('notes', 'Demo GI SUBMITTED')->exists()) {
            $number = DocumentNumberService::generate('GI');
            $issue = GoodsIssue::create([
                'number' => $number,
                'transaction_date' => Carbon::now()->subDays(1)->toDateString(),
                'customer_id' => $customerRetail?->id,
                'destination' => 'Demo destination submitted',
                'warehouse_id' => $warehouseJkt->id,
                'status' => 'submitted',
                'notes' => 'Demo GI SUBMITTED',
                'created_by' => $staff->id,
                'submitted_by' => $staff->id,
                'submitted_at' => Carbon::now()->subDays(1),
            ]);

            $itemsForSubmitted = [
                ['sku' => 'BRG-003', 'qty' => 15, 'loc' => 'A01-03'],
                ['sku' => 'BRG-004', 'qty' => 8, 'loc' => 'A02-01'],
            ];

            foreach ($itemsForSubmitted as $row) {
                $item = Item::where('sku', $row['sku'])->first();
                $loc = Location::where('code', $row['loc'])->first();
                if (! $item || ! $loc) {
                    continue;
                }
                $issue->issueItems()->create([
                    'item_id' => $item->id,
                    'quantity' => $row['qty'],
                    'unit_id' => $item->unit_id,
                    'location_id' => $loc->id,
                ]);
            }
        }

        try {
            AuditLogger::log('CREATE', 'items', Item::first(), null, ['demo' => true]);
        } catch (\Throwable $e) {
        }
        try {
            $gr = GoodsReceipt::where('notes', 'Demo GR POSTED')->first();
            if ($gr) {
                AuditLogger::log('POST', 'goods_receipt', $gr);
            }
        } catch (\Throwable $e) {
        }
        try {
            $gi = GoodsIssue::where('notes', 'Demo GI POSTED')->first();
            if ($gi) {
                AuditLogger::log('POST', 'goods_issue', $gi);
            }
        } catch (\Throwable $e) {
        }
        try {
            AuditLogger::log('VIEW', 'stock', null);
        } catch (\Throwable $e) {
        }

        $whJkt = $warehouseJkt ?? Warehouse::where('code', 'WH-JKT')->first();
        $whBdg = Warehouse::where('code', 'WH-BDG')->first();
        $defaultLoc = Location::where('code', 'A01-02')->first() ?? Location::first();
        $locBdg = Location::where('code', 'C01-01')->first() ?? $defaultLoc;
        $adjItem = Item::where('sku', 'BRG-009')->first() ?? Item::first();
        $adjQty = (int) (StockBalance::where('item_id', $adjItem?->id)->where('warehouse_id', $whJkt?->id)->where('location_id', $defaultLoc?->id)->value('quantity_on_hand') ?? 12);

        if ($whJkt && $defaultLoc && $adjItem && ! StockAdjustment::where('number', 'ADJ-DEMO-001')->exists()) {
            $adjDraft = StockAdjustment::create([
                'number' => 'ADJ-DEMO-001', 'transaction_date' => Carbon::now()->subDays(1)->toDateString(),
                'warehouse_id' => $whJkt->id, 'location_id' => $defaultLoc->id,
                'reason' => 'Stock Count Error', 'status' => 'draft', 'notes' => 'Demo adjustment — draft',
                'created_by' => $staff->id,
            ]);
            $adjDraft->items()->create(['item_id' => $adjItem->id, 'system_quantity' => $adjQty, 'actual_quantity' => max(0, $adjQty - 2), 'difference' => -2, 'notes' => $adjItem->name.' kurang 2']);
        }
        if ($whJkt && $defaultLoc && $adjItem && ! StockAdjustment::where('number', 'ADJ-DEMO-002')->exists()) {
            $adjPosted = StockAdjustment::create([
                'number' => 'ADJ-DEMO-002', 'transaction_date' => Carbon::now()->subDays(2)->toDateString(),
                'warehouse_id' => $whJkt->id, 'location_id' => $defaultLoc->id,
                'reason' => 'Stock Count Error', 'status' => 'approved', 'notes' => 'Demo posted',
                'created_by' => $staff->id, 'submitted_by' => $staff->id, 'submitted_at' => Carbon::now()->subDays(2),
                'approved_by' => $supervisor->id, 'approved_at' => Carbon::now()->subDays(1),
            ]);
            $adjPosted->items()->create(['item_id' => $adjItem->id, 'system_quantity' => $adjQty, 'actual_quantity' => max(0, $adjQty - 2), 'difference' => -2, 'notes' => $adjItem->name.' kurang 2 (demo)']);
            try { InventoryService::postStockAdjustment($adjPosted); } catch (\Throwable $e) {}
        }

        if ($whJkt && $defaultLoc && ! StockOpname::where('number', 'OPN-DEMO-001')->exists()) {
            $opn = StockOpname::create([
                'number' => 'OPN-DEMO-001', 'opname_date' => Carbon::now()->subDays(1)->toDateString(),
                'warehouse_id' => $whJkt->id, 'location_id' => $defaultLoc->id,
                'status' => 'submitted', 'notes' => 'Demo opname — submitted',
                'created_by' => $staff->id, 'submitted_by' => $staff->id, 'submitted_at' => Carbon::now()->subDays(1),
            ]);
            $opnItem = Item::where('sku', 'BRG-002')->first() ?? Item::first();
            $sysForOpn = (int) (StockBalance::where('item_id', $opnItem?->id)->where('warehouse_id', $whJkt->id)->where('location_id', $defaultLoc->id)->value('quantity_on_hand') ?? 0);
            $opn->items()->create(['item_id' => $opnItem->id, 'system_quantity' => $sysForOpn, 'physical_quantity' => max(0, $sysForOpn - 1), 'difference' => -1, 'reason' => 'Counted less 1']);
        }

        if ($whJkt && $whBdg && $defaultLoc && $locBdg && ! StockTransfer::where('number', 'TR-DEMO-001')->exists()) {
            $trItem = Item::where('sku', 'BRG-003')->first() ?? Item::first();
            $tr = StockTransfer::create([
                'number' => 'TR-DEMO-001', 'transfer_date' => Carbon::now()->subDays(1)->toDateString(),
                'from_warehouse_id' => $whJkt->id, 'from_location_id' => $defaultLoc->id,
                'to_warehouse_id' => $whBdg->id, 'to_location_id' => $locBdg->id,
                'status' => 'requested', 'notes' => 'Demo transfer — requested',
                'created_by' => $staff->id, 'requested_by' => $staff->id, 'requested_at' => Carbon::now()->subDays(1),
            ]);
            $tr->items()->create(['item_id' => $trItem->id, 'quantity' => 5, 'unit_id' => $trItem->unit_id, 'notes' => $trItem->name.' 5 pcs']);
        }

        $demoUserIds = collect([$admin->id, $supervisor->id, $staff->id])->unique()->values();

        if (Notification::whereIn('user_id', $demoUserIds)->count() === 0) {
            $lowItem = Item::where('sku', 'BRG-009')->first() ?? Item::first();

            $samples = [
                ['user_id' => $supervisor->id, 'type' => 'stock.low', 'title' => 'Low stock', 'message' => ($lowItem?->sku ?? 'BRG-009').' '.($lowItem?->name ?? 'Packing Tape').' tinggal 12 (min 25) di Warehouse Jakarta'],
                ['user_id' => $admin->id, 'type' => 'stock.low', 'title' => 'Low stock', 'message' => ($lowItem?->sku ?? 'BRG-009').' '.($lowItem?->name ?? 'Packing Tape').' tinggal 12 (min 25) di Warehouse Jakarta'],
                ['user_id' => $supervisor->id, 'type' => 'approval.request', 'title' => 'Approval Barang Masuk', 'message' => 'GR-DEMO-0001 menunggu persetujuan'],
                ['user_id' => $admin->id, 'type' => 'approval.request', 'title' => 'Approval Barang Keluar', 'message' => 'GI-DEMO-0001 menunggu persetujuan'],
            ];

            foreach ($samples as $sample) {
                try {
                    Notification::create($sample);
                } catch (\Throwable $e) {
                }
            }
        }

        if (! PurchaseOrder::where('number', 'PO-DEMO-001')->exists()) {
            $poWh = $warehouseJkt ?? Warehouse::where('code', 'WH-JKT')->first();
            $poSup = \App\Models\Supplier::where('code', 'SUP001')->first();
            $poItem1 = Item::where('sku', 'BRG-001')->first();
            $poItem2 = Item::where('sku', 'BRG-002')->first();

            if ($poWh && $poSup && $poItem1 && $poItem2) {
                try {
                    $po = PurchaseOrder::create([
                        'number' => 'PO-DEMO-001',
                        'order_date' => Carbon::now()->subDays(1)->toDateString(),
                        'expected_date' => Carbon::now()->addDays(7)->toDateString(),
                        'supplier_id' => $poSup->id,
                        'warehouse_id' => $poWh->id,
                        'status' => 'approved',
                        'notes' => 'Demo PO — approved',
                        'created_by' => $staff->id,
                        'submitted_by' => $staff->id,
                        'submitted_at' => Carbon::now()->subDays(1),
                        'approved_by' => $supervisor->id,
                        'approved_at' => Carbon::now()->subHours(12),
                    ]);

                    $po->items()->createMany([
                        ['item_id' => $poItem1->id, 'quantity' => 10, 'received_quantity' => 0, 'unit_id' => $poItem1->unit_id, 'unit_price' => 15000],
                        ['item_id' => $poItem2->id, 'quantity' => 5, 'received_quantity' => 0, 'unit_id' => $poItem2->unit_id, 'unit_price' => 50000],
                    ]);
                } catch (\Throwable $e) {
                }
            }
        }

        try {
            \App\Models\Setting::updateOrCreate(['key' => 'expiry.warn_days'], ['value' => '30', 'group' => 'expiry']);
            \App\Models\Setting::updateOrCreate(['key' => 'expiry.critical_days'], ['value' => '7', 'group' => 'expiry']);
        } catch (\Throwable $e) {
        }

        auth()->logout();
    }
}
