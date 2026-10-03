<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Item;
use App\Models\Location;
use App\Models\Rack;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Models\Zone;
use Illuminate\Database\Seeder;

class MasterDataSeeder extends Seeder
{
    public static function itemLocationMap(): array
    {
        return [
            'BRG-001' => 'A01-01',
            'BRG-002' => 'A01-02',
            'BRG-003' => 'A01-03',
            'BRG-004' => 'A02-01',
            'BRG-005' => 'A02-02',
            'BRG-006' => 'A02-03',
            'BRG-007' => 'B01-01',
            'BRG-008' => 'B01-02',
            'BRG-009' => 'B01-03',
            'BRG-010' => 'B01-01',
        ];
    }

    public function run(): void
    {
        $categories = [
            'ELEC' => 'Electronics',
            'COMP' => 'Computer Accessories',
            'OFFICE' => 'Office Supplies',
            'PACK' => 'Packaging',
            'SPARE' => 'Spare Parts',
            'CONS' => 'Consumables',
        ];

        foreach ($categories as $code => $name) {
            Category::updateOrCreate(['code' => $code], ['name' => $name, 'status' => 'active']);
        }

        $units = [
            'PCS' => 'Pieces',
            'BOX' => 'Box',
            'ROLL' => 'Roll',
            'SET' => 'Set',
            'UNIT' => 'Unit',
            'PACK' => 'Pack',
        ];

        foreach ($units as $code => $name) {
            Unit::updateOrCreate(['code' => $code], ['name' => $name]);
        }

        $suppliers = [
            'SUP001' => 'PT Sumber Elektronik',
            'SUP002' => 'CV Media Computer',
            'SUP003' => 'PT Kemasan Nusantara',
        ];

        foreach ($suppliers as $code => $name) {
            Supplier::updateOrCreate(['code' => $code], ['name' => $name, 'status' => 'active']);
        }

        $customers = [
            ['code' => 'CUST001', 'name' => 'PT Retail Sejahtera', 'type' => 'customer'],
            ['code' => 'DEPT001', 'name' => 'IT Department', 'type' => 'department'],
            ['code' => 'DEPT002', 'name' => 'General Affairs', 'type' => 'department'],
            ['code' => 'CUST002', 'name' => 'Toko Online Jaya', 'type' => 'customer'],
        ];

        foreach ($customers as $customer) {
            Customer::updateOrCreate(['code' => $customer['code']], $customer + ['status' => 'active']);
        }

        $jkt = Warehouse::updateOrCreate(['code' => 'WH-JKT'], ['name' => 'Warehouse Jakarta', 'status' => 'active']);
        $bdg = Warehouse::updateOrCreate(['code' => 'WH-BDG'], ['name' => 'Warehouse Bandung', 'status' => 'active']);

        $zones = [
            [$jkt, 'Z-A', 'Zone A', [['A01', 'Rack A01'], ['A02', 'Rack A02']]],
            [$jkt, 'Z-B', 'Zone B', [['B01', 'Rack B01']]],
            [$bdg, 'Z-C', 'Zone C', [['C01', 'Rack C01']]],
        ];

        foreach ($zones as [$warehouse, $zoneCode, $zoneName, $racks]) {
            $zone = Zone::updateOrCreate(
                ['warehouse_id' => $warehouse->id, 'code' => $zoneCode],
                ['name' => $zoneName]
            );

            foreach ($racks as [$rackCode, $rackName]) {
                $rack = Rack::updateOrCreate(
                    ['zone_id' => $zone->id, 'code' => $rackCode],
                    ['name' => $rackName]
                );

                for ($i = 1; $i <= 3; $i++) {
                    $locationCode = sprintf('%s-%02d', $rackCode, $i);
                    Location::updateOrCreate(
                        ['code' => $locationCode],
                        ['rack_id' => $rack->id, 'name' => 'Bin '.$locationCode]
                    );
                }
            }
        }

        $items = [
            ['sku' => 'BRG-001', 'name' => 'Wireless Mouse', 'category' => 'ELEC', 'unit' => 'PCS', 'brand' => 'Logitech', 'min' => 30, 'max' => 300, 'supplier' => 'SUP001'],
            ['sku' => 'BRG-002', 'name' => 'Mechanical Keyboard', 'category' => 'COMP', 'unit' => 'PCS', 'brand' => 'Logitech', 'min' => 20, 'max' => 200, 'supplier' => 'SUP002'],
            ['sku' => 'BRG-003', 'name' => 'USB Cable', 'category' => 'COMP', 'unit' => 'PCS', 'brand' => 'Vivan', 'min' => 50, 'max' => 500, 'supplier' => 'SUP002'],
            ['sku' => 'BRG-004', 'name' => 'HDMI Cable', 'category' => 'COMP', 'unit' => 'PCS', 'brand' => 'Vivan', 'min' => 40, 'max' => 400, 'supplier' => 'SUP002'],
            ['sku' => 'BRG-005', 'name' => 'LAN Cable', 'category' => 'COMP', 'unit' => 'ROLL', 'brand' => 'Belden', 'min' => 10, 'max' => 100, 'supplier' => 'SUP002'],
            ['sku' => 'BRG-006', 'name' => 'Laptop Stand', 'category' => 'COMP', 'unit' => 'PCS', 'brand' => 'Ugreen', 'min' => 15, 'max' => 150, 'supplier' => 'SUP002'],
            ['sku' => 'BRG-007', 'name' => 'Monitor 24 inch', 'category' => 'ELEC', 'unit' => 'UNIT', 'brand' => 'Samsung', 'min' => 5, 'max' => 50, 'supplier' => 'SUP001'],
            ['sku' => 'BRG-008', 'name' => 'Printer Ink', 'category' => 'OFFICE', 'unit' => 'PCS', 'brand' => 'Epson', 'min' => 20, 'max' => 200, 'supplier' => 'SUP001'],
            ['sku' => 'BRG-009', 'name' => 'Packing Tape', 'category' => 'PACK', 'unit' => 'ROLL', 'brand' => '3M', 'min' => 25, 'max' => 250, 'supplier' => 'SUP003'],
            ['sku' => 'BRG-010', 'name' => 'Cardboard Box', 'category' => 'PACK', 'unit' => 'PCS', 'brand' => 'Kemasan', 'min' => 100, 'max' => 1000, 'supplier' => 'SUP003'],
        ];

        foreach ($items as $index => $data) {
            $category = Category::where('code', $data['category'])->firstOrFail();
            $unit = Unit::where('code', $data['unit'])->firstOrFail();
            $supplier = Supplier::where('code', $data['supplier'])->first();

            $barcode = '899' . str_pad((string) ($index + 1), 10, '0', STR_PAD_LEFT);

            Item::updateOrCreate(
                ['sku' => $data['sku']],
                [
                    'barcode' => $barcode,
                    'name' => $data['name'],
                    'category_id' => $category->id,
                    'unit_id' => $unit->id,
                    'brand' => $data['brand'],
                    'minimum_stock' => $data['min'],
                    'maximum_stock' => $data['max'],
                    'primary_supplier_id' => $supplier?->id,
                    'status' => 'active',
                ]
            );
        }
    }
}
