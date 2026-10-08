<?php

namespace Tests\Feature;

use App\Mail\ReportMail;
use App\Models\Setting;
use App\Services\Reports\ReportMailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ReportMailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Mail::fake();
    }

    public function test_command_sends_report_when_enabled(): void
    {
        Setting::set('reports.mail_enabled', '1', 'reports');
        Setting::set('reports.mail_report', 'stock', 'reports');
        Setting::set('reports.mail_period', 'daily', 'reports');
        Setting::set('reports.mail_recipients', 'finance@stock.test, ops@stock.test', 'reports');

        $this->artisan('reports:mail', ['--period' => 'daily'])->assertSuccessful();

        Mail::assertSent(ReportMail::class, function (ReportMail $mail): bool {
            return $mail->hasTo('finance@stock.test') && $mail->hasTo('ops@stock.test');
        });
    }

    public function test_command_skips_when_disabled(): void
    {
        Setting::set('reports.mail_enabled', '0', 'reports');

        $this->artisan('reports:mail', ['--period' => 'daily'])->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_command_skips_on_period_mismatch(): void
    {
        Setting::set('reports.mail_enabled', '1', 'reports');
        Setting::set('reports.mail_period', 'monthly', 'reports');
        Setting::set('reports.mail_recipients', 'finance@stock.test', 'reports');

        $this->artisan('reports:mail', ['--period' => 'daily'])->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_to_override_forces_send(): void
    {
        $this->artisan('reports:mail', ['--report' => 'low', '--to' => 'ops@stock.test', '--period' => 'weekly'])
            ->assertSuccessful();

        Mail::assertSent(ReportMail::class, fn (ReportMail $mail) => $mail->hasTo('ops@stock.test'));
    }

    public function test_service_builds_all_report_types(): void
    {
        [$from, $to] = ReportMailService::range('daily');

        foreach (ReportMailService::REPORTS as $report) {
            $payload = ReportMailService::build($report, $from, $to);

            $this->assertArrayHasKey('title', $payload);
            $this->assertArrayHasKey('columns', $payload);
            $this->assertArrayHasKey('rows', $payload);
            $this->assertArrayHasKey('summary', $payload);
            $this->assertArrayHasKey('total', $payload);
        }
    }

    public function test_weekly_and_monthly_ranges_are_descending(): void
    {
        [$wf, $wt] = ReportMailService::range('weekly');
        $this->assertTrue($wf->lte($wt));

        [$mf, $mt] = ReportMailService::range('monthly');
        $this->assertTrue($mf->lte($mt));
        $this->assertTrue($mf->isStartOfMonth());
        $this->assertTrue($mt->greaterThan(Carbon::now()->subMonth()->addDays(20)));
    }
}
