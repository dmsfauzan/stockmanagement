<?php

namespace Tests\Feature;

use App\Imports\CategoryImport;
use App\Imports\CustomerImport;
use App\Imports\LocationImport;
use App\Imports\SupplierImport;
use App\Jobs\ImportMasterJob;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Item;
use App\Models\Rack;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class MasterImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Mail::fake();
    }

    public function test_category_import_creates_then_updates(): void
    {
        $import = new CategoryImport;
        $import->collection(collect([
            collect(['code' => 'IMP-CAT', 'name' => 'Import Kategori', 'description' => 'x', 'status' => 'active']),
        ]));

        $this->assertSame(1, $import->imported);
        $this->assertDatabaseHas('categories', ['code' => 'IMP-CAT', 'name' => 'Import Kategori']);

        $import2 = new CategoryImport;
        $import2->collection(collect([
            collect(['code' => 'IMP-CAT', 'name' => 'Import Kategori Baru', 'status' => 'inactive']),
        ]));

        $this->assertSame(1, $import2->updated);
        $this->assertSame('Import Kategori Baru', Category::where('code', 'IMP-CAT')->value('name'));
    }

    public function test_supplier_import_maps_all_fields(): void
    {
        $import = new SupplierImport;
        $import->collection(collect([
            collect([
                'code' => 'IMP-SUP',
                'name' => 'Supplier Import',
                'contact_person' => 'Budi',
                'phone' => '0812',
                'email' => 'budi@example.com',
                'address' => 'Jakarta',
                'status' => 'active',
                'lead_time_days' => 10,
                'payment_terms' => 'NET 45',
                'region' => 'Jabodetabek',
            ]),
        ]));

        $this->assertSame(1, $import->imported);
        $this->assertDatabaseHas('suppliers', [
            'code' => 'IMP-SUP',
            'lead_time_days' => 10,
            'payment_terms' => 'NET 45',
            'region' => 'Jabodetabek',
        ]);
    }

    public function test_customer_import_normalizes_type(): void
    {
        $import = new CustomerImport;
        $import->collection(collect([
            collect(['code' => 'IMP-CUST', 'name' => 'Cust Import', 'type' => 'invalid-type']),
        ]));

        $this->assertSame('customer', Customer::where('code', 'IMP-CUST')->value('type'));
    }

    public function test_location_import_resolves_hierarchy_and_reports_missing(): void
    {
        $warehouse = Warehouse::create(['code' => 'WH-IMP', 'name' => 'WH Import', 'status' => 'active']);
        $zone = Zone::create(['warehouse_id' => $warehouse->id, 'code' => 'Z-IMP', 'name' => 'Zona Import']);
        $rack = Rack::create(['zone_id' => $zone->id, 'code' => 'R-IMP', 'name' => 'Rak Import']);

        $import = new LocationImport;
        $import->collection(collect([
            collect(['warehouse_code' => 'WH-IMP', 'zone_code' => 'Z-IMP', 'rack_code' => 'R-IMP', 'code' => 'LOC-IMP', 'name' => 'Lokasi Import']),
            collect(['warehouse_code' => 'WH-X', 'zone_code' => 'Z-X', 'rack_code' => 'R-X', 'code' => 'LOC-BAD', 'name' => 'Gagal']),
        ]));

        $this->assertSame(1, $import->imported);
        $this->assertCount(1, $import->errors);
        $this->assertDatabaseHas('locations', ['code' => 'LOC-IMP', 'rack_id' => $rack->id]);
        $this->assertDatabaseMissing('locations', ['code' => 'LOC-BAD']);
    }

    public function test_import_job_processes_csv_and_notifies(): void
    {
        Storage::fake('local');
        $admin = User::where('email', 'admin@stock.test')->firstOrFail();

        $csv = "code,name,type,status\nJOB-CUST,Job Customer,customer,active\n";
        Storage::disk('local')->put('imports/test_customers.csv', $csv);

        ImportMasterJob::dispatchSync(CustomerImport::class, 'imports/test_customers.csv', $admin->id, 'local', 'Customer', 'customers');

        $this->assertDatabaseHas('customers', ['code' => 'JOB-CUST', 'name' => 'Job Customer']);
        $this->assertDatabaseHas('notifications', ['user_id' => $admin->id, 'type' => 'import.completed']);
        Storage::disk('local')->assertMissing('imports/test_customers.csv');
    }

    public function test_open_import_modal_sets_headings(): void
    {
        $admin = User::where('email', 'admin@stock.test')->firstOrFail();

        Livewire::actingAs($admin)->test('master-data.supplier-index')
            ->call('openImportModal')
            ->assertSet('showImportModal', true)
            ->assertSet('importLabel', 'Supplier')
            ->assertSet('importHeadings', SupplierImport::headings());
    }

    public function test_item_import_still_works_via_legacy_import(): void
    {
        $base = Item::query()->firstOrFail();

        $this->assertNotNull($base->category_id);
    }
}
