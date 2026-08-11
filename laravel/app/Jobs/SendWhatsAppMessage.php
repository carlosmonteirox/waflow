<?php

namespace App\Jobs;

use App\Models\MessageEvent;
use App\Services\OpenWaClient;
use App\Services\WorkspaceWebhookDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SendWhatsAppMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [5, 30, 120];

    public function __construct(public readonly int $messageEventId) {}

    public function handle(OpenWaClient $openwa, WorkspaceWebhookDispatcher $webhooks): void
    {
        $message = MessageEvent::query()->with(['instance', 'workspace'])->findOrFail($this->messageEventId);
        if (in_array($message->status, ['sent', 'delivered', 'read'], true)) {
            return;
        }
        if (! $message->instance?->openwa_session_id || $message->instance->status !== 'connected') {
            throw new \RuntimeException('A instância do WhatsApp não está conectada.');
        }

        $message->update(['status' => 'processing', 'attempts' => $message->attempts + 1, 'dispatched_at' => now(), 'error' => null]);

        try {
            $result = $openwa->sendMessage($message->instance->openwa_session_id, $message->to, $message->payload ?? []);
            $message->update([
                'status' => 'sent',
                'provider_message_id' => $result['messageId'] ?? null,
                'processed_at' => now(),
                'payload' => array_merge($message->payload ?? [], ['provider_response' => $result]),
            ]);
            $webhooks->dispatch($message->workspace, 'message.sent', $message->fresh()->toArray());
        } catch (Throwable $exception) {
            $message->update(['status' => 'retrying', 'error' => $exception->getMessage()]);
            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $message = MessageEvent::query()->with('workspace')->find($this->messageEventId);
        if (! $message) {
            return;
        }
        $message->update(['status' => 'failed', 'error' => $exception?->getMessage(), 'processed_at' => now()]);
        app(WorkspaceWebhookDispatcher::class)->dispatch($message->workspace, 'message.failed', $message->fresh()->toArray());
    }
}
