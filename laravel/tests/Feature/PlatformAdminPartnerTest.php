<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\PartnerMember;
use App\Models\User;
use App\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlatformAdminPartnerTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_has_global_console_and_regular_user_is_denied(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $regular = User::factory()->create();

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('"console":"admin"', false)
            ->assertSee('"page":"admin-dashboard"', false);

        $this->actingAs($regular)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($regular)->get(route('partner.dashboard'))->assertForbidden();
    }

    public function test_admin_can_create_a_partner_and_owner_can_access_partner_console(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $owner = User::factory()->create(['email' => 'owner@partner.test']);

        $this->actingAs($admin)->post(route('admin.partners.store'), [
            'name' => 'Parceiro Centro-Oeste',
            'owner_email' => $owner->email,
            'commission_percent' => 25,
        ])->assertRedirect();

        $partner = Partner::query()->where('name', 'Parceiro Centro-Oeste')->firstOrFail();
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{6}$/', $partner->referral_code);
        $this->assertDatabaseHas('partner_members', [
            'partner_id' => $partner->id,
            'user_id' => $owner->id,
            'role' => 'owner',
        ]);

        $this->actingAs($owner)->get(route('partner.dashboard'))
            ->assertOk()
            ->assertSee('Visão geral do parceiro')
            ->assertSee($partner->referral_code);
    }

    public function test_each_account_type_is_sent_to_its_own_console_after_login(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin-type@test.local',
            'password' => 'password123',
            'is_platform_admin' => true,
            'two_factor_enabled' => false,
        ]);
        $partnerUser = User::factory()->create([
            'email' => 'partner-type@test.local',
            'password' => 'password123',
            'two_factor_enabled' => false,
        ]);
        $client = User::factory()->create([
            'email' => 'client-type@test.local',
            'password' => 'password123',
            'two_factor_enabled' => false,
        ]);
        $partner = Partner::query()->create([
            'owner_id' => $partnerUser->id,
            'name' => 'Parceiro de Login',
            'slug' => 'parceiro-de-login',
            'status' => 'active',
            'referral_code' => 'LOG123',
        ]);
        PartnerMember::query()->create([
            'partner_id' => $partner->id,
            'user_id' => $partnerUser->id,
            'role' => 'owner',
            'status' => 'active',
        ]);

        $this->post(route('login.store'), ['email' => $admin->email, 'password' => 'password123'])
            ->assertRedirect(route('admin.dashboard'));
        $this->post(route('logout'));

        $this->post(route('login.store'), ['email' => $partnerUser->email, 'password' => 'password123'])
            ->assertRedirect(route('partner.dashboard'));
        $this->post(route('logout'));

        $this->withSession(['url.intended' => route('admin.dashboard')])
            ->post(route('login.store'), ['email' => $client->email, 'password' => 'password123'])
            ->assertRedirect(route('dashboard'));
    }

    public function test_partner_referral_registration_assigns_new_workspace_to_partner(): void
    {
        $owner = User::factory()->create();
        $partner = Partner::query()->create([
            'uuid' => (string) Str::uuid(),
            'owner_id' => $owner->id,
            'name' => 'Parceiro de Teste',
            'slug' => 'parceiro-de-teste',
            'status' => 'active',
            'referral_code' => 'PAR123',
            'commission_percent' => 20,
        ]);
        PartnerMember::query()->create([
            'partner_id' => $partner->id,
            'user_id' => $owner->id,
            'role' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $this->post(route('register.store'), [
            'name' => 'Cliente Indicado',
            'workspace_name' => 'Empresa Indicada',
            'email' => 'cliente@indicado.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'partner_code' => $partner->referral_code,
        ])->assertRedirect(route('verification.notice'));

        $workspace = User::query()->where('email', 'cliente@indicado.test')->firstOrFail()->workspaces()->firstOrFail();
        $this->assertDatabaseHas('partner_workspaces', [
            'partner_id' => $partner->id,
            'workspace_id' => $workspace->id,
            'status' => 'active',
        ]);
    }

    public function test_client_navigation_uses_sites_and_domains_label(): void
    {
        Http::fake(['*/api/health/ready' => Http::response(['status' => 'ok'])]);
        $user = User::factory()->create();
        app(WorkspaceContext::class)->createForUser($user, 'Empresa Cliente');

        $this->actingAs($user)->get(route('panel.portals'))
            ->assertOk()
            ->assertSee('Sites/Domínios');
    }

    public function test_suspended_partner_code_cannot_receive_new_registrations(): void
    {
        $owner = User::factory()->create();
        Partner::query()->create([
            'owner_id' => $owner->id,
            'name' => 'Parceiro Suspenso',
            'slug' => 'parceiro-suspenso',
            'status' => 'suspended',
            'referral_code' => 'SUS123',
        ]);

        $this->post(route('register.store'), [
            'name' => 'Cliente Bloqueado',
            'workspace_name' => 'Empresa Bloqueada',
            'email' => 'cliente@bloqueado.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'partner_code' => 'sus123',
        ])->assertSessionHasErrors('partner_code');

        $this->assertDatabaseMissing('users', ['email' => 'cliente@bloqueado.test']);
    }
}
