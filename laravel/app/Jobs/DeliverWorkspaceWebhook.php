<?php

namespace App\Jobs;

use App\Models\WebhookDelivery;
use App\Services\PublicWebhookTarget;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Throwable;

class DeliverWorkspaceWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public array $backoff = [10, 30, 120, 300];

    public function __construct(public readonly int $deliveryId, public readonly array $payload) {}

    public function handle(PublicWebhookTarget $target): void
    {
        $delivery = WebhookDelivery::query()->with('webhook')->findOrFail($this->deliveryId);
        $webhook = $delivery->webhook;
        if (! $webhook || $webhook->status !== 'active') {
            return;
        }
        $body = json_encode($this->payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $signature = 'sha256='.hash_hmac('sha256', $body, $webhook->signing_secret);

        try {
            $response = Http::withOptions($target->requestOptions($webhook->url, ! app()->environment('testing')))
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'Waflow-Webhook/1.0',
                    'X-Waflow-Event' => $delivery->event,
                    'X-Waflow-Delivery' => $delivery->uuid,
                    'X-Waflow-Signature' => $signature,
                ])
                ->timeout(10)
                ->withBody($body, 'application/json')
                ->post($webhook->url);

            if (! $response->successful()) {
                throw new \RuntimeException("Webhook respondeu HTTP {$response->status()}.");
            }
            $delivery->update(['status' => 'delivered', 'attempt' => $this->attempts(), 'response_status' => $response->status(), 'delivered_at' => now(), 'error' => null]);
            $webhook->update(['last_delivery_status' => 'delivered', 'last_delivered_at' => now(), 'failure_count' => 0]);
        } catch (Throwable $exception) {
            $delivery->update(['status' => 'retrying', 'attempt' => $this->attempts(), 'error' => $exception->getMessage()]);
            $webhook->increment('failure_count');
            $webhook->update(['last_delivery_status' => 'failed']);
            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        WebhookDelivery::query()->whereKey($this->deliveryId)->update(['status' => 'failed', 'error' => $exception?->getMessage()]);
    }
}
