<?php

namespace App\Services;

use App\Exceptions\OpenWaException;
use App\Models\WhatsAppInstance;
use Illuminate\Support\Arr;

class InstanceManager
{
    public function __construct(
        private readonly OpenWaClient $openwa,
        private readonly TrialAbuseService $abuse,
    ) {}

    public function provision(WhatsAppInstance $instance): WhatsAppInstance
    {
        if ($instance->openwa_session_id) {
            return $instance;
        }

        try {
            $session = $this->openwa->createSession($instance->session_name);
            $instance->update([
                'openwa_session_id' => $session['id'],
                'status' => $this->mapStatus($session['status'] ?? 'created'),
                'engine_payload' => $session,
                'last_error' => null,
            ]);
            if (filled(config('services.openwa.webhook_url')) && filled(config('services.openwa.webhook_secret'))) {
                $this->openwa->createInternalWebhook($session['id']);
            }
        } catch (\Throwable $exception) {
            $instance->update(['status' => 'failed', 'last_error' => $exception->getMessage()]);
            throw $exception;
        }

        return $instance->fresh();
    }

    public function connect(WhatsAppInstance $instance): array
    {
        $instance = $this->provision($instance);
        try {
            $session = $this->openwa->startSession($instance->openwa_session_id);
        } catch (OpenWaException $exception) {
            if ($exception->status !== 400 || ! str_contains(mb_strtolower($exception->getMessage()), 'already started')) {
                $instance->update(['status' => 'failed', 'last_error' => $exception->getMessage()]);
                throw $exception;
            }
            $session = $this->openwa->session($instance->openwa_session_id);
        }

        $instance = $this->sync($instance, $session);

        $qrCode = $this->openwa->qrCode($instance->openwa_session_id)['qrCode'] ?? null;
        if ($qrCode) {
            $instance->update(['engine_payload' => array_merge($instance->engine_payload ?? [], ['qr_code' => $qrCode])]);
        }

        return ['instance' => $instance->fresh(), 'qr_code' => $qrCode];
    }

    public function refresh(WhatsAppInstance $instance): WhatsAppInstance
    {
        if (! $instance->openwa_session_id) {
            return $instance;
        }

        return $this->sync($instance, $this->openwa->session($instance->openwa_session_id));
    }

    public function disconnect(WhatsAppInstance $instance): WhatsAppInstance
    {
        if ($instance->openwa_session_id) {
            $this->openwa->stopSession($instance->openwa_session_id);
        }
        $instance->update(['status' => 'disconnected', 'last_error' => null]);

        return $instance->fresh();
    }

    public function delete(WhatsAppInstance $instance): void
    {
        if ($instance->openwa_session_id) {
            $this->openwa->deleteSession($instance->openwa_session_id);
        }
        $instance->delete();
    }

    private function sync(WhatsAppInstance $instance, array $session): WhatsAppInstance
    {
        $status = $this->mapStatus($session['status'] ?? 'disconnected');
        $phone = $session['phone'] ?? $instance->phone_number;

        if ($status === 'connected' && filled($phone)) {
            try {
                $this->abuse->claimTrialIdentifier($instance->workspace()->with('subscription')->firstOrFail(), 'whatsapp_phone', preg_replace('/\D+/', '', $phone));
            } catch (\Throwable $exception) {
                $this->openwa->stopSession($instance->openwa_session_id);
                $instance->update(['status' => 'failed', 'last_error' => $exception->getMessage()]);
                throw $exception;
            }
        }

        $instance->update([
            'status' => $status,
            'phone_number' => $phone,
            'connected_at' => $status === 'connected' ? ($instance->connected_at ?? now()) : $instance->connected_at,
            'last_seen_at' => $status === 'connected' ? now() : $instance->last_seen_at,
            'last_error' => $session['lastError'] ?? null,
            'engine_payload' => Arr::except($session, ['qrCode']),
        ]);

        return $instance->fresh();
    }

    private function mapStatus(string $status): string
    {
        return match ($status) {
            'ready' => 'connected',
            'created', 'initializing', 'qr_ready', 'authenticating' => 'qr_pending',
            'failed' => 'failed',
            default => 'disconnected',
        };
    }
}
