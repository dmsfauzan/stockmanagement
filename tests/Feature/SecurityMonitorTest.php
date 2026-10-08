<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\SecurityEvent;
use App\Models\Setting;
use App\Models\User;
use App\Services\Security\GeoLocationService;
use App\Services\Security\SecurityMonitor;
use Illuminate\Auth\Events\Failed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SecurityMonitorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Cache::flush();
    }

    public function test_failed_login_records_event(): void
    {
        Setting::set('security.monitor_enabled', '1', 'security');
        Cache::forget('security_event_dedupe:'.md5('127.0.0.1|login_failed'));

        event(new Failed('web', new User(['email' => 'phisher@example.com']), ['email' => 'phisher@example.com']));

        $this->assertDatabaseHas('security_events', ['event_type' => 'login_failed', 'email' => 'phisher@example.com']);
    }

    public function test_record_response_unauthorized(): void
    {
        $this->actingAs(User::where('email', 'staff@stock.test')->firstOrFail())
            ->get(route('admin.users'))
            ->assertForbidden();

        $this->assertDatabaseHas('security_events', ['event_type' => 'unauthorized']);
    }

    public function test_record_response_rate_limited(): void
    {
        $this->get('/api/me')->assertUnauthorized();

        SecurityMonitor::record('rate_limited', 'high', null, ['status' => 429]);

        $this->assertDatabaseHas('security_events', ['event_type' => 'rate_limited']);
    }

    public function test_suspicious_path_recorded(): void
    {
        $admin = User::where('email', 'admin@stock.test')->firstOrFail();

        $this->actingAs($admin)->get('/wp-admin')->assertNotFound();

        $this->assertDatabaseHas('security_events', ['event_type' => 'suspicious_path']);
    }

    public function test_banned_ip_is_blocked_globally(): void
    {
        Setting::set('security.monitor_enabled', '1', 'security');
        Setting::set('security.autoban_enabled', '0', 'security');

        SecurityMonitor::ban('198.51.100.9', 'unit-test ban', 'manual', 60, 1);

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.9'])->get('/dashboard')->assertForbidden();

        SecurityMonitor::unban('198.51.100.9');

        $this->assertDatabaseMissing('banned_ips', ['ip_address' => '198.51.100.9']);
    }

    public function test_allowlisted_ip_is_not_auto_banned(): void
    {
        Setting::set('security.monitor_enabled', '1', 'security');
        Setting::set('security.autoban_enabled', '1', 'security');
        Setting::set('security.autoban_threshold', '2', 'security');
        Setting::set('security.autoban_window', '5', 'security');
        Setting::set('security.autoban_duration', '60', 'security');
        Setting::set('security.ban_allowlist', '198.51.100.9', 'security');

        $request = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '198.51.100.9']);

        SecurityMonitor::record('suspicious_path', 'high', $request);
        Cache::forget('security_event_dedupe:'.md5('198.51.100.9|suspicious_path'));
        SecurityMonitor::record('suspicious_path', 'high', $request);

        $this->assertDatabaseMissing('banned_ips', ['ip_address' => '198.51.100.9']);
    }

    public function test_auto_ban_triggers_at_threshold(): void
    {
        Setting::set('security.monitor_enabled', '1', 'security');
        Setting::set('security.autoban_enabled', '1', 'security');
        Setting::set('security.autoban_threshold', '3', 'security');
        Setting::set('security.autoban_window', '10', 'security');
        Setting::set('security.autoban_duration', '15', 'security');

        $request = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '198.51.100.7']);

        for ($i = 0; $i < 3; $i++) {
            SecurityMonitor::record('suspicious_path', 'high', $request, ['seq' => $i]);
            Cache::forget('security_event_dedupe:'.md5('198.51.100.7|suspicious_path'));
        }

        $this->assertDatabaseHas('banned_ips', ['ip_address' => '198.51.100.7', 'source' => 'auto']);

        SecurityMonitor::unban('198.51.100.7');
    }

    public function test_auto_ban_skipped_for_authenticated_user(): void
    {
        Setting::set('security.monitor_enabled', '1', 'security');
        Setting::set('security.autoban_enabled', '1', 'security');
        Setting::set('security.autoban_threshold', '2', 'security');
        Setting::set('security.autoban_window', '5', 'security');

        $admin = User::where('email', 'admin@stock.test')->firstOrFail();
        $request = Request::create('/', 'GET');
        $request->setLaravelSession(app('session.store'));
        $request->setUserResolver(fn () => $admin);

        $this->actingAs($admin)->get(route('admin.users'))->assertOk();

        SecurityMonitor::record('unauthorized', 'warning', $request, [], null, $admin->id);
        Cache::forget('security_event_dedupe:'.md5('127.0.0.1|unauthorized'));
        SecurityMonitor::record('unauthorized', 'warning', $request, [], null, $admin->id);

        $this->assertDatabaseMissing('banned_ips', ['source' => 'auto']);
    }

    public function test_purge_command(): void
    {
        SecurityEvent::create([
            'event_type' => 'suspicious_path', 'severity' => 'high',
            'ip_address' => '203.0.113.1', 'path' => '/wp-admin',
            'created_at' => now()->subDays(100),
        ]);

        Setting::set('security.retention_days', '30', 'security');

        $this->artisan('security:purge')->assertSuccessful();

        $this->assertDatabaseMissing('security_events', ['ip_address' => '203.0.113.1']);
    }

    public function test_geo_service_skips_private_ip(): void
    {
        $this->assertNull(GeoLocationService::forIp('127.0.0.1'));
        $this->assertNull(GeoLocationService::forIp('192.168.0.10'));
        $this->assertNull(GeoLocationService::forIp('::1'));
    }

    public function test_geo_service_respects_disabled_setting(): void
    {
        Setting::set('security.geoip_enabled', '0', 'security');
        Cache::forget('security_geoip_'.md5('8.8.8.8'));
        config(['security.geoip.enabled' => true]);

        $this->assertNull(GeoLocationService::forIp('8.8.8.8'));
    }

    public function test_security_page_renders_and_respects_permission(): void
    {
        $admin = User::where('email', 'admin@stock.test')->firstOrFail();
        $staff = User::where('email', 'staff@stock.test')->firstOrFail();

        $this->freshAdminRequestAs($admin)
            ->get(route('admin.security'))->assertOk();

        $this->freshAdminRequestAs($staff)
            ->get(route('admin.security'))->assertForbidden();

        $role = Role::where('slug', 'warehouse_staff')->firstOrFail();
        $perm = Permission::where('slug', 'security.manage')->firstOrFail();

        $role->permissions()->attach($perm->id);

        $this->freshAdminRequestAs($staff)
            ->get(route('admin.security'))->assertOk();

        $role->permissions()->detach($perm->id);
    }

    private function freshAdminRequestAs(User $user): static
    {
        return $this->actingAs(User::find($user->id));
    }

    public function test_blocked_ip_cannot_call_api(): void
    {
        Setting::set('security.monitor_enabled', '1', 'security');

        SecurityMonitor::ban('198.51.100.8', 'unit-test', 'manual', 60, 1);

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.8'])->getJson('/api/me')->assertStatus(403);

        SecurityMonitor::unban('198.51.100.8');
    }

    public function test_banned_ip_records_banned_blocked_event(): void
    {
        SecurityMonitor::ban('198.51.100.10', 'test', 'manual', 60, 1);

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.10'])->get('/dashboard')->assertForbidden();

        $this->assertDatabaseHas('security_events', ['ip_address' => '198.51.100.10', 'event_type' => 'banned_blocked']);

        SecurityMonitor::unban('198.51.100.10');
    }
}
