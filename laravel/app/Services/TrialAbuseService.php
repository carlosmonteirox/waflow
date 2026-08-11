<?php

namespace App\Services;

use App\Models\TrialIdentifier;
use App\Models\TrialRegistration;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TrialAbuseService
{
    public function assertRegistrationAllowed(string $email, Request $request, ?string $fingerprint, ?string $siteUrl): array
    {
        $email = mb_strtolower(trim($email));
        $domain = (string) str($email)->afterLast('@');
        $ipHash = $this->hash($request->ip() ?: 'unknown');
        $fingerprintHash = filled($fingerprint) ? $this->hash((string) $fingerprint) : null;
        $siteDomain = $this->domainFromUrl($siteUrl);

        if (in_array($domain, config('waflow.registration.blocked_email_domains', []), true)) {
            throw ValidationException::withMessages(['email' => 'Use um endereço de e-mail permanente.']);
        }

        if (TrialRegistration::query()->where('email_hash', $this->hash($email))->exists()) {
            throw ValidationException::withMessages(['email' => 'Este e-mail já utilizou o período de avaliação.']);
        }

        $ipCount = TrialRegistration::query()->where('ip_hash', $ipHash)->where('created_at', '>=', now()->subDays(30))->count();
        if ($ipCount >= config('waflow.registration.max_per_ip_30_days', 2)) {
            throw ValidationException::withMessages(['email' => 'O limite de avaliações para esta rede foi atingido.']);
        }

        if ($fingerprintHash && TrialRegistration::query()->where('fingerprint_hash', $fingerprintHash)->exists()) {
            throw ValidationException::withMessages(['device_fingerprint' => 'Este dispositivo já utilizou o período de avaliação.']);
        }

        if ($siteDomain && TrialRegistration::query()->where('site_domain', $siteDomain)->exists()) {
            throw ValidationException::withMessages(['site_url' => 'Este domínio já está vinculado a uma avaliação.']);
        }

        $isPublicProvider = in_array($domain, config('waflow.registration.public_email_domains', []), true);
        if (! $isPublicProvider && TrialRegistration::query()->where('email_domain', $domain)->exists()) {
            throw ValidationException::withMessages(['email' => 'A organização deste e-mail já utilizou uma avaliação.']);
        }

        return compact('domain', 'ipHash', 'fingerprintHash', 'siteDomain');
    }

    public function recordRegistration(int $userId, int $workspaceId, string $email, array $signals, array $metadata = []): void
    {
        TrialRegistration::query()->create([
            'user_id' => $userId,
            'workspace_id' => $workspaceId,
            'email_hash' => $this->hash(mb_strtolower(trim($email))),
            'email_domain' => $signals['domain'],
            'ip_hash' => $signals['ipHash'],
            'fingerprint_hash' => $signals['fingerprintHash'],
            'site_domain' => $signals['siteDomain'],
            'metadata' => $metadata,
        ]);
    }

    public function claimTrialIdentifier(Workspace $workspace, string $type, string $value): void
    {
        if ($workspace->subscription?->status !== 'trialing') {
            return;
        }

        $hash = $this->hash(mb_strtolower(trim($value)));
        $inserted = TrialIdentifier::query()->insertOrIgnore([
            'workspace_id' => $workspace->id,
            'type' => $type,
            'value_hash' => $hash,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        if ($inserted === 0) {
            $belongsToWorkspace = TrialIdentifier::query()
                ->where('type', $type)
                ->where('value_hash', $hash)
                ->where('workspace_id', $workspace->id)
                ->exists();
            if (! $belongsToWorkspace) {
                throw ValidationException::withMessages([$type => 'Este identificador já utilizou uma avaliação em outra conta.']);
            }
        }
    }

    public function domainFromUrl(?string $url): ?string
    {
        if (! filled($url)) {
            return null;
        }
        $host = parse_url((string) $url, PHP_URL_HOST);

        return $host ? mb_strtolower(rtrim((string) $host, '.')) : null;
    }

    private function hash(string $value): string
    {
        return hash_hmac('sha256', $value, (string) config('app.key'));
    }
}
