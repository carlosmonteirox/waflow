<?php

namespace App\Services;

use App\Jobs\DeliverWorkspaceWebhook;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use App\Models\Workspace;

class WorkspaceWebhookDispatcher
{
    public function dispatch(Workspace $workspace, string $event, array $data): void
    {
        $webhooks = $workspace->webhooks()->where('status', 'active')->get()
            ->filter(fn ($webhook) => in_array('*', $webhook->events ?? [], true) || in_array($event, $webhook->events ?? [], true));

        foreach ($webhooks as $webhook) {
            $this->dispatchWebhook($webhook, $event, $data);
        }
    }

    public function dispatchWebhook(Webhook $webhook, string $event, array $data): void
    {
        $delivery = WebhookDelivery::query()->create([
            'webhook_id' => $webhook->id,
            'workspace_id' => $webhook->workspace_id,
            'event' => $event,
            'status' => 'pending',
        ]);
        DeliverWorkspaceWebhook::dispatch($delivery->id, [
            'id' => $delivery->uuid,
            'event' => $event,
            'timestamp' => now()->toIso8601String(),
            'workspace_id' => $webhook->workspace_id,
            'data' => $data,
        ]);
    }
}
