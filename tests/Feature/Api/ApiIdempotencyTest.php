<?php

namespace Tests\Feature\Api;

use App\Models\GoodsReceipt;
use App\Models\IdempotencyKey;
use App\Models\Item;
use App\Models\Location;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function adminToken(): string
    {
        return User::where('email', 'admin@stock.test')->firstOrFail()
            ->createToken('test', ['*'])->plainTextToken;
    }

    private function payload(array $itemOverrides = []): array
    {
        return [
            'transaction_date' => now()->toDateString(),
            'supplier_id' => Supplier::where('code', 'SUP001')->value('id'),
            'warehouse_id' => Warehouse::where('code', 'WH-JKT')->value('id'),
            'items' => [array_merge([
                'item_id' => Item::where('sku', 'BRG-002')->value('id'),
                'quantity' => 7,
                'unit_cost' => 1000,
                'unit_id' => Unit::where('code', 'PCS')->value('id'),
                'location_id' => Location::where('code', 'A01-02')->value('id'),
            ], $itemOverrides)],
        ];
    }

    public function test_repeated_create_with_same_key_returns_same_response(): void
    {
        $token = $this->adminToken();
        $key = 'idem-'.uniqid('', true);
        $before = GoodsReceipt::count();

        $first = $this->withToken($token)->postJson('/api/goods-receipts', $this->payload(), ['Idempotency-Key' => $key])
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->json('data.id');

        $this->assertSame($before + 1, GoodsReceipt::count());

        $second = $this->withToken($token)->postJson('/api/goods-receipts', $this->payload(), ['Idempotency-Key' => $key])
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertHeader('Idempotent-Replay', 'true')
            ->json('data.id');

        $this->assertSame($first, $second);
        $this->assertSame($before + 1, GoodsReceipt::count());
    }

    public function test_same_key_different_payload_is_rejected(): void
    {
        $token = $this->adminToken();
        $key = 'idem-conflict-'.uniqid('', true);
        $before = GoodsReceipt::count();

        $this->withToken($token)->postJson('/api/goods-receipts', $this->payload(['quantity' => 1]), ['Idempotency-Key' => $key])
            ->assertStatus(201);

        $this->withToken($token)->postJson('/api/goods-receipts', $this->payload(['quantity' => 99]), ['Idempotency-Key' => $key])
            ->assertStatus(409)
            ->assertJsonPath('success', false);

        $this->assertSame($before + 1, GoodsReceipt::count());
    }

    public function test_without_header_two_posts_create_two_records(): void
    {
        $token = $this->adminToken();
        $before = GoodsReceipt::count();

        $this->withToken($token)->postJson('/api/goods-receipts', $this->payload())->assertStatus(201);
        $this->withToken($token)->postJson('/api/goods-receipts', $this->payload())->assertStatus(201);

        $this->assertSame($before + 2, GoodsReceipt::count());
    }

    public function test_purge_deletes_expired_keys(): void
    {
        $record = IdempotencyKey::create([
            'key' => 'old-'.uniqid('', true),
            'user_id' => null,
            'method' => 'POST',
            'path' => '/api/goods-receipts',
            'request_hash' => sha1('a'),
            'status_code' => 201,
            'response_body' => '{}',
        ]);

        $record->forceFill([
            'created_at' => now()->subHours(25),
            'updated_at' => now()->subHours(25),
        ])->save();

        $this->artisan('idempotency:purge')->assertSuccessful();

        $this->assertDatabaseCount('idempotency_keys', 0);
    }
}
