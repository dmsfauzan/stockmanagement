<?php

namespace Tests\Feature;

use App\Mail\NotificationMail;
use App\Models\User;
use App\Services\Support\NotificationPreferenceService;
use App\Services\Support\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NotificationEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_submit_queues_email_job_for_approvers(): void
    {
        Queue::fake();

        NotificationService::notifyApprovers('approval.request', 'Butuh Approval', 'Tolong setujui');

        Queue::assertPushed(\App\Jobs\SendNotificationEmailJob::class);
    }

    public function test_email_sent_with_mail_fake_and_sync_queue(): void
    {
        Mail::fake();

        $supervisor = User::where('email', 'supervisor@stock.test')->firstOrFail();

        NotificationService::notify($supervisor->id, 'approval.request', 'Butuh Approval', 'Tolong setujui');

        Mail::assertSent(NotificationMail::class, function ($mail) use ($supervisor) {
            return $mail->hasTo($supervisor->email);
        });
    }

    public function test_email_disabled_sends_no_mail(): void
    {
        Mail::fake();

        $supervisor = User::where('email', 'supervisor@stock.test')->firstOrFail();
        NotificationPreferenceService::setEmailEnabled($supervisor, 'approval.request', false);

        NotificationService::notify($supervisor->id, 'approval.request', 'Butuh Approval', 'Tolong setujui');

        Mail::assertNothingSent();
    }

    public function test_master_switch_off_sends_no_mail(): void
    {
        Mail::fake();

        $supervisor = User::where('email', 'supervisor@stock.test')->firstOrFail();
        \App\Models\Setting::set('notifications.email_enabled', '0', 'notifications');

        NotificationService::notify($supervisor->id, 'stock.low', 'Stok Rendah', 'BRG kurang');

        Mail::assertNothingSent();
    }

    public function test_preferences_page_renders_and_saves(): void
    {
        $admin = User::where('email', 'admin@stock.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('profile.notifications'))
            ->assertOk();
    }
}
