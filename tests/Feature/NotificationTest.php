<?php

namespace Tests\Feature;

use App\Livewire\NotificationsBell;
use App\Models\Notification;
use App\Models\User;
use App\Services\Support\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $supervisor;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRoleId = DB::table('roles')->insertGetId(['name' => 'Administrator', 'slug' => 'admin', 'is_system' => true, 'created_at' => now(), 'updated_at' => now()]);
        $supRoleId = DB::table('roles')->insertGetId(['name' => 'Supervisor', 'slug' => 'supervisor', 'is_system' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('roles')->insert(['name' => 'Warehouse Staff', 'slug' => 'warehouse_staff', 'is_system' => true, 'created_at' => now(), 'updated_at' => now()]);

        $permId = DB::table('permissions')->insertGetId(['slug' => 'admin', 'name' => 'All', 'group' => 'system', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('role_permission')->insert(['role_id' => $adminRoleId, 'permission_id' => $permId]);
        DB::table('role_permission')->insert(['role_id' => $supRoleId, 'permission_id' => $permId]);

        $this->admin = User::factory()->create();
        $this->supervisor = User::factory()->create();

        DB::table('user_role')->insert(['user_id' => $this->admin->id, 'role_id' => $adminRoleId]);
        DB::table('user_role')->insert(['user_id' => $this->supervisor->id, 'role_id' => $supRoleId]);

        $this->actingAs($this->admin);
    }

    public function test_notify_creates_row_with_null_read_at(): void
    {
        $notification = NotificationService::notify($this->admin->id, 'approval.request', 'Approval Test', 'Message body');

        $this->assertNotNull($notification);
        $this->assertNull($notification->read_at);
        $this->assertDatabaseHas('notifications', ['user_id' => $this->admin->id, 'type' => 'approval.request']);
    }

    public function test_notify_role_creates_per_user(): void
    {
        $admin2 = User::factory()->create();
        DB::table('user_role')->insert(['user_id' => $admin2->id, 'role_id' => DB::table('roles')->where('slug', 'admin')->value('id')]);

        NotificationService::notifyRole('admin', 'stock.low', 'Low', 'Low stock message');

        $this->assertDatabaseCount('notifications', 2);
    }

    public function test_mark_as_read_sets_timestamp(): void
    {
        $notification = NotificationService::notify($this->admin->id, 'approval.request', 'T', 'M');
        $this->assertNull($notification->fresh()->read_at);

        $notification->markAsRead();

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_bell_unread_count_and_mark_all(): void
    {
        NotificationService::notify($this->admin->id, 'approval.request', 'T1', 'M1');
        NotificationService::notify($this->admin->id, 'stock.low', 'T2', 'M2');
        NotificationService::notify($this->supervisor->id, 'stock.low', 'T3', 'M3');

        $component = Livewire::test(NotificationsBell::class);
        $component->assertSet('unreadCount', 2);

        $component->call('markAllAsRead');

        $this->assertEquals(0, Notification::where('user_id', $this->admin->id)->unread()->count());
        $component->assertSet('unreadCount', 0);
    }
}
