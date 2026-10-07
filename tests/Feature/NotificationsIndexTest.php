<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use App\Services\Support\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NotificationsIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_page_renders(): void
    {
        $admin = User::where('email', 'admin@stock.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('notifications.index'))
            ->assertOk();
    }

    public function test_search_and_type_filters_work(): void
    {
        $admin = User::where('email', 'admin@stock.test')->firstOrFail();

        NotificationService::notify($admin->id, 'stock.low', 'Stok Rendah Test', 'BRG-TEST kurang');

        Livewire::actingAs($admin)->test('notifications-index')
            ->set('search', 'BRG-TEST')
            ->assertSee('Stok Rendah Test');

        Livewire::actingAs($admin)->test('notifications-index')
            ->set('search', 'tidak-ada-hasil-xyz')
            ->assertDontSee('Stok Rendah Test');

        Livewire::actingAs($admin)->test('notifications-index')
            ->set('typeFilter', 'stock.low')
            ->assertSee('Stok Rendah Test');
    }

    public function test_mark_as_read_and_mark_all_as_read(): void
    {
        $admin = User::where('email', 'admin@stock.test')->firstOrFail();

        NotificationService::notify($admin->id, 'approval.request', 'Butuh Approval', 'Tolong setujui');

        $notification = Notification::where('user_id', $admin->id)
            ->where('type', 'approval.request')
            ->latest('id')
            ->first();

        Livewire::actingAs($admin)->test('notifications-index')
            ->call('markAsRead', $notification->id);

        $this->assertNotNull($notification->fresh()->read_at);

        Livewire::actingAs($admin)->test('notifications-index')
            ->call('markAllAsRead');

        $this->assertEquals(0, Notification::where('user_id', $admin->id)->whereNull('read_at')->count());
    }
}
