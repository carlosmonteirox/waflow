<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\User;
use App\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ApiResourceCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_instance_lifecycle_endpoints(): void
    {
        Http::fake(function (ClientRequest $request) {
            $path = parse_url($request->url(), PHP_URL_PATH);

            return match (true) {
                $request->method() === 'POST' && $path === '/api/sessions' => Http::response([
                    'id' => '55555555-5555-4555-8555-555555555555', 'status' => 'created',
                ], 201),
                str_ends_with($path, '/start') => Http::response(['status' => 'ready', 'phone' => '5562999999999']),
                str_ends_with($path, '/stop') => Http::response(['status' => 'stopped']),
                str_ends_with($path, '/qr') => Http::response(['qrCode' => 'data:image/png;base64,e2U=']),
                str_ends_with($path, '/groups') => Http::response([['id' => 'group@g.us', 'name' => 'Redação']]),
                str_ends_with($path, '/channels') => Http::response([['id' => 'channel@newsletter', 'name' => 'Canal']]),
                str_ends_with($path, '/contacts') => Http::response([['id' => 'contact@c.us', 'name' => 'Contato']]),
                $request->method() === 'GET' && str_contains($path, '/api/sessions/') => Http::response(['status' => 'ready', 'phone' => '5562999999999']),
                $request->method() === 'DELETE' && str_contains($path, '/api/sessions/') => Http::response(status: 204),
                default => Http::response(['id' => 'internal-webhook'], 201),
            };
        });
        [, , $token] = $this->workspaceWithKey();

        $created = $this->withToken($token)->postJson('/api/v1/instances', ['name' => 'Atendimento'])->assertCreated();
        $uuid = $created->json('data.uuid');
        $this->withToken($token)->getJson('/api/v1/instances?status=qr_pending&search=Atendimento')->assertOk()->assertJsonPath('meta.pagination.total', 1);
        $this->withToken($token)->getJson('/api/v1/instances/'.$uuid)->assertOk();
        $this->withToken($token)->patchJson('/api/v1/instances/'.$uuid, ['name' => 'Redação'])->assertOk()->assertJsonPath('data.name', 'Redação');
        $this->withToken($token)->postJson('/api/v1/instances/'.$uuid.'/connect')->assertOk()->assertJsonPath('data.status', 'connected');
        $this->withToken($token)->getJson('/api/v1/instances/'.$uuid.'/status')->assertOk()->assertJsonPath('data.status', 'connected');
        $this->withToken($token)->getJson('/api/v1/instances/'.$uuid.'/qr')->assertOk()->assertJsonPath('data.qrCode', 'data:image/png;base64,e2U=');
        $this->withToken($token)->getJson('/api/v1/instances/'.$uuid.'/groups')->assertOk()->assertJsonPath('data.0.id', 'group@g.us');
        $this->withToken($token)->getJson('/api/v1/instances/'.$uuid.'/channels')->assertOk()->assertJsonPath('data.0.id', 'channel@newsletter');
        $this->withToken($token)->getJson('/api/v1/instances/'.$uuid.'/remote-contacts')->assertOk()->assertJsonPath('data.0.id', 'contact@c.us');
        $this->withToken($token)->postJson('/api/v1/instances/'.$uuid.'/disconnect')->assertOk()->assertJsonPath('data.status', 'disconnected');
        $this->withToken($token)->deleteJson('/api/v1/instances/'.$uuid)->assertNoContent();
    }

    public function test_crud_endpoints_for_contacts_portals_flows_webhooks_and_api_keys(): void
    {
        Http::fake(['https://hooks.example.com/*' => Http::response(status: 204)]);
        [, $workspace, $token] = $this->workspaceWithKey();
        $workspace->subscription()->update(['status' => 'active']);

        $contact = $this->withToken($token)->postJson('/api/v1/contacts', ['name' => 'Ana', 'phone_number' => '5562111111111'])->assertCreated();
        $contactUuid = $contact->json('data.uuid');
        $this->withToken($token)->getJson('/api/v1/contacts/'.$contactUuid)->assertOk();
        $this->withToken($token)->patchJson('/api/v1/contacts/'.$contactUuid, ['name' => 'Ana Maria'])->assertOk()->assertJsonPath('data.name', 'Ana Maria');
        $this->withToken($token)->getJson('/api/v1/contacts?has_wa_id=0&search=Ana')->assertOk()->assertJsonPath('meta.pagination.total', 1);
        $this->withToken($token)->deleteJson('/api/v1/contacts/'.$contactUuid)->assertNoContent();

        $portal = $this->withToken($token)->postJson('/api/v1/portals', ['name' => 'Portal', 'url' => 'https://portal-resource.example.com'])->assertCreated();
        $portalUuid = $portal->json('data.uuid');
        $this->withToken($token)->getJson('/api/v1/portals/'.$portalUuid)->assertOk();
        $this->withToken($token)->patchJson('/api/v1/portals/'.$portalUuid, ['name' => 'Portal Atualizado'])->assertOk()->assertJsonPath('data.name', 'Portal Atualizado');
        $this->withToken($token)->postJson('/api/v1/portals/'.$portalUuid.'/verify', ['verification_token' => $portal->json('verification_token')])->assertOk()->assertJsonPath('data.status', 'verified');
        $this->withToken($token)->getJson('/api/v1/portals?status=verified')->assertOk()->assertJsonPath('meta.pagination.total', 1);
        $this->withToken($token)->deleteJson('/api/v1/portals/'.$portalUuid)->assertNoContent();

        $flow = $this->withToken($token)->postJson('/api/v1/flows', ['name' => 'Fluxo CRUD', 'trigger_type' => 'message_received', 'steps' => []])->assertCreated();
        $flowUuid = $flow->json('data.uuid');
        $this->withToken($token)->getJson('/api/v1/flows/'.$flowUuid)->assertOk();
        $this->withToken($token)->patchJson('/api/v1/flows/'.$flowUuid, ['name' => 'Fluxo Atualizado', 'status' => 'active'])->assertOk();
        $this->withToken($token)->getJson('/api/v1/flows?status=active&trigger_type=message_received')->assertOk()->assertJsonPath('meta.pagination.total', 1);
        $this->withToken($token)->postJson('/api/v1/flows/'.$flowUuid.'/test', ['payload' => []])->assertStatus(202);
        $this->withToken($token)->getJson('/api/v1/flows/'.$flowUuid.'/executions?status=completed')->assertOk()->assertJsonPath('meta.pagination.total', 1);
        $this->withToken($token)->deleteJson('/api/v1/flows/'.$flowUuid)->assertNoContent();

        $webhook = $this->withToken($token)->postJson('/api/v1/webhooks', [
            'name' => 'Webhook CRUD', 'url' => 'https://hooks.example.com/waflow', 'events' => ['message.sent'],
        ])->assertCreated();
        $webhookUuid = $webhook->json('data.uuid');
        $this->withToken($token)->getJson('/api/v1/webhooks/'.$webhookUuid)->assertOk();
        $this->withToken($token)->patchJson('/api/v1/webhooks/'.$webhookUuid, ['status' => 'paused'])->assertOk()->assertJsonPath('data.status', 'paused');
        $this->withToken($token)->getJson('/api/v1/webhooks?status=paused')->assertOk()->assertJsonPath('meta.pagination.total', 1);
        $this->withToken($token)->postJson('/api/v1/webhooks/'.$webhookUuid.'/test')->assertAccepted();
        $this->withToken($token)->deleteJson('/api/v1/webhooks/'.$webhookUuid)->assertNoContent();

        $newKey = $this->withToken($token)->postJson('/api/v1/api-keys', ['name' => 'Read only', 'abilities' => ['workspace:read']])->assertCreated();
        $this->withToken($token)->getJson('/api/v1/api-keys?search=Read')->assertOk()->assertJsonPath('meta.pagination.total', 1);
        $this->withToken($token)->deleteJson('/api/v1/api-keys/'.$newKey->json('data.id'))->assertNoContent();
    }

    public function test_workspace_billing_and_message_read_endpoints(): void
    {
        [, $workspace, $token] = $this->workspaceWithKey();
        $this->withToken($token)->getJson('/api/v1/workspace')->assertOk()->assertJsonPath('data.id', $workspace->id);
        $this->withToken($token)->patchJson('/api/v1/workspace', ['name' => 'Workspace Atualizado', 'timezone' => 'America/Recife'])
            ->assertOk()->assertJsonPath('data.name', 'Workspace Atualizado');
        $this->withToken($token)->getJson('/api/v1/billing/usage')->assertOk()->assertJsonPath('data.usage.portals.limit', 1);

        $checkout = $workspace->checkoutSessions()->create([
            'plan_id' => $workspace->subscription->plan_id,
            'created_by' => $workspace->owner_id,
            'payment_method' => 'pix',
            'status' => 'pending',
            'amount_cents' => 9900,
            'currency' => 'BRL',
        ]);
        $invoice = $workspace->invoices()->create([
            'subscription_id' => $workspace->subscription->id,
            'number' => 'INV-TEST-001',
            'status' => 'open',
            'amount_cents' => 9900,
            'currency' => 'BRL',
            'payment_method' => 'pix',
        ]);
        $workspace->payments()->create([
            'invoice_id' => $invoice->id,
            'billing_checkout_session_id' => $checkout->id,
            'payment_method' => 'pix',
            'status' => 'pending',
            'amount_cents' => 9900,
            'currency' => 'BRL',
        ]);

        $this->withToken($token)->getJson('/api/v1/billing/checkouts?status=pending&payment_method=pix')->assertOk()->assertJsonPath('meta.pagination.total', 1);
        $this->withToken($token)->getJson('/api/v1/billing/checkouts/'.$checkout->uuid)->assertOk();
        $this->withToken($token)->postJson('/api/v1/billing/checkouts/'.$checkout->uuid.'/cancel')->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->withToken($token)->getJson('/api/v1/billing/invoices?status=open')->assertOk()->assertJsonPath('meta.pagination.total', 1);
        $this->withToken($token)->getJson('/api/v1/billing/invoices/'.$invoice->uuid)->assertOk()->assertJsonPath('data.number', 'INV-TEST-001');
        $this->withToken($token)->getJson('/api/v1/billing/payments?status=pending')->assertOk()->assertJsonPath('meta.pagination.total', 1);

        $message = $workspace->messageEvents()->create([
            'direction' => 'inbound', 'status' => 'received', 'from' => '5562999999999', 'payload' => ['text' => 'Olá'],
        ]);
        $this->withToken($token)->getJson('/api/v1/messages?direction=inbound&search=5562')->assertOk()->assertJsonPath('meta.pagination.total', 1);
        $this->withToken($token)->getJson('/api/v1/messages/'.$message->uuid)->assertOk()->assertJsonPath('data.uuid', $message->uuid);
    }

    private function workspaceWithKey(): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $workspace = app(WorkspaceContext::class)->createForUser($user, 'Workspace API');
        $issued = ApiKey::issue($workspace, 'Coverage key');

        return [$user, $workspace, $issued['plain_text_token']];
    }
}
