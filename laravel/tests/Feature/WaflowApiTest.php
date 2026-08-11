<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\User;
use App\Services\WorkspaceContext;
use App\Services\WorkspaceWebhookDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class WaflowApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_registration_creates_a_seven_day_trial_and_returns_one_key(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Editora Exemplo',
            'email' => 'owner@gmail.com',
            'password' => 'Strong-password-123',
            'password_confirmation' => 'Strong-password-123',
            'workspace_name' => 'Portal Exemplo',
            'site_url' => 'https://noticias.example.com',
            'device_fingerprint' => 'browser-device-fingerprint-001',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.email_verification_required', true)
            ->assertJsonStructure(['data' => ['api_key', 'trial_ends_at', 'portal_verification_token']]);
        $this->assertDatabaseCount('api_keys', 1);
        $this->assertDatabaseHas('subscriptions', ['status' => 'trialing']);
        $this->assertTrue(now()->addDays(7)->diffInMinutes(now()->parse($response->json('data.trial_ends_at'))) < 2);

        $this->withToken($response->json('data.api_key'))->getJson('/api/v1/workspace')
            ->assertForbidden()->assertJsonPath('code', 'email_not_verified');
    }

    public function test_trial_cannot_be_recreated_for_the_same_portal_domain(): void
    {
        Notification::fake();
        $base = [
            'password' => 'Strong-password-123',
            'password_confirmation' => 'Strong-password-123',
            'site_url' => 'https://portal.example.com',
        ];

        $this->postJson('/api/v1/auth/register', $base + ['name' => 'One', 'email' => 'one@gmail.com'])->assertCreated();
        $this->postJson('/api/v1/auth/register', $base + ['name' => 'Two', 'email' => 'two@gmail.com'])
            ->assertUnprocessable()->assertJsonValidationErrors('site_url');
    }

    public function test_expired_trial_is_rejected_before_controller_execution(): void
    {
        [$user, $workspace, $token] = $this->workspaceWithKey();
        $workspace->subscription()->update(['trial_ends_at' => now()->subMinute()]);

        $this->withToken($token)->getJson('/api/v1/workspace')
            ->assertStatus(402)->assertJsonPath('code', 'trial_expired');
        $this->assertDatabaseHas('subscriptions', ['workspace_id' => $workspace->id, 'status' => 'expired']);
    }

    public function test_text_message_is_idempotent_and_sent_by_the_queue_job(): void
    {
        Http::fake(['*/api/sessions/*/messages/send-text' => Http::response(['messageId' => 'wamid-123', 'timestamp' => 12345], 201)]);
        [, $workspace, $token] = $this->workspaceWithKey();
        $instance = $workspace->instances()->create([
            'name' => 'Redação',
            'session_name' => 'redacao-test',
            'openwa_session_id' => '22222222-2222-4222-8222-222222222222',
            'status' => 'connected',
        ]);
        $payload = ['instance_uuid' => $instance->uuid, 'to' => '5562999999999', 'type' => 'text', 'text' => 'Notícia publicada'];

        $this->withToken($token)->withHeader('Idempotency-Key', 'post-42-group-1')->postJson('/api/v1/messages', $payload)->assertAccepted();
        $this->withToken($token)->withHeader('Idempotency-Key', 'post-42-group-1')->postJson('/api/v1/messages', $payload)
            ->assertOk()->assertJsonPath('idempotent_replay', true);

        $this->assertDatabaseCount('message_events', 1);
        $this->assertDatabaseHas('message_events', ['provider_message_id' => 'wamid-123', 'status' => 'sent']);
        Http::assertSentCount(1);
    }

    public function test_openwa_webhook_requires_hmac_and_deduplicates_inbound_message(): void
    {
        config()->set('services.openwa.webhook_secret', 'internal-secret');
        [, $workspace] = $this->workspaceWithKey();
        $instance = $workspace->instances()->create([
            'name' => 'Entrada',
            'session_name' => 'entrada-test',
            'openwa_session_id' => '33333333-3333-4333-8333-333333333333',
            'status' => 'connected',
        ]);
        $payload = [
            'event' => 'message.received',
            'sessionId' => $instance->openwa_session_id,
            'idempotencyKey' => 'msg-inbound-1',
            'deliveryId' => 'delivery-1',
            'data' => ['id' => 'wamid-in-1', 'from' => '5562888888888@c.us', 'body' => 'Olá'],
        ];
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $signature = 'sha256='.hash_hmac('sha256', $body, 'internal-secret');

        $this->call('POST', '/api/v1/internal/openwa/events', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_OPENWA_SIGNATURE' => $signature], $body)->assertOk();
        $this->call('POST', '/api/v1/internal/openwa/events', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_OPENWA_SIGNATURE' => $signature], $body)
            ->assertOk()->assertJsonPath('duplicate', true);

        $this->assertDatabaseCount('inbound_events', 1);
        $this->assertDatabaseHas('message_events', ['workspace_id' => $workspace->id, 'provider_message_id' => 'wamid-in-1', 'direction' => 'inbound']);
    }

    public function test_customer_webhook_is_signed_and_delivery_is_recorded(): void
    {
        Http::fake(['https://hooks.example.com/waflow' => Http::response(status: 204)]);
        [, $workspace] = $this->workspaceWithKey();
        $secret = 'customer-webhook-secret';
        $workspace->webhooks()->create([
            'name' => 'WordPress',
            'url' => 'https://hooks.example.com/waflow',
            'events' => ['message.sent'],
            'status' => 'active',
            'signing_secret' => $secret,
        ]);

        app(WorkspaceWebhookDispatcher::class)->dispatch($workspace, 'message.sent', ['message_uuid' => 'message-1']);

        Http::assertSent(function (ClientRequest $request) use ($secret): bool {
            $expected = 'sha256='.hash_hmac('sha256', $request->body(), $secret);

            return $request->url() === 'https://hooks.example.com/waflow'
                && $request->header('X-Waflow-Event')[0] === 'message.sent'
                && hash_equals($expected, $request->header('X-Waflow-Signature')[0]);
        });
        $this->assertDatabaseHas('webhook_deliveries', ['workspace_id' => $workspace->id, 'event' => 'message.sent', 'status' => 'delivered']);
    }

    private function workspaceWithKey(): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $workspace = app(WorkspaceContext::class)->createForUser($user, 'Workspace Teste');
        $issued = ApiKey::issue($workspace, 'Test key');

        return [$user, $workspace, $issued['plain_text_token']];
    }
}
