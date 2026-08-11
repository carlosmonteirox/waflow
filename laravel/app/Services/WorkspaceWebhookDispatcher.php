<?php

namespace App\Services;

use App\Jobs\DeliverWorkspaceWebhook;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use App\Models\Workspace;

class WorkspaceWebhookDispatcher
{
    public function dispatch(Workspace $workspace, string $event, array $data, ?string $idempotencyKey = null): void
    {
        $webhooks = $workspace->webhooks()->where('status', 'active')->get()
            ->filter(fn ($webhook) => in_array('*', $webhook->events ?? [], true) || in_array($event, $webhook->events ?? [], true));

        foreach ($webhooks as $webhook) {
            $this->dispatchWebhook($webhook, $event, $data, $idempotencyKey);
        }
    }

    public function dispatchWebhook(Webhook $webhook, string $event, array $data, ?string $idempotencyKey = null): void
    {
        $payload = [
            'event' => $event,
            'timestamp' => now()->toIso8601String(),
            'workspace_id' => $webhook->workspace_id,
            'data' => $data,
        ];
        $deliveryKey = $idempotencyKey ? hash('sha256', $idempotencyKey) : null;
        $attributes = ['webhook_id' => $webhook->id, 'idempotency_key' => $deliveryKey];
        $delivery = $idempotencyKey
            ? WebhookDelivery::query()->firstOrCreate($attributes, [
                'workspace_id' => $webhook->workspace_id, 'event' => $event, 'status' => 'pending',
            ])
            : WebhookDelivery::query()->create(array_merge($attributes, [
                'workspace_id' => $webhook->workspace_id, 'event' => $event, 'status' => 'pending',
            ]));
        if ($delivery->status === 'delivered') {
            return;
        }

        $payload['id'] = $delivery->uuid;
        $delivery->update(['payload' => $payload]);
        DeliverWorkspaceWebhook::dispatch($delivery->id, $payload);
    }
}
