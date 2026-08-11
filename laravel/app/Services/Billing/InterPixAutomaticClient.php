<?php

namespace App\Services\Billing;

use App\Exceptions\InterApiException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class InterPixAutomaticClient
{
    public function configured(): bool
    {
        return filled(config('billing.inter.client_id'))
            && filled(config('billing.inter.client_secret'))
            && is_readable((string) config('billing.inter.certificate_path'))
            && is_readable((string) config('billing.inter.private_key_path'));
    }

    /** @return array<string, mixed> */
    public function get(string $path, array $query = []): array
    {
        return $this->request('GET', $path, query: $query);
    }

    /** @return array<string, mixed> */
    public function post(string $path, ?array $payload = null): array
    {
        return $this->request('POST', $path, $payload);
    }

    /** @return array<string, mixed> */
    public function put(string $path, array $payload): array
    {
        return $this->request('PUT', $path, $payload);
    }

    /** @return array<string, mixed> */
    public function patch(string $path, array $payload): array
    {
        return $this->request('PATCH', $path, $payload);
    }

    public function webhookBaseUrl(): string
    {
        $configured = trim((string) config('billing.inter.webhook_url'));
        if ($configured !== '') {
            return rtrim($configured, '/');
        }

        $secret = trim((string) config('billing.inter.webhook_secret'));
        if ($secret === '') {
            throw new InterApiException('Defina INTER_WEBHOOK_SECRET antes de cadastrar os webhooks do Banco Inter.', 503, 'inter_not_configured');
        }

        return url('/api/v1/internal/billing/inter/'.$secret);
    }

    /** @return array<string, mixed> */
    private function request(string $method, string $path, ?array $payload = null, array $query = []): array
    {
        $this->assertConfigured();
        $request = $this->authenticatedRequest();
        $options = [];
        if ($payload !== null) {
            $options['json'] = $payload;
        }
        if ($query !== []) {
            $options['query'] = $query;
        }

        $response = $request->send($method, ltrim($path, '/'), $options);
        if ($response->status() === 401) {
            Cache::forget($this->tokenCacheKey());
            $response = $this->authenticatedRequest()->send($method, ltrim($path, '/'), $options);
        }

        return $this->decode($response);
    }

    private function authenticatedRequest(): PendingRequest
    {
        $request = Http::withOptions($this->certificateOptions())
            ->baseUrl(rtrim((string) config('billing.inter.base_url'), '/').'/')
            ->acceptJson()
            ->asJson()
            ->withToken($this->accessToken())
            ->timeout(30)
            ->connectTimeout(10)
            ->retry(2, 250, throw: false);

        if (filled(config('billing.inter.account'))) {
            $request->withHeaders(['x-conta-corrente' => (string) config('billing.inter.account')]);
        }

        return $request;
    }

    private function accessToken(): string
    {
        $this->assertConfigured();
        $cacheKey = $this->tokenCacheKey();
        if ($token = Cache::get($cacheKey)) {
            return (string) $token;
        }

        return Cache::lock($cacheKey.':lock', 15)->block(10, function () use ($cacheKey): string {
            if ($token = Cache::get($cacheKey)) {
                return (string) $token;
            }

            $response = Http::withOptions($this->certificateOptions())
                ->asForm()
                ->acceptJson()
                ->timeout(30)
                ->post((string) config('billing.inter.token_url'), [
                    'client_id' => config('billing.inter.client_id'),
                    'client_secret' => config('billing.inter.client_secret'),
                    'grant_type' => 'client_credentials',
                    'scope' => config('billing.inter.scopes'),
                ]);
            $body = $this->decode($response);
            $token = (string) ($body['access_token'] ?? '');
            if ($token === '') {
                throw new InterApiException('O Banco Inter não retornou um token OAuth válido.', 502, 'inter_oauth_invalid_response');
            }

            $ttl = max(60, ((int) ($body['expires_in'] ?? 3600)) - 120);
            Cache::put($cacheKey, $token, now()->addSeconds($ttl));

            return $token;
        });
    }

    /** @return array<string, string|array<int, string>> */
    private function certificateOptions(): array
    {
        $cert = (string) config('billing.inter.certificate_path');
        $key = (string) config('billing.inter.private_key_path');
        $certPassphrase = (string) config('billing.inter.certificate_passphrase');
        $keyPassphrase = (string) config('billing.inter.private_key_passphrase');

        return [
            'cert' => $certPassphrase !== '' ? [$cert, $certPassphrase] : $cert,
            'ssl_key' => $keyPassphrase !== '' ? [$key, $keyPassphrase] : $key,
        ];
    }

    /** @return array<string, mixed> */
    private function decode(Response $response): array
    {
        $body = $response->json();
        if (! $response->successful()) {
            $message = is_array($body)
                ? (string) ($body['detail'] ?? $body['title'] ?? 'O Banco Inter recusou a operação.')
                : 'O Banco Inter não respondeu à operação.';
            $code = is_array($body) ? (string) ($body['type'] ?? $body['correlationId'] ?? 'inter_api_error') : 'inter_api_error';

            throw new InterApiException($message, $response->status() >= 500 ? 502 : 422, $code);
        }

        return is_array($body) ? $body : [];
    }

    private function assertConfigured(): void
    {
        if (! $this->configured()) {
            throw new InterApiException(
                'A integração Banco Inter requer Client ID, Client Secret, certificado e chave privada mTLS.',
                503,
                'inter_not_configured',
            );
        }
    }

    private function tokenCacheKey(): string
    {
        return 'billing:inter:oauth:'.hash('sha256', (string) config('billing.inter.client_id').'|'.config('billing.inter.token_url'));
    }
}
