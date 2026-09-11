<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\User;
use App\Rules\PublicWebhookUrl;
use App\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_restricted_api_key_cannot_delegate_more_privileges_than_it_has(): void
    {
        [, $workspace] = $this->workspace();
        $workspace->subscription()->update(['status' => 'active']);
        $workspace->subscription->plan()->update(['api_key_limit' => 5]);
        $issuer = ApiKey::issue($workspace, 'Restricted issuer', ['api-keys:write', 'workspace:read']);

        $this->withToken($issuer['plain_text_token'])->postJson('/api/v1/api-keys', [
            'name' => 'Escalated key',
            'abilities' => ['*'],
        ])->assertUnprocessable()->assertJsonValidationErrors('abilities');

        $created = $this->withToken($issuer['plain_text_token'])->postJson('/api/v1/api-keys', [
            'name' => 'Delegated key',
            'abilities' => ['workspace:read'],
        ])->assertCreated();
        $this->assertSame(['workspace:read'], $created->json('data.abilities'));
    }

    public function test_invalid_api_credentials_are_rate_limited_before_repeated_database_lookups(): void
    {
        config()->set('waflow.api.authentication_attempts_per_ip', 2);

        foreach (['invalid-one', 'invalid-two'] as $index => $token) {
            $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.42'])
                ->withHeader('X-Forwarded-For', '203.0.113.'.($index + 1))
                ->withToken($token)->getJson('/api/v1/workspace')->assertUnauthorized();
        }

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.42'])
            ->withHeader('X-Forwarded-For', '203.0.113.99')
            ->withToken('invalid-three')->getJson('/api/v1/workspace')->assertTooManyRequests();
    }

    public function test_password_recovery_has_the_same_response_for_known_and_unknown_accounts(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $known = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.51'])
            ->post('/forgot-password', ['email' => $user->email]);
        $unknown = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.52'])
            ->post('/forgot-password', ['email' => 'unknown@example.com']);

        $known->assertRedirect('/forgot-password')->assertSessionHas('status');
        $unknown->assertRedirect('/forgot-password')->assertSessionHas('status', $known->getSession()->get('status'));
    }

    public function test_registration_rejects_a_weak_password(): void
    {
        $this->post('/register', [
            'name' => 'Weak Password',
            'email' => 'weak-password@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors('password');
    }

    public function test_private_ipv6_userinfo_and_nonstandard_webhook_ports_are_rejected(): void
    {
        foreach (['http://[::1]/hook', 'https://user:pass@example.com/hook', 'https://example.com:8443/hook'] as $url) {
            $validator = Validator::make(['url' => $url], ['url' => [new PublicWebhookUrl]]);
            $this->assertTrue($validator->fails(), $url.' should have been rejected.');
        }

        $this->assertFalse(Validator::make(['url' => 'https://hooks.example.com/waflow'], ['url' => [new PublicWebhookUrl]])->fails());
    }

    public function test_base64_media_and_internal_webhook_bodies_are_bounded(): void
    {
        config()->set('waflow.api.max_base64_bytes', 3);
        [, $workspace] = $this->workspace();
        $issued = ApiKey::issue($workspace, 'Media key');

        $this->withToken($issued['plain_text_token'])->postJson('/api/v1/messages', [
            'instance_uuid' => '11111111-1111-4111-8111-111111111111',
            'to' => '5562999999999',
            'type' => 'image',
            'base64' => 'QUJDRA==',
        ])->assertUnprocessable()->assertJsonValidationErrors('base64');

        config()->set('waflow.webhooks.max_payload_bytes', 8);
        $this->call(
            'POST',
            '/api/v1/internal/openwa/events',
            server: ['CONTENT_TYPE' => 'application/json', 'CONTENT_LENGTH' => 9],
            content: '{"x":1234}',
        )->assertStatus(413)->assertJsonPath('code', 'payload_too_large');
    }

    public function test_customer_webhook_secret_is_encrypted_at_rest(): void
    {
        [, $workspace] = $this->workspace();
        $webhook = $workspace->webhooks()->create([
            'name' => 'Encrypted secret',
            'url' => 'https://hooks.example.com/waflow',
            'events' => ['message.sent'],
            'status' => 'active',
            'signing_secret' => 'plain-customer-secret',
        ]);

        $stored = DB::table('webhooks')->where('id', $webhook->id)->value('signing_secret');
        $this->assertNotSame('plain-customer-secret', $stored);
        $this->assertSame('plain-customer-secret', $webhook->fresh()->signing_secret);
    }

    private function workspace(): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $workspace = app(WorkspaceContext::class)->createForUser($user, 'Secure Workspace');

        return [$user, $workspace];
    }
}
