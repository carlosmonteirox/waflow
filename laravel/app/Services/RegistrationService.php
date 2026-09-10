<?php

namespace App\Services;

use App\Models\ApiKey;
use App\Models\Partner;
use App\Models\Portal;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RegistrationService
{
    public function __construct(
        private readonly WorkspaceContext $workspaces,
        private readonly TrialAbuseService $abuse,
    ) {}

    public function register(array $attributes, Request $request, bool $issueApiKey = true): array
    {
        $signals = $this->abuse->assertRegistrationAllowed(
            $attributes['email'],
            $request,
            $attributes['device_fingerprint'] ?? null,
            $attributes['site_url'] ?? null,
        );

        $result = DB::transaction(function () use ($attributes, $request, $signals, $issueApiKey): array {
            $user = User::query()->create([
                'name' => $attributes['name'],
                'email' => mb_strtolower(trim($attributes['email'])),
                'password' => $attributes['password'],
                'email_domain' => $signals['domain'],
                'registration_ip_hash' => $signals['ipHash'],
                'device_fingerprint_hash' => $signals['fingerprintHash'],
                'trial_started_at' => now(),
            ]);
            $workspace = $this->workspaces->createForUser($user, $attributes['workspace_name'] ?? null);
            $partner = filled($attributes['partner_code'] ?? null)
                ? Partner::query()->where('referral_code', $attributes['partner_code'])->where('status', 'active')->first()
                : null;
            $partner?->workspaces()->syncWithoutDetaching([
                $workspace->id => ['status' => 'active', 'referred_at' => now()],
            ]);
            $issued = $issueApiKey ? ApiKey::issue($workspace, 'Chave inicial', ['*']) : null;
            $portalToken = null;

            if ($signals['siteDomain']) {
                $portalToken = Str::random(48);
                Portal::query()->create([
                    'workspace_id' => $workspace->id,
                    'name' => $attributes['portal_name'] ?? $signals['siteDomain'],
                    'url' => rtrim($attributes['site_url'], '/'),
                    'domain' => $signals['siteDomain'],
                    'status' => 'pending',
                    'verification_token' => hash('sha256', $portalToken),
                ]);
            }

            $this->abuse->recordRegistration($user->id, $workspace->id, $user->email, $signals, [
                'user_agent_hash' => $request->userAgent() ? hash('sha256', $request->userAgent()) : null,
            ]);

            return compact('user', 'workspace', 'issued', 'portalToken', 'partner');
        });

        $result['user']->sendEmailVerificationNotification();

        return $result;
    }
}
