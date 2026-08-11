<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\User;
use App\Services\Billing\InterRenewalService;
use App\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InterBillingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set([
            'billing.driver' => 'inter',
            'billing.available_payment_methods' => ['pix'],
            'billing.inter.base_url' => 'https://inter.test/pix/v2',
            'billing.inter.token_url' => 'https://inter.test/oauth/v2/token',
            'billing.inter.client_id' => 'client-id',
            'billing.inter.client_secret' => 'client-secret',
            'billing.inter.certificate_path' => __FILE__,
            'billing.inter.private_key_path' => __FILE__,
            'billing.inter.account' => '123456',
            'billing.inter.webhook_secret' => 'inter-webhook-secret',
            'billing.inter.receiver' => [
                'name' => 'Waflow Tecnologia Ltda',
                'cnpj' => '12.345.678/0001-90',
                'agency' => '0001',
                'account' => '1234567',
                'account_type' => 'CORRENTE',
            ],
        ]);
        Cache::flush();
    }

    public function test_pix_automatic_checkout_authorization_renewal_and_payment_webhooks(): void
    {
        Http::fake(function (Request $request) {
            $url = $request->url();

            return match (true) {
                $url === 'https://inter.test/oauth/v2/token' => Http::response(['access_token' => 'oauth-token', 'expires_in' => 3600]),
                $url === 'https://inter.test/pix/v2/locrec' => Http::response(['id' => 12069, 'location' => 'pix.inter.test/rec/abc'], 201),
                $url === 'https://inter.test/pix/v2/rec' => Http::response(['idRec' => 'RR1026652320240821lab77511abf', 'status' => 'CRIADA'], 201),
                $url === 'https://inter.test/pix/v2/rec/RR1026652320240821lab77511abf' => Http::response([
                    'idRec' => 'RR1026652320240821lab77511abf',
                    'status' => 'CRIADA',
                    'pixCopiaECola' => '00020101021226890014br.gov.bcb.pix',
                    'loc' => ['id' => 12069, 'location' => 'pix.inter.test/rec/abc'],
                ]),
                str_contains($url, '/pix/v2/cobr/') => Http::response([
                    'idRec' => 'RR1026652320240821lab77511abf',
                    'txid' => basename($url),
                    'status' => 'CRIADA',
                ], 201),
                default => Http::response(['detail' => 'Unexpected test request: '.$url], 500),
            };
        });

        [, $workspace, $token] = $this->workspaceWithKey();
        $checkoutResponse = $this->withToken($token)->postJson('/api/v1/billing/checkouts', [
            'plan_slug' => 'growth',
            'payment_method' => 'pix',
            'payer_name' => 'Cliente Teste',
            'payer_tax_id' => '451.646.324-81',
        ])->assertCreated()
            ->assertJsonPath('data.provider', 'inter')
            ->assertJsonPath('data.provider_reference', 'RR1026652320240821lab77511abf')
            ->assertJsonPath('data.provider_payload.integration_status', 'awaiting_authorization')
            ->assertJsonPath('data.provider_payload.pix_copy_paste', '00020101021226890014br.gov.bcb.pix');

        $checkout = $workspace->checkoutSessions()->where('uuid', $checkoutResponse->json('data.uuid'))->firstOrFail();
        $this->assertSame('2481', $checkout->provider_payload['payer_document_suffix']);
        $this->assertArrayNotHasKey('cpf', $checkout->provider_payload);

        $this->postJson('/api/v1/internal/billing/inter/inter-webhook-secret/rec', [
            'recs' => [[
                'idRec' => 'RR1026652320240821lab77511abf',
                'status' => 'APROVADA',
            ]],
        ])->assertOk()->assertJsonPath('processed', 1);

        $subscription = $workspace->subscription()->firstOrFail();
        $this->assertSame('completed', $checkout->fresh()->status);
        $this->assertSame('inter', $subscription->fresh()->payment_provider);
        $this->assertSame('RR1026652320240821lab77511abf', $subscription->fresh()->provider_subscription_id);

        $subscription->update(['current_period_ends_at' => now()->addDay()]);
        $this->assertSame(1, app(InterRenewalService::class)->generateDueCharges());
        $invoice = $workspace->invoices()->firstOrFail();
        $payment = $workspace->payments()->firstOrFail();
        $this->assertSame('pending', $invoice->status);
        $this->assertSame($invoice->provider_reference, $payment->provider_reference);

        $this->postJson('/api/v1/internal/billing/inter/inter-webhook-secret/cobr', [
            'cobrs' => [[
                'idRec' => 'RR1026652320240821lab77511abf',
                'txid' => $invoice->provider_reference,
                'status' => 'CONCLUIDA',
            ]],
        ])->assertOk()->assertJsonPath('processed', 1);

        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame('active', $subscription->fresh()->status);
        $this->assertNotNull($invoice->fresh()->paid_at);

        Http::assertSentCount(5);
        $recurrenceRequest = Http::recorded(fn (Request $request) => $request->url() === 'https://inter.test/pix/v2/rec')->first()[0];
        $this->assertSame('45164632481', $recurrenceRequest['vinculo']['devedor']['cpf']);
        $this->assertSame('MENSAL', $recurrenceRequest['calendario']['periodicidade']);
        $this->assertSame('99.00', $recurrenceRequest['valor']['valorRec']);
        $this->assertTrue($recurrenceRequest->hasHeader('Authorization', 'Bearer oauth-token'));
        $this->assertTrue($recurrenceRequest->hasHeader('x-conta-corrente', '123456'));
    }

    public function test_inter_rejects_cards_requires_payer_and_protects_webhook(): void
    {
        [, , $token] = $this->workspaceWithKey();

        $this->withToken($token)->postJson('/api/v1/billing/checkouts', [
            'plan_slug' => 'growth',
            'payment_method' => 'credit_card',
        ])->assertUnprocessable()->assertJsonValidationErrors(['payment_method', 'payer_name', 'payer_tax_id']);

        $this->withToken($token)->postJson('/api/v1/billing/checkouts', [
            'plan_slug' => 'growth',
            'payment_method' => 'pix',
        ])->assertUnprocessable()->assertJsonValidationErrors(['payer_name', 'payer_tax_id']);

        $this->postJson('/api/v1/internal/billing/inter/wrong-secret/rec', ['recs' => []])->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_inter_configuration_error_has_standard_api_envelope(): void
    {
        config()->set('billing.inter.client_id', null);
        [, , $token] = $this->workspaceWithKey();

        $this->withToken($token)->postJson('/api/v1/billing/checkouts', [
            'plan_slug' => 'growth',
            'payment_method' => 'pix',
            'payer_name' => 'Cliente Teste',
            'payer_tax_id' => '45164632481',
        ])->assertStatus(503)
            ->assertJsonPath('error.code', 'inter_not_configured')
            ->assertJsonStructure(['meta' => ['request_id']]);
    }

    public function test_inter_webhooks_use_the_shared_base_url_expected_by_inter(): void
    {
        config()->set('billing.inter.webhook_url', 'https://waflow.test/inter-callback');
        Http::fake([
            'https://inter.test/oauth/v2/token' => Http::response(['access_token' => 'oauth-token', 'expires_in' => 3600]),
            'https://inter.test/pix/v2/webhookrec' => Http::response([], 204),
            'https://inter.test/pix/v2/webhookcobr' => Http::response([], 204),
        ]);

        $this->artisan('billing:inter:configure-webhooks')->assertSuccessful();

        Http::assertSent(fn (Request $request) => $request->url() === 'https://inter.test/pix/v2/webhookrec'
            && $request['webhookUrl'] === 'https://waflow.test/inter-callback');
        Http::assertSent(fn (Request $request) => $request->url() === 'https://inter.test/pix/v2/webhookcobr'
            && $request['webhookUrl'] === 'https://waflow.test/inter-callback');
        Http::assertSentCount(3);
    }

    private function workspaceWithKey(): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $workspace = app(WorkspaceContext::class)->createForUser($user, 'Workspace Inter');
        $issued = ApiKey::issue($workspace, 'Inter test key');

        return [$user, $workspace, $issued['plain_text_token']];
    }
}
