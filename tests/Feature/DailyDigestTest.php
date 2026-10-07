<?php

namespace Tests\Feature;

use App\Jobs\SendDailyDigestJob;
use App\Mail\DailyDigestMail;
use App\Models\Setting;
use App\Models\User;
use App\Services\Support\NotificationPreferenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DailyDigestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_digest_command_queues_jobs_for_default_roles(): void
    {
        Queue::fake();

        $this->artisan('inventory:digest')->assertSuccessful();

        Queue::assertPushed(SendDailyDigestJob::class);
    }

    public function test_digest_email_sent_to_admin_with_mail_fake(): void
    {
        Mail::fake();

        $admin = User::where('email', 'admin@stock.test')->firstOrFail();

        $this->artisan('inventory:digest')->assertSuccessful();

        Mail::assertSent(DailyDigestMail::class, function ($mail) use ($admin) {
            return $mail->hasTo($admin->email);
        });
    }

    public function test_digest_disabled_globally_sends_nothing(): void
    {
        Mail::fake();

        Setting::set('notifications.digest_enabled', '0', 'notifications');

        $this->artisan('inventory:digest')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_digest_preference_override(): void
    {
        Mail::fake();

        $admin = User::where('email', 'admin@stock.test')->firstOrFail();
        NotificationPreferenceService::setEmailEnabled($admin, NotificationPreferenceService::digestType(), false);

        $this->artisan('inventory:digest')->assertSuccessful();

        Mail::assertNotSent(DailyDigestMail::class, function ($mail) use ($admin) {
            return $mail->hasTo($admin->email);
        });
    }
}
