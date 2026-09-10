<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Contact;
use App\Models\MessageEvent;
use App\Models\User;
use App\Notifications\TwoFactorCodeNotification;
use App\Services\AutomationExecutor;
use App\Services\PlatformMaintenance;
use App\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Mockery;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

class PlatformExpansionTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_requires_a_six_digit_email_code_that_expires_in_five_minutes(): void
    {
        Notification::fake();
        Log::shouldReceive('channel')->never();
        $user = User::factory()->create([
            'email' => 'secure@example.com',
            'email_verified_at' => now(),
            'password' => Hash::make('password123'),
        ]);
        app(WorkspaceContext::class)->createForUser($user, 'Workspace Seguro');

        $this->post('/login', ['email' => $user->email, 'password' => 'password123'])
            ->assertRedirect(route('two-factor.challenge'));
        $this->assertGuest();

        $plainCode = null;
        Notification::assertSentTo($user, TwoFactorCodeNotification::class, function ($notification) use (&$plainCode): bool {
            $plainCode = $notification->code;

            return preg_match('/^\d{6}$/', $notification->code) === 1;
        });
        $issued = $user->twoFactorCodes()->latest()->firstOrFail();
        $this->assertLessThanOrEqual(301, now()->diffInSeconds($issued->expires_at));
        $this->assertGreaterThanOrEqual(299, now()->diffInSeconds($issued->expires_at));

        $this->post('/two-factor', ['verification_code' => $plainCode])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($issued->fresh()->consumed_at);
        $this->assertNotNull($user->fresh()->last_two_factor_at);
    }

    public function test_sandbox_login_logs_the_two_factor_code_when_explicitly_enabled(): void
    {
        Notification::fake();
        config()->set('waflow.two_factor.log_codes', true);

        $logger = Mockery::mock(LoggerInterface::class);
        $logger->shouldReceive('info')
            ->once()
            ->with('Waflow sandbox 2FA code issued.', Mockery::on(
                fn (array $context): bool => $context['email'] === 'sandbox@example.com'
                    && preg_match('/^\d{6}$/', $context['code']) === 1
                    && is_string($context['expires_at']),
            ));
        Log::shouldReceive('channel')->once()->with('mail')->andReturn($logger);

        $user = User::factory()->create([
            'email' => 'sandbox@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password123'])
            ->assertRedirect(route('two-factor.challenge'));
    }

    public function test_expired_and_incorrect_two_factor_codes_are_rejected(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'expired@example.com', 'password' => Hash::make('password123')]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password123']);
        $this->post('/two-factor', ['verification_code' => '000000'])
            ->assertSessionHasErrors('verification_code');
        $this->assertSame(1, $user->twoFactorCodes()->latest()->firstOrFail()->attempts);

        $user->twoFactorCodes()->latest()->firstOrFail()->update(['expires_at' => now()->subSecond()]);
        $this->post('/two-factor', ['verification_code' => '123456'])
            ->assertSessionHasErrors('verification_code');
        $this->assertGuest();
    }

    public function test_billing_checkout_supports_cards_and_pix_and_controls_renewal(): void
    {
        [, $workspace, $token] = $this->workspaceWithKey();

        foreach (['credit_card', 'debit_card', 'pix'] as $method) {
            $this->withToken($token)->postJson('/api/v1/billing/checkouts', [
                'plan_slug' => 'growth',
                'payment_method' => $method,
            ])->assertStatus(202)
                ->assertJsonPath('data.payment_method', $method)
                ->assertJsonPath('data.status', 'provider_pending')
                ->assertJsonPath('data.provider_payload.integration_status', 'provider_selection_pending');
        }

        $this->assertDatabaseCount('billing_checkout_sessions', 3);
        $this->assertSame(2, $workspace->checkoutSessions()->where('status', 'cancelled')->count());

        $this->withToken($token)->getJson('/api/v1/billing/summary')
            ->assertOk()
            ->assertJsonPath('data.provider_status', 'pending_selection')
            ->assertJsonPath('data.payment_methods.2', 'pix');

        $this->withToken($token)->postJson('/api/v1/billing/subscription/cancel', ['at_period_end' => true])
            ->assertOk()->assertJsonPath('data.cancel_at_period_end', true);
        $this->withToken($token)->postJson('/api/v1/billing/subscription/resume')
            ->assertOk()->assertJsonPath('data.cancel_at_period_end', false);
    }

    public function test_team_invitation_can_be_accepted_managed_and_revoked_through_the_api(): void
    {
        Notification::fake();
        [, $workspace, $token] = $this->workspaceWithKey();
        $invitee = User::factory()->create(['email' => 'member@example.com', 'email_verified_at' => now()]);

        $response = $this->withToken($token)->postJson('/api/v1/team/invitations', [
            'email' => $invitee->email,
            'role' => 'support',
        ])->assertCreated();

        $this->withToken($token)->getJson('/api/v1/team/invitations?status=pending&per_page=10')
            ->assertOk()->assertJsonPath('meta.pagination.total', 1);

        $this->actingAs($invitee)->get($response->json('accept_url'))
            ->assertRedirect(route('panel.team'));
        $this->assertSame($workspace->uuid, session('waflow.workspace_uuid'));
        $member = $workspace->members()->where('user_id', $invitee->id)->firstOrFail();
        $this->assertSame('support', $member->role);

        $this->withToken($token)->patchJson('/api/v1/team/members/'.$member->id, [
            'role' => 'developer',
            'status' => 'suspended',
        ])->assertOk()->assertJsonPath('data.role', 'developer')->assertJsonPath('data.status', 'suspended');
        $this->withToken($token)->deleteJson('/api/v1/team/members/'.$member->id)->assertNoContent();

        $second = $this->withToken($token)->postJson('/api/v1/team/invitations', [
            'email' => 'another@example.com',
            'role' => 'member',
        ])->assertCreated();
        $invitationUuid = $second->json('data.uuid');
        $this->withToken($token)->postJson('/api/v1/team/invitations/'.$invitationUuid.'/resend')->assertOk();
        $this->withToken($token)->deleteJson('/api/v1/team/invitations/'.$invitationUuid)
            ->assertOk()->assertJsonPath('data.status', 'revoked');
    }

    public function test_members_can_switch_workspaces_but_member_role_cannot_manage_the_team(): void
    {
        $memberUser = User::factory()->create(['email_verified_at' => now()]);
        $ownWorkspace = app(WorkspaceContext::class)->createForUser($memberUser, 'Workspace Pessoal');
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $teamWorkspace = app(WorkspaceContext::class)->createForUser($owner, 'Workspace da Equipe');
        $teamWorkspace->members()->create([
            'user_id' => $memberUser->id,
            'role' => 'member',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $this->actingAs($memberUser)->post(route('panel.workspaces.select', $teamWorkspace))
            ->assertRedirect(route('dashboard'));
        $this->assertSame($teamWorkspace->uuid, session('waflow.workspace_uuid'));
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Workspace da Equipe')
            ->assertSee('"member_role":"member"', false);

        $this->post(route('panel.team.store'), ['email' => 'blocked@example.com', 'role' => 'support'])
            ->assertForbidden();
        $this->assertDatabaseMissing('workspace_invitations', ['workspace_id' => $teamWorkspace->id, 'email' => 'blocked@example.com']);

        $this->post(route('panel.workspaces.select', $ownWorkspace))->assertRedirect(route('dashboard'));
        $this->assertSame($ownWorkspace->uuid, session('waflow.workspace_uuid'));
    }

    public function test_automation_executor_is_idempotent_and_records_results(): void
    {
        [, $workspace, $token] = $this->workspaceWithKey();
        $created = $this->withToken($token)->postJson('/api/v1/flows', [
            'name' => 'Classificar notícia',
            'trigger_type' => 'message_received',
            'steps' => [
                ['type' => 'condition', 'field' => 'trigger.body', 'operator' => 'contains', 'value' => 'notícia'],
                ['type' => 'action', 'action' => 'set_context', 'key' => 'classification', 'value' => 'editorial: {{trigger.body}}'],
            ],
        ])->assertCreated();
        $flowUuid = $created->json('data.uuid');

        $this->withToken($token)->patchJson('/api/v1/flows/'.$flowUuid, ['status' => 'active'])->assertOk();
        $executor = app(AutomationExecutor::class);
        $this->assertSame(1, $executor->dispatch($workspace, 'message_received', ['body' => 'Nova notícia'], 'source-123'));
        $this->assertSame(0, $executor->dispatch($workspace, 'message_received', ['body' => 'Nova notícia'], 'source-123'));

        $execution = $workspace->flowExecutions()->firstOrFail();
        $this->assertSame('completed', $execution->status);
        $this->assertSame('editorial: Nova notícia', data_get($execution->result, 'context.variables.classification'));
        $this->assertSame(1, $execution->flow->fresh()->run_count);

        $this->withToken($token)->getJson('/api/v1/flows/'.$flowUuid.'/executions')
            ->assertOk()->assertJsonPath('meta.pagination.total', 1);
        $this->withToken($token)->postJson('/api/v1/flows/'.$flowUuid.'/test', ['payload' => ['body' => 'notícia de teste']])
            ->assertStatus(202)->assertJsonPath('data.queued_executions', 1);
    }

    public function test_pagination_filters_and_error_envelope_are_consistent(): void
    {
        [, $workspace, $token] = $this->workspaceWithKey();
        Contact::query()->create(['workspace_id' => $workspace->id, 'name' => 'Ana', 'phone_number' => '5511']);
        Contact::query()->create(['workspace_id' => $workspace->id, 'name' => 'Bruno', 'phone_number' => '5522']);
        Contact::query()->create(['workspace_id' => $workspace->id, 'name' => 'Carla', 'phone_number' => '5533']);

        $this->withToken($token)->withHeader('X-Request-Id', 'test-request-123')
            ->getJson('/api/v1/contacts?search=a&per_page=2')
            ->assertOk()
            ->assertHeader('X-Request-Id', 'test-request-123')
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.pagination.per_page', 2)
            ->assertJsonStructure(['links' => ['first', 'last', 'prev', 'next']]);

        $this->withToken($token)->withHeader('X-Request-Id', 'validation-123')
            ->getJson('/api/v1/contacts?per_page=101')
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonPath('meta.request_id', 'validation-123')
            ->assertJsonValidationErrors('per_page');
    }

    public function test_bulk_messages_reserve_quota_once_and_ignore_inbound_usage(): void
    {
        Http::fake(['*/api/sessions/*/messages/send-text' => Http::response(['messageId' => 'provider-id'], 201)]);
        [, $workspace, $token] = $this->workspaceWithKey();
        config()->set('waflow.trial_limits.messages', 2);
        $instance = $workspace->instances()->create([
            'name' => 'Redação',
            'session_name' => 'redacao-bulk',
            'openwa_session_id' => '44444444-4444-4444-8444-444444444444',
            'status' => 'connected',
        ]);
        MessageEvent::query()->create([
            'workspace_id' => $workspace->id,
            'whatsapp_instance_id' => $instance->id,
            'direction' => 'inbound',
            'status' => 'received',
            'from' => '5511999999999',
            'payload' => ['text' => 'entrada'],
        ]);

        $this->withToken($token)->postJson('/api/v1/messages/bulk', ['messages' => [
            ['instance_uuid' => $instance->uuid, 'to' => '551100000001', 'type' => 'text', 'text' => 'A', 'idempotency_key' => 'bulk-a'],
            ['instance_uuid' => $instance->uuid, 'to' => '551100000002', 'type' => 'text', 'text' => 'B', 'idempotency_key' => 'bulk-b'],
        ]])->assertAccepted()->assertJsonCount(2, 'data');

        $this->assertSame(2, $workspace->messageEvents()->where('direction', 'outbound')->count());
        $this->withToken($token)->postJson('/api/v1/messages', [
            'instance_uuid' => $instance->uuid,
            'to' => '551100000003',
            'type' => 'text',
            'text' => 'C',
        ])->assertTooManyRequests()->assertJsonPath('code', 'rate_limit_exceeded');
    }

    public function test_paid_portal_quota_uses_its_own_plan_limit(): void
    {
        [, $workspace, $token] = $this->workspaceWithKey();
        $workspace->subscription()->update(['status' => 'active']);
        $workspace->subscription->plan()->update(['instance_limit' => 1, 'portal_limit' => 2]);

        foreach (['one.example.com', 'two.example.com'] as $index => $domain) {
            $this->withToken($token)->postJson('/api/v1/portals', [
                'name' => 'Portal '.($index + 1),
                'url' => 'https://'.$domain,
            ])->assertCreated();
        }

        $this->withToken($token)->postJson('/api/v1/portals', [
            'name' => 'Portal excedente',
            'url' => 'https://three.example.com',
        ])->assertTooManyRequests()->assertJsonPath('code', 'rate_limit_exceeded');
        $this->assertDatabaseCount('portals', 2);
    }

    public function test_scheduler_maintenance_expires_checkouts_invitations_and_period_end_cancellations(): void
    {
        [$user, $workspace] = $this->workspaceWithKey();
        $subscription = $workspace->subscription;
        $subscription->update([
            'status' => 'active',
            'cancel_at_period_end' => true,
            'current_period_ends_at' => now()->subMinute(),
        ]);
        $checkout = $workspace->checkoutSessions()->create([
            'plan_id' => $subscription->plan_id,
            'created_by' => $user->id,
            'payment_method' => 'pix',
            'status' => 'pending',
            'amount_cents' => 9900,
            'currency' => 'BRL',
            'expires_at' => now()->subMinute(),
        ]);
        $invitation = $workspace->invitations()->create([
            'invited_by' => $user->id,
            'email' => 'late@example.com',
            'role' => 'member',
            'token_hash' => hash('sha256', 'expired-invitation'),
            'status' => 'pending',
            'expires_at' => now()->subMinute(),
        ]);
        $user->twoFactorCodes()->create([
            'code_hash' => hash('sha256', 'old-code'),
            'expires_at' => now()->subDays(2),
        ]);

        $result = app(PlatformMaintenance::class)->expireTemporaryRecords();

        $this->assertSame(['checkouts' => 1, 'invitations' => 1, 'subscriptions' => 1, 'two_factor_codes' => 1, 'webhook_deliveries' => 0], $result);
        $this->assertSame('expired', $checkout->fresh()->status);
        $this->assertSame('expired', $invitation->fresh()->status);
        $this->assertSame('cancelled', $subscription->fresh()->status);
        $this->assertNotNull($subscription->fresh()->cancelled_at);
    }

    public function test_every_panel_page_has_a_dedicated_blade_view_and_vue_payload(): void
    {
        Http::fake(['*/api/health/ready' => Http::response(['status' => 'ok'])]);
        [$user] = $this->workspaceWithKey();

        $pages = [
            'dashboard' => 'DashboardPage.vue',
            'panel.instances' => 'InstancesPage.vue',
            'panel.flows' => 'FlowsPage.vue',
            'panel.messages' => 'MessagesPage.vue',
            'panel.contacts' => 'ContactsPage.vue',
            'panel.portals' => 'PortalsPage.vue',
            'panel.developers' => 'DevelopersPage.vue',
            'panel.webhooks' => 'WebhooksPage.vue',
            'panel.api-keys' => 'ApiKeysPage.vue',
            'panel.team' => 'TeamPage.vue',
            'panel.billing' => 'BillingPage.vue',
            'panel.settings' => 'SettingsPage.vue',
        ];

        foreach ($pages as $routeName => $componentFile) {
            $pageName = str($componentFile)->before('Page.vue')->kebab()->toString();

            $this->assertFileExists(resource_path("js/panel/pages/{$componentFile}"));
            $this->actingAs($user)->get(route($routeName))
                ->assertOk()
                ->assertSee('waflow-panel', false)
                ->assertSee("data-waflow-page=\"{$pageName}\"", false)
                ->assertSee('waflow-page-props', false);
        }

        $this->assertFileExists(resource_path('js/panel/PanelShell.vue'));
        $this->assertFileDoesNotExist(resource_path('js/components/PanelApp.vue'));
    }

    public function test_panel_lists_filter_sort_and_preserve_their_query_state(): void
    {
        [$user, $workspace] = $this->workspaceWithKey();
        Contact::query()->create(['workspace_id' => $workspace->id, 'name' => 'Ana Lima', 'phone_number' => '551100000001']);
        Contact::query()->create(['workspace_id' => $workspace->id, 'name' => 'Bruno Alves', 'phone_number' => '551100000002']);
        MessageEvent::query()->create([
            'workspace_id' => $workspace->id,
            'direction' => 'outbound',
            'status' => 'failed',
            'to' => '551100000001',
            'payload' => ['text' => 'Falha de teste'],
        ]);
        MessageEvent::query()->create([
            'workspace_id' => $workspace->id,
            'direction' => 'inbound',
            'status' => 'delivered',
            'from' => '551100000002',
            'payload' => ['text' => 'Mensagem entregue'],
        ]);

        $this->actingAs($user)->get(route('panel.contacts', ['q' => 'ana', 'sort' => 'name', 'direction' => 'asc']))
            ->assertOk()
            ->assertSee('Ana Lima')
            ->assertDontSee('Bruno Alves')
            ->assertSee('"search":"ana"', false)
            ->assertSee('"sort":"name"', false);

        $this->actingAs($user)->get(route('panel.messages', ['status' => 'failed', 'sort' => 'status', 'direction' => 'asc']))
            ->assertOk()
            ->assertSee('Falha de teste')
            ->assertDontSee('Mensagem entregue')
            ->assertSee('"status":"failed"', false)
            ->assertSee('"sort":"status"', false);
    }

    public function test_waflow_openapi_document_is_public_and_valid_yaml(): void
    {
        $this->get('/api/openapi.yaml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/yaml; charset=UTF-8')
            ->assertSee('openapi: 3.1.0')
            ->assertSee('/team/invitations:');
    }

    private function workspaceWithKey(): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $workspace = app(WorkspaceContext::class)->createForUser($user, 'Workspace Teste');
        $issued = ApiKey::issue($workspace, 'Test key');

        return [$user, $workspace, $issued['plain_text_token']];
    }
}
