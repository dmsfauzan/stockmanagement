<?php

namespace Tests\Feature;

use App\Livewire\Transactions\StockAdjustmentForm;
use App\Models\Item;
use App\Models\Location;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class SecurityUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('public');
    }

    private function fillHeader(): array
    {
        return [
            'transaction_date' => today()->toDateString(),
            'warehouse_id' => Warehouse::query()->firstOrFail()->id,
            'location_id' => Location::query()->firstOrFail()->id,
            'reason' => 'Uji keamanan',
            'items' => [[
                'item_id' => Item::query()->firstOrFail()->id,
                'system_quantity' => 0,
                'actual_quantity' => 5,
                'difference' => 5,
                'notes' => '',
            ]],
        ];
    }

    public function test_php_attachment_is_rejected(): void
    {
        $admin = User::where('email', 'admin@stock.test')->firstOrFail();

        Livewire::actingAs($admin)->test(StockAdjustmentForm::class)
            ->set('attachment', UploadedFile::fake()->create('shell.php', 10, 'application/x-php'))
            ->call('save')
            ->assertHasErrors('attachment');
    }

    public function test_html_attachment_is_rejected(): void
    {
        $admin = User::where('email', 'admin@stock.test')->firstOrFail();

        Livewire::actingAs($admin)->test(StockAdjustmentForm::class)
            ->set('attachment', UploadedFile::fake()->create('xss.html', 10, 'text/html'))
            ->call('save')
            ->assertHasErrors('attachment');
    }

    public function test_valid_pdf_attachment_is_accepted_and_stored_with_safe_name(): void
    {
        $admin = User::where('email', 'admin@stock.test')->firstOrFail();

        $component = Livewire::actingAs($admin)->test(StockAdjustmentForm::class);

        foreach ($this->fillHeader() as $key => $value) {
            $component->set($key, $value);
        }

        $component
            ->set('attachment', UploadedFile::fake()->create('document.pdf', 10, 'application/pdf'))
            ->call('save')
            ->assertHasNoErrors('attachment');

        $files = Storage::disk('public')->files('adjustments');
        $this->assertNotEmpty($files);
        $this->assertStringEndsWith('.pdf', $files[0]);
    }
}
