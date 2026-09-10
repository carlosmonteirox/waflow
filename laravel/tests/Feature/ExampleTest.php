<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\User;
use App\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_registration_creates_user_workspace_and_subscription(): void
    {
        $response = $this->post('/register', [
            'name' => 'Carlos Monteiro',
            'workspace_name' => 'Acme Atendimento',
            'email' => 'carlos@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('verification.notice'));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('workspaces', ['name' => 'Acme Atendimento']);
        $this->assertDatabaseHas('subscriptions', ['status' => 'trialing']);
        $this->assertDatabaseCount('api_keys', 0);
    }

    public function test_email_verification_issues_the_initial_panel_api_key_once(): void
    {
        $this->post('/register', [
            'name' => 'Carlos Monteiro',
            'workspace_name' => 'Acme Atendimento',
            'email' => 'carlos@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $user = User::query()->where('email', 'carlos@example.com')->firstOrFail();
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addHour(),
            ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())],
        );

        $this->get($verificationUrl)
            ->assertRedirect(route('panel.api-keys'))
            ->assertSessionHas('created_api_token');

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->assertDatabaseCount('api_keys', 1);

        $this->get($verificationUrl)->assertRedirect(route('panel.api-keys'));
        $this->assertDatabaseCount('api_keys', 1);
    }

    public function test_authenticated_dashboard_returns_the_metronic_waflow_shell(): void
    {
        Http::fake([
            '*/api/health/ready' => Http::response(['status' => 'ok']),
        ]);

        $user = User::factory()->create();
        app(WorkspaceContext::class)->createForUser($user, 'Waflow Demo');

        $response = $this->actingAs($user)->get('/');

        $response
            ->assertOk()
            ->assertSee('Waflow')
            ->assertSee('waflow-panel', false)
            ->assertSee('waflow-page-props', false)
            ->assertSee('"page":"dashboard"', false)
            ->assertSee('"title":"Vis\\u00e3o geral"', false);
    }

    public function test_system_health_reports_dependencies(): void
    {
        Http::fake([
            '*/api/health/ready' => Http::response(['status' => 'ok']),
        ]);

        $response = $this->getJson('/api/v1/health');

        $response
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('service', 'waflow')
            ->assertJsonPath('checks.database.ok', true)
            ->assertJsonPath('checks.openwa.ok', true);
    }

    public function test_api_key_can_create_an_instance(): void
    {
        Http::fake([
            '*/api/sessions' => Http::response([
                'id' => '11111111-1111-4111-8111-111111111111',
                'name' => 'waflow-api-suporte-test',
                'status' => 'created',
            ], 201),
            '*/api/sessions/*/webhooks' => Http::response(['id' => 'webhook-1'], 201),
        ]);
        $user = User::factory()->create();
        $workspace = app(WorkspaceContext::class)->createForUser($user, 'Waflow API');
        $issued = ApiKey::issue($workspace, 'CI token');

        $response = $this
            ->withToken($issued['plain_text_token'])
            ->postJson('/api/v1/instances', [
                'name' => 'Suporte',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.name', 'Suporte')
            ->assertJsonPath('data.status', 'qr_pending')
            ->assertJsonPath('data.openwa_session_id', '11111111-1111-4111-8111-111111111111');

        $this->assertDatabaseHas('whatsapp_instances', [
            'workspace_id' => $workspace->id,
            'name' => 'Suporte',
        ]);
    }

    public function test_database_seeder_is_idempotent(): void
    {
        $this->artisan('db:seed')->assertExitCode(0);
        $this->artisan('db:seed')->assertExitCode(0);

        $this->assertDatabaseCount('users', 9);
        $this->assertDatabaseCount('workspaces', 2);
        $this->assertDatabaseCount('whatsapp_instances', 1);
        $this->assertDatabaseCount('partners', 1);
        $this->assertDatabaseCount('partner_members', 4);
        $this->assertDatabaseMissing('workspaces', ['uuid' => null]);

        $client = User::query()->where('email', 'client@waflow.local')->firstOrFail();
        $this->actingAs($client)->get(route('dashboard'))->assertOk();
    }
}
