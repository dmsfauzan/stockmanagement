<?php

namespace App\Services\Integration;

use App\Jobs\SendWebhookJob;
use App\Models\Setting;
use App\Models\WebhookDelivery;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

class WebhookService
{
    public const EVENTS = [
        'goods_receipt.posted',
        'goods_issue.posted',
        'adjustment.posted',
        'stock_opname.completed',
        'transfer.dispatched',
        'transfer.received',
        'sales_order.fulfilled',
    ];

    public static function enabled(): bool
    {
        return (string) (Setting::get('integration.webhook_enabled', '0') ?? '0') === '1';
    }

    public static function url(): string
    {
        return trim((string) (Setting::get('integration.webhook_url', '') ?? ''));
    }

    /** @return array<int, string> */
    public static function events(): array
    {
        $raw = (string) (Setting::get('integration.webhook_events', implode(',', self::EVENTS)) ?? '');

        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    public static function secret(): string
    {
        $stored = (string) (Setting::get('integration.webhook_secret', '') ?? '');

        if ($stored === '') {
            return '';
        }

        try {
            return Crypt::decryptString($stored);
        } catch (DecryptException) {
            return $stored;
        }
    }

    public static function setSecret(string $plain): void
    {
        Setting::set('integration.webhook_secret', $plain === '' ? '' : Crypt::encryptString($plain), 'integration');
    }

    public static function signature(string $payload, string $secret): string
    {
        return hash_hmac('sha256', $payload, $secret);
    }

    /**
     * Queue a webhook for the given event (no-op when disabled or event not subscribed).
     *
     * @param  array<string, mixed>  $payload
     */
    public static function emit(string $event, array $payload): ?WebhookDelivery
    {
        if (! static::enabled()) {
            return null;
        }

        if (! in_array($event, static::events(), true)) {
            return null;
        }

        $url = static::url();

        if ($url === '') {
            return null;
        }

        $delivery = WebhookDelivery::create([
            'event' => $event,
            'url' => $url,
            'payload' => $payload,
            'success' => false,
            'attempts' => 0,
        ]);

        try {
            SendWebhookJob::dispatch($delivery->id);
        } catch (\Throwable $e) {
        }

        return $delivery;
    }

    public static function sendTest(): ?WebhookDelivery
    {
        $url = static::url();

        if ($url === '') {
            return null;
        }

        $delivery = WebhookDelivery::create([
            'event' => 'webhook.test',
            'url' => $url,
            'payload' => ['event' => 'webhook.test', 'message' => 'Test webhook from Warehouse Stock Management', 'occurred_at' => now()->toIso8601String()],
            'success' => false,
            'attempts' => 0,
        ]);

        try {
            SendWebhookJob::dispatch($delivery->id);
        } catch (\Throwable $e) {
        }

        return $delivery;
    }
}
