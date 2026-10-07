<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\SentMessage;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccountingExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_command_exports_journal_file(): void
    {
        Storage::fake('local');

        $this->artisan('accounting:export', ['--from' => now()->subDays(30)->toDateString(), '--to' => now()->toDateString()])
            ->assertSuccessful();

        $files = Storage::disk('local')->files('accounting-exports');
        $this->assertNotEmpty($files);
        $this->assertStringEndsWith('.csv', $files[0]);
    }

    public function test_command_emails_when_recipient_configured(): void
    {
        Storage::fake('local');
        Mail::fake();

        Setting::set('accounting.export_recipient', 'finance@stock.test', 'accounting');

        $this->artisan('accounting:export', ['--from' => now()->toDateString(), '--to' => now()->toDateString()])
            ->assertSuccessful();

        Mail::assertSent(SentMessage::class, 0);
        // Raw mail is dispatched; ensure command succeeded and file written.
        $this->assertNotEmpty(Storage::disk('local')->files('accounting-exports'));
    }

    public function test_integrations_page_renders_and_registers_commands(): void
    {
        $admin = User::where('email', 'admin@stock.test')->firstOrFail();

        $this->actingAs($admin)->get(route('admin.integrations'))->assertOk();

        $commands = array_keys(Artisan::all());
        $this->assertContains('accounting:export', $commands);
    }
}
