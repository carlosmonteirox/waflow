<?php

namespace App\Jobs;

use App\Models\FlowExecution;
use App\Services\UsageLimiter;
use App\Services\WorkspaceWebhookDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Arr;
use Throwable;

class ExecuteAutomationFlow implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [5, 30, 120];

    public function __construct(public readonly int $executionId) {}

    public function handle(UsageLimiter $limiter, WorkspaceWebhookDispatcher $webhooks): void
    {
        $execution = FlowExecution::query()->with(['flow', 'workspace'])->findOrFail($this->executionId);
        if (in_array($execution->status, ['completed', 'skipped'], true)) {
            return;
        }

        $execution->update(['status' => 'running', 'started_at' => now(), 'error' => null]);
        $context = ['trigger' => $execution->trigger_payload ?? [], 'variables' => []];
        $actions = [];

        try {
            foreach ($execution->flow->steps ?? [] as $index => $step) {
                if (($step['type'] ?? null) === 'condition' && ! $this->condition($step, $context)) {
                    $execution->update([
                        'status' => 'skipped',
                        'result' => ['reason' => 'condition_not_matched', 'step' => $index, 'actions' => $actions],
                        'completed_at' => now(),
                    ]);
                    $execution->flow->update(['last_run_at' => now(), 'last_error' => null]);

                    return;
                }
                if (($step['type'] ?? null) === 'action') {
                    $actions[] = $this->action($step, $index, $execution, $context, $limiter, $webhooks);
                }
            }

            $execution->update(['status' => 'completed', 'result' => ['actions' => $actions, 'context' => $context], 'completed_at' => now()]);
            $execution->flow->increment('run_count');
            $execution->flow->update(['last_run_at' => now(), 'last_error' => null]);
        } catch (Throwable $exception) {
            $execution->update(['status' => 'retrying', 'error' => $exception->getMessage()]);
            throw $exception;
        }

        try {
            $webhooks->dispatch(
                $execution->workspace,
                'automation.completed',
                $execution->fresh()->toArray(),
                'automation:'.$execution->uuid.':completed',
            );
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $execution = FlowExecution::query()->with('flow')->find($this->executionId);
        if (! $execution) {
            return;
        }
        $execution->update(['status' => 'failed', 'error' => $exception?->getMessage(), 'completed_at' => now()]);
        $execution->flow->increment('failure_count');
        $execution->flow->update(['last_run_at' => now(), 'last_error' => $exception?->getMessage()]);
    }

    private function condition(array $step, array $context): bool
    {
        $actual = data_get($context, $step['field']);
        $expected = $step['value'] ?? null;

        return match ($step['operator']) {
            'equals' => $actual == $expected,
            'not_equals' => $actual != $expected,
            'contains' => str_contains(mb_strtolower((string) $actual), mb_strtolower((string) $expected)),
            'starts_with' => str_starts_with(mb_strtolower((string) $actual), mb_strtolower((string) $expected)),
            'ends_with' => str_ends_with(mb_strtolower((string) $actual), mb_strtolower((string) $expected)),
            'exists' => $actual !== null && $actual !== '',
            'in' => in_array($actual, Arr::wrap($expected), true),
            default => false,
        };
    }

    private function action(
        array $step,
        int $index,
        FlowExecution $execution,
        array &$context,
        UsageLimiter $limiter,
        WorkspaceWebhookDispatcher $webhooks,
    ): array {
        if ($step['action'] === 'set_context') {
            $key = (string) ($step['key'] ?? 'value');
            data_set($context, 'variables.'.$key, $this->interpolate($step['value'] ?? null, $context));

            return ['action' => 'set_context', 'key' => $key];
        }

        if ($step['action'] === 'dispatch_webhook') {
            $event = (string) ($step['event'] ?? 'automation.action');
            $webhooks->dispatch($execution->workspace, $event, [
                'flow_uuid' => $execution->flow->uuid,
                'execution_uuid' => $execution->uuid,
                'payload' => $context,
            ], 'automation:'.$execution->uuid.':step:'.$index);

            return ['action' => 'dispatch_webhook', 'event' => $event];
        }

        $limiter->ensureMessages($execution->workspace);
        $instanceUuid = $this->interpolate($step['instance_uuid'] ?? data_get($context, 'trigger.instance_uuid'), $context);
        $instance = $execution->workspace->instances()->where('uuid', $instanceUuid)->firstOrFail();
        abort_unless($instance->status === 'connected', 409, 'A instância configurada na automação não está conectada.');

        $recipient = (string) $this->interpolate($step['to'] ?? '{{trigger.from}}', $context);
        $messageType = (string) ($step['message_type'] ?? 'text');
        $payload = ['type' => $messageType];
        foreach (['text', 'url', 'caption', 'mimetype', 'filename'] as $field) {
            if (array_key_exists($field, $step)) {
                $payload[$field] = $this->interpolate($step[$field], $context);
            }
        }

        $idempotencyKey = 'flow:'.$execution->uuid.':'.$index;
        $message = $execution->workspace->messageEvents()->where('idempotency_key', $idempotencyKey)->first();
        if (! $message) {
            $message = $execution->workspace->messageEvents()->create([
                'whatsapp_instance_id' => $instance->id,
                'direction' => 'outbound',
                'status' => 'queued',
                'to' => $recipient,
                'idempotency_key' => $idempotencyKey,
                'payload' => $payload,
            ]);
        }
        if (in_array($message->status, ['queued', 'retrying'], true)) {
            SendWhatsAppMessage::dispatch($message->id);
        }

        return ['action' => 'send_message', 'message_uuid' => $message->uuid];
    }

    private function interpolate(mixed $value, array $context): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        return preg_replace_callback('/\{\{\s*([^}]+)\s*\}\}/', fn ($matches) => (string) data_get($context, trim($matches[1]), ''), $value);
    }
}
