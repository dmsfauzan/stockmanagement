<?php

namespace Tests\Feature;

use App\Jobs\ImportItemsJob;
use App\Models\Item;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportQueueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_import_action_dispatches_queued_job(): void
    {
        Queue::fake();

        $admin = User::where('email', 'admin@stock.test')->firstOrFail();

        $csv = "sku,barcode,name,category_code,unit_code,brand,minimum_stock,maximum_stock,supplier_code,status\n"
            ."BRG-Q-1,,Queue Item,ELEC,PCS,,5,50,,active\n";

        Storage::fake('local');
        Storage::disk('local')->put('imports/test.csv', $csv);

        ImportItemsJob::dispatch('imports/test.csv', $admin->id, 'local');

        Queue::assertPushed(ImportItemsJob::class);
    }

    public function test_import_job_creates_items_and_completion_notification(): void
    {
        $admin = User::where('email', 'admin@stock.test')->firstOrFail();

        $csv = "sku,barcode,name,category_code,unit_code,brand,minimum_stock,maximum_stock,supplier_code,status\n"
            ."BRG-Q-1,,Queue Item,ELEC,PCS,,5,50,,active\n"
            ."BRG-Q-2,,Queue Item Dua,OFFICE,BOX,,3,30,,active\n";

        Storage::disk('local')->put('imports/test.csv', $csv);

        (new ImportItemsJob('imports/test.csv', $admin->id, 'local'))->handle();

        $this->assertTrue(Item::where('sku', 'BRG-Q-1')->exists());
        $this->assertTrue(Item::where('sku', 'BRG-Q-2')->exists());

        $this->assertTrue(
            Notification::where('user_id', $admin->id)
                ->where('type', 'import.completed')
                ->exists(),
        );

        Storage::disk('local')->delete('imports/test.csv');
    }
}
