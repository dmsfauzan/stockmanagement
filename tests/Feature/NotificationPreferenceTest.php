<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\Support\NotificationPreferenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationPreferenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_default_email_enabled_for_known_types(): void
    {
        $admin = User::where('email', 'admin@stock.test')->firstOrFail();

        $this->assertTrue(NotificationPreferenceService::isEmailEnabled($admin, 'stock.low'));
        $this->assertTrue(NotificationPreferenceService::isEmailEnabled($admin, 'approval.request'));
        $this->assertFalse(NotificationPreferenceService::isEmailEnabled($admin, 'unknown.type'));
    }

    public function test_user_override_disables_email(): void
    {
        $admin = User::where('email', 'admin@stock.test')->firstOrFail();

        NotificationPreferenceService::setEmailEnabled($admin, 'stock.low', false);
        $this->assertFalse(NotificationPreferenceService::isEmailEnabled($admin, 'stock.low'));

        NotificationPreferenceService::setEmailEnabled($admin, 'stock.low', true);
        $this->assertTrue(NotificationPreferenceService::isEmailEnabled($admin, 'stock.low'));
    }

    public function test_global_master_switch_disables_all_email(): void
    {
        $admin = User::where('email', 'admin@stock.test')->firstOrFail();

        Setting::set('notifications.email_enabled', '0', 'notifications');

        $this->assertFalse(NotificationPreferenceService::isEmailEnabled($admin, 'stock.low'));
        $this->assertFalse(NotificationPreferenceService::isEmailEnabled($admin, 'approval.request'));
    }

    public function test_digest_defaults_by_role_with_override(): void
    {
        $admin = User::where('email', 'admin@stock.test')->firstOrFail();
        $manager = User::where('email', 'manager@stock.test')->firstOrFail();

        $this->assertTrue(NotificationPreferenceService::isDigestEnabled($admin));
        $this->assertFalse(NotificationPreferenceService::isDigestEnabled($manager));

        NotificationPreferenceService::setEmailEnabled($manager, NotificationPreferenceService::digestType(), true);
        $this->assertTrue(NotificationPreferenceService::isDigestEnabled($manager));
    }
}
