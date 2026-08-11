<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InboundEvent;
use App\Models\MessageEvent;
use App\Models\WhatsAppInstance;
use App\Services\AutomationExecutor;
use App\Services\OpenWaClient;
use App\Services\TrialAbuseService;
use App\Services\WorkspaceWebhookDispatcher;
use App\Support\ApiResponse;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OpenWaEventController extends Controller
{
    public function __invoke(Request $request, WorkspaceWebhookDispatcher $dispatcher, TrialAbuseService $abuse, OpenWaClient $openwa, AutomationExecutor $automations): JsonResponse
    {
        $rawBody = $request->getContent();
        $expected = 'sha256='.hash_hmac('sha256', $rawBody, (string) config('services.openwa.webhook_secret'));
        $provided = (string) $request->header('X-OpenWA-Signature');
        if (! $provided || ! hash_equals($expected, $provided)) {
            return ApiResponse::error($request, 'invalid_signature', 'Assinatura inválida.', 401);
        }

        $payload = $request->json()->all();
        $eventName = (string) ($payload['event'] ?? $request->header('X-OpenWA-Event', 'unknown'));
        $idempotencyKey = (string) ($payload['idempotencyKey'] ?? $request->header('X-OpenWA-Idempotency-Key'));
        if ($idempotencyKey === '') {
            return ApiResponse::error($request, 'idempotency_key_missing', 'Chave de idempotência ausente.', 422);
        }

        $instance = WhatsAppInstance::query()->with('workspace')->where('openwa_session_id', $payload['sessionId'] ?? null)->first();
        try {
            $event = InboundEvent::query()->create([
                'workspace_id' => $instance?->workspace_id,
                'whatsapp_instance_id' => $instance?->id,
                'event' => $eventName,
                'idempotency_key' => $idempotencyKey,
                'delivery_id' => $payload['deliveryId'] ?? $request->header('X-OpenWA-Delivery-Id'),
                'payload' => $payload,
            ]);
        } catch (QueryException $exception) {
            if (InboundEvent::query()->where('idempotency_key', $idempotencyKey)->exists()) {
                return response()->json(['accepted' => true, 'duplicate' => true]);
            }

            throw $exception;
        }

        if ($instance) {
            $this->apply($instance, $eventName, is_array($payload['data'] ?? null) ? $payload['data'] : [], $abuse, $openwa);
            $dispatcher->dispatch($instance->workspace, $eventName, $payload['data'] ?? [], 'openwa:'.$idempotencyKey.':'.$eventName);
            $trigger = match ($eventName) {
                'message.received' => 'message_received',
                'message.failed' => 'message_failed',
                'session.authenticated' => 'instance_connected',
                default => null,
            };
            if ($trigger) {
                $automations->dispatch(
                    $instance->workspace,
                    $trigger,
                    array_merge(is_array($payload['data'] ?? null) ? $payload['data'] : [], [
                        'event' => $eventName,
                        'instance_uuid' => $instance->uuid,
                    ]),
                    'openwa:'.$idempotencyKey,
                );
            }
        }
        $event->update(['processed_at' => now()]);

        return response()->json(['accepted' => true]);
    }

    private function apply(WhatsAppInstance $instance, string $event, array $data, TrialAbuseService $abuse, OpenWaClient $openwa): void
    {
        if ($event === 'session.status') {
            $status = match ($data['status'] ?? null) {
                'ready' => 'connected',
                'qr_ready', 'initializing', 'authenticating', 'created' => 'qr_pending',
                'failed' => 'failed',
                default => 'disconnected',
            };
            $instance->update(['status' => $status, 'last_seen_at' => now(), 'last_error' => $data['reason'] ?? null]);
        } elseif ($event === 'session.qr') {
            $instance->update(['status' => 'qr_pending', 'engine_payload' => array_merge($instance->engine_payload ?? [], ['qr_code' => $data['qrCode'] ?? $data['qr'] ?? null])]);
        } elseif ($event === 'session.authenticated') {
            $phone = $data['phone'] ?? $instance->phone_number;
            try {
                if ($phone) {
                    $abuse->claimTrialIdentifier($instance->workspace()->with('subscription')->firstOrFail(), 'whatsapp_phone', preg_replace('/\D+/', '', $phone));
                }
                $instance->update(['status' => 'connected', 'phone_number' => $phone, 'connected_at' => now(), 'last_seen_at' => now(), 'last_error' => null]);
            } catch (\Throwable $exception) {
                $instance->update(['status' => 'failed', 'last_error' => $exception->getMessage()]);
                try {
                    $openwa->stopSession($instance->openwa_session_id);
                } catch (\Throwable $stopException) {
                    report($stopException);
                }
            }
        } elseif ($event === 'session.disconnected') {
            $instance->update(['status' => 'disconnected', 'last_error' => $data['reason'] ?? null]);
        } elseif ($event === 'message.received') {
            $providerId = $data['id'] ?? $data['messageId'] ?? null;
            if ($providerId && MessageEvent::query()->where('provider_message_id', $providerId)->exists()) {
                return;
            }
            $instance->workspace->messageEvents()->create([
                'whatsapp_instance_id' => $instance->id,
                'direction' => 'inbound',
                'status' => 'received',
                'from' => $data['from'] ?? null,
                'to' => $data['to'] ?? $instance->phone_number,
                'provider_message_id' => $providerId,
                'payload' => $data,
                'processed_at' => now(),
            ]);
        } elseif (in_array($event, ['message.ack', 'message.failed'], true)) {
            $providerId = $data['id'] ?? $data['messageId'] ?? null;
            if (! $providerId) {
                return;
            }
            $message = MessageEvent::query()->where('provider_message_id', $providerId)->first();
            if ($message) {
                $message->update([
                    'status' => $event === 'message.failed' ? 'failed' : ($data['status'] ?? 'delivered'),
                    'error' => $data['error'] ?? null,
                    'processed_at' => now(),
                ]);
            }
        }
    }
}
