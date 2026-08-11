<?php

namespace App\Services;

use App\Models\Workspace;
use Symfony\Component\HttpKernel\Exception\HttpException;

class UsageLimiter
{
    public function ensureInstances(Workspace $workspace, int $increment = 1): void
    {
        $this->ensure($workspace, 'instances', $workspace->instances()->count(), $increment);
    }

    public function ensureMessages(Workspace $workspace, int $increment = 1): void
    {
        $used = $workspace->messageEvents()->where('direction', 'outbound')->where('created_at', '>=', now()->startOfMonth())->count();
        $this->ensure($workspace, 'messages', $used, $increment);
    }

    public function ensureWebhooks(Workspace $workspace, int $increment = 1): void
    {
        $this->ensure($workspace, 'webhooks', $workspace->webhooks()->count(), $increment);
    }

    public function ensureApiKeys(Workspace $workspace, int $increment = 1): void
    {
        $this->ensure($workspace, 'api_keys', $workspace->apiKeys()->count(), $increment);
    }

    public function ensurePortals(Workspace $workspace, int $increment = 1): void
    {
        $this->ensure($workspace, 'portals', $workspace->portals()->count(), $increment);
    }

    public function limit(Workspace $workspace, string $resource): int
    {
        $workspace->loadMissing('subscription.plan');
        if ($workspace->subscription?->status === 'trialing') {
            return (int) config("waflow.trial_limits.{$resource}", 0);
        }

        return (int) data_get($workspace->subscription?->plan, match ($resource) {
            'instances' => 'instance_limit',
            'messages' => 'message_limit',
            'webhooks' => 'webhook_limit',
            'api_keys' => 'api_key_limit',
            'portals' => 'instance_limit',
            default => null,
        }, 0);
    }

    private function ensure(Workspace $workspace, string $resource, int $used, int $increment): void
    {
        $limit = $this->limit($workspace, $resource);
        if ($limit > 0 && $used + $increment > $limit) {
            throw new HttpException(429, "Limite de {$resource} atingido para o plano atual.");
        }
    }
}
