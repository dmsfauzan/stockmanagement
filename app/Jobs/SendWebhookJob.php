<?php

namespace App\Jobs;

use App\Models\WebhookDelivery;
use App\Services\Integration\WebhookService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class SendWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public int $deliveryId) {}

    public function handle(): void
    {
        $delivery = WebhookDelivery::find($this->deliveryId);

        if (! $delivery) {
            return;
        }

        $body = json_encode($delivery->payload ?? [], JSON_UNESCAPED_SLASHES);
        $secret = WebhookService::secret();

        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-Webhook-Event' => $delivery->event,
            'X-Timestamp' => (string) now()->timestamp,
        ];

        if ($secret !== '') {
            $headers['X-Signature'] = WebhookService::signature($body, $secret);
        }

        $delivery->increment('attempts');

        try {
            $response = Http::withHeaders($headers)->timeout(10)->withBody($body, 'application/json')->post($delivery->url);

            $delivery->update([
                'response_status' => $response->status(),
                'success' => $response->successful(),
                'error' => $response->successful() ? null : substr((string) $response->body(), 0, 500),
            ]);

            if (! $response->successful()) {
                throw new \RuntimeException('Webhook responded '.$response->status());
            }
        } catch (\Throwable $e) {
            $delivery->update([
                'success' => false,
                'error' => substr($e->getMessage(), 0, 500),
            ]);

            throw $e;
        }
    }
}
