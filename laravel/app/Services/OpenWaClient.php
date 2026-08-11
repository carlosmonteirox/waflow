<?php

namespace App\Services;

use App\Exceptions\OpenWaException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

class OpenWaClient
{
    public function health(): array
    {
        $startedAt = microtime(true);

        try {
            $body = $this->json($this->request(2)->get('/api/health/ready'));
            $ok = data_get($body, 'status') === 'ok';

            return [
                'ok' => $ok,
                'status' => $ok ? 'online' : 'degraded',
                'label' => $ok ? 'Operacional' : 'Com atenção',
                'latency_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'message' => $ok ? null : data_get($body, 'message', 'O OpenWA respondeu com falha.'),
            ];
        } catch (Throwable $exception) {
            report($exception);

            return ['ok' => false, 'status' => 'offline', 'label' => 'Indisponível', 'latency_ms' => null, 'message' => 'Não foi possível alcançar o serviço OpenWA.'];
        }
    }

    public function createSession(string $name): array
    {
        return $this->json($this->call(fn () => $this->request()->post('/api/sessions', [
            'name' => $name,
            'config' => ['autoReconnect' => true],
        ])));
    }

    public function session(string $sessionId): array
    {
        return $this->json($this->call(fn () => $this->request()->get("/api/sessions/{$sessionId}")));
    }

    public function startSession(string $sessionId): array
    {
        return $this->json($this->call(fn () => $this->request()->post("/api/sessions/{$sessionId}/start")));
    }

    public function stopSession(string $sessionId): array
    {
        return $this->json($this->call(fn () => $this->request()->post("/api/sessions/{$sessionId}/stop")));
    }

    public function deleteSession(string $sessionId): void
    {
        $response = $this->call(fn () => $this->request()->delete("/api/sessions/{$sessionId}"));
        if (! $response->successful() && $response->status() !== 404) {
            $this->fail($response);
        }
    }

    public function qrCode(string $sessionId): ?array
    {
        $response = $this->call(fn () => $this->request()->get("/api/sessions/{$sessionId}/qr"));
        if ($response->status() === 400) {
            return null;
        }

        return $this->json($response);
    }

    public function groups(string $sessionId, int $limit = 1000, int $offset = 0): array
    {
        return $this->json($this->call(fn () => $this->request()->get("/api/sessions/{$sessionId}/groups", ['limit' => $limit, 'offset' => $offset])));
    }

    public function channels(string $sessionId): array
    {
        return $this->json($this->call(fn () => $this->request()->get("/api/sessions/{$sessionId}/channels")));
    }

    public function contacts(string $sessionId, int $limit = 1000, int $offset = 0): array
    {
        return $this->json($this->call(fn () => $this->request()->get("/api/sessions/{$sessionId}/contacts", ['limit' => $limit, 'offset' => $offset])));
    }

    public function createInternalWebhook(string $sessionId): array
    {
        return $this->json($this->call(fn () => $this->request()->post("/api/sessions/{$sessionId}/webhooks", [
            'url' => config('services.openwa.webhook_url'),
            'events' => ['*'],
            'secret' => config('services.openwa.webhook_secret'),
            'retryCount' => 5,
        ])));
    }

    public function sendMessage(string $sessionId, string $to, array $payload): array
    {
        $type = $payload['type'] ?? 'text';
        $body = ['chatId' => $this->chatId($to)];

        if ($type === 'text') {
            $path = 'send-text';
            $body['text'] = (string) ($payload['text'] ?? '');
            $body['mentions'] = $payload['mentions'] ?? null;
        } elseif (in_array($type, ['image', 'video', 'audio', 'document', 'sticker'], true)) {
            $path = 'send-'.$type;
            $body = array_merge($body, array_filter([
                'url' => $payload['url'] ?? null,
                'base64' => $payload['base64'] ?? null,
                'mimetype' => $payload['mimetype'] ?? null,
                'filename' => $payload['filename'] ?? null,
                'caption' => $payload['caption'] ?? null,
                'mentions' => $payload['mentions'] ?? null,
                'ptt' => $type === 'audio' ? ($payload['ptt'] ?? null) : null,
            ], fn ($value) => $value !== null));
        } elseif ($type === 'location') {
            $path = 'send-location';
            $body = array_merge($body, array_intersect_key($payload, array_flip(['latitude', 'longitude', 'description', 'address'])));
        } elseif ($type === 'contact') {
            $path = 'send-contact';
            $body['contactName'] = $payload['contact_name'];
            $body['contactNumber'] = $payload['contact_number'];
        } elseif ($type === 'poll') {
            $path = 'send-poll';
            $body['name'] = $payload['name'];
            $body['options'] = $payload['options'];
            $body['allowMultipleAnswers'] = $payload['allow_multiple_answers'] ?? false;
        } else {
            throw new OpenWaException("Tipo de mensagem não suportado: {$type}", 422);
        }

        return $this->json($this->call(fn () => $this->request()->post("/api/sessions/{$sessionId}/messages/{$path}", $body)));
    }

    private function request(?float $timeout = null): PendingRequest
    {
        $request = Http::baseUrl(rtrim((string) config('services.openwa.url'), '/'))
            ->acceptJson()
            ->asJson()
            ->connectTimeout(3)
            ->timeout($timeout ?? (float) config('services.openwa.timeout', 30));

        return filled(config('services.openwa.api_key'))
            ? $request->withHeaders(['X-API-Key' => config('services.openwa.api_key')])
            : $request;
    }

    private function json(Response $response): array
    {
        if (! $response->successful()) {
            $this->fail($response);
        }

        $body = $response->json();

        return is_array($body) ? $body : [];
    }

    private function call(callable $callback): Response
    {
        try {
            return $callback();
        } catch (ConnectionException $exception) {
            throw new OpenWaException('O runtime do WhatsApp está indisponível.', 502, [], $exception);
        }
    }

    private function fail(Response $response): never
    {
        $body = $response->json();
        $message = data_get($body, 'message', 'Falha na comunicação com o runtime do WhatsApp.');
        if (is_array($message)) {
            $message = implode(' ', $message);
        }

        throw new OpenWaException((string) $message, $response->status(), is_array($body) ? $body : []);
    }

    private function chatId(string $recipient): string
    {
        if (str_contains($recipient, '@')) {
            return $recipient;
        }

        return preg_replace('/\D+/', '', $recipient).'@c.us';
    }
}
