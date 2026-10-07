<?php

namespace Tests\Feature;

use App\Jobs\SendWebhookJob;
use App\Models\GoodsIssue;
use App\Models\GoodsIssueItem;
use App\Models\Item;
use App\Models\Location;
use App\Models\Setting;
use App\Models\StockBalance;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Integration\WebhookService;
use App\Services\Inventory\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_disabled_webhook_emits_no_delivery(): void
    {
        Setting::set('integration.webhook_enabled', '0', 'integration');

        WebhookService::emit('goods_issue.posted', ['ok' => true]);

        $this->assertDatabaseMissing('webhook_deliveries', ['event' => 'goods_issue.posted']);
    }

    public function test_enabled_webhook_queues_job_and_signature_is_hmac(): void
    {
        Queue::fake();

        Setting::set('integration.webhook_enabled', '1', 'integration');
        Setting::set('integration.webhook_url', 'https://example.test/hook', 'integration');
        $this->setWebhookSecret('my_secret');

        WebhookService::emit('goods_issue.posted', ['id' => 123]);

        Queue::assertPushed(SendWebhookJob::class);

        $secret = WebhookService::secret();
        $payload = json_encode(['id' => 123], JSON_UNESCAPED_SLASHES);
        $this->assertSame(hash_hmac('sha256', $payload, 'my_secret'), WebhookService::signature($payload, $secret));
    }

    public function test_posting_goods_issue_emits_webhook(): void
    {
        Queue::fake();

        Setting::set('integration.webhook_enabled', '1', 'integration');
        Setting::set('integration.webhook_url', 'https://example.test/hook', 'integration');
        $this->setWebhookSecret('s');

        $warehouse = Warehouse::where('code', 'WH-JKT')->firstOrFail();
        $location = Location::where('code', 'A01-01')->firstOrFail();
        $item = Item::firstOrFail();
        $admin = User::where('email', 'admin@stock.test')->firstOrFail();

        $this->actingAs($admin);

        StockBalance::updateOrCreate(
            ['item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'location_id' => $location->id],
            ['quantity_on_hand' => 100, 'quantity_reserved' => 0],
        );

        $issue = GoodsIssue::create([
            'number' => 'GI-WH-TEST',
            'transaction_date' => today(),
            'destination' => 'Test',
            'warehouse_id' => $warehouse->id,
            'status' => 'approved',
            'created_by' => $admin->id,
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);

        GoodsIssueItem::create([
            'goods_issue_id' => $issue->id, 'item_id' => $item->id,
            'quantity' => 2, 'unit_id' => $item->unit_id, 'location_id' => $location->id,
        ]);

        InventoryService::postGoodsIssue($issue->fresh('issueItems'));

        Queue::assertPushed(SendWebhookJob::class);
    }

    private function setWebhookSecret(string $plain): void
    {
        WebhookService::setSecret($plain);
    }
}
