<?php

namespace Database\Seeders;

use App\Models\Partner;
use App\Models\PartnerMember;
use App\Models\User;
use App\Models\WorkspaceMember;
use App\Services\WorkspaceContext;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $context = app(WorkspaceContext::class);

        $admin = $this->sandboxUser('admin@waflow.local', 'Waflow Admin', isPlatformAdmin: true);
        $partnerOwner = $this->sandboxUser('partner@waflow.local', 'Parceiro Proprietário');
        $partnerAdmin = $this->sandboxUser('partner.admin@waflow.local', 'Parceiro Administrador');
        $partnerSales = $this->sandboxUser('partner.sales@waflow.local', 'Parceiro Comercial');
        $partnerSupport = $this->sandboxUser('partner.support@waflow.local', 'Parceiro Suporte');
        $clientOwner = $this->sandboxUser('client@waflow.local', 'Cliente Proprietário');
        $clientAdmin = $this->sandboxUser('client.admin@waflow.local', 'Cliente Administrador');
        $clientDeveloper = $this->sandboxUser('client.developer@waflow.local', 'Cliente Desenvolvedor');
        $clientMember = $this->sandboxUser('client.member@waflow.local', 'Cliente Membro');

        $adminWorkspace = $admin->workspaces()->wherePivot('status', 'active')->first()
            ?: $context->createForUser($admin, 'Waflow Admin Demo');
        $clientWorkspace = $clientOwner->workspaces()->wherePivot('status', 'active')->first()
            ?: $context->createForUser($clientOwner, 'Waflow Cliente Demo');

        foreach ([
            [$clientAdmin, 'admin'],
            [$clientDeveloper, 'developer'],
            [$clientMember, 'member'],
        ] as [$account, $role]) {
            WorkspaceMember::query()->updateOrCreate(
                ['workspace_id' => $clientWorkspace->id, 'user_id' => $account->id],
                ['role' => $role, 'status' => 'active', 'joined_at' => now()],
            );
        }

        $clientWorkspace->instances()->firstOrCreate(
            ['session_name' => 'waflow-demo-atendimento'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Atendimento',
                'phone_number' => '+55 62 99999-9999',
                'status' => 'disconnected',
            ],
        );

        $partner = Partner::query()->firstOrNew(['slug' => 'waflow-partner-demo']);
        if (! $partner->exists) {
            $partner->uuid = (string) Str::uuid();
        }
        $partner->fill([
            'owner_id' => $partnerOwner->id,
            'name' => 'Waflow Partner Demo',
            'brand_name' => 'Waflow Partner',
            'status' => 'active',
            'commission_percent' => 20,
            'referral_code' => '7K3M9Q',
        ])->save();

        foreach ([
            [$partnerOwner, 'owner'],
            [$partnerAdmin, 'admin'],
            [$partnerSales, 'sales'],
            [$partnerSupport, 'support'],
        ] as [$account, $role]) {
            PartnerMember::query()->updateOrCreate(
                ['partner_id' => $partner->id, 'user_id' => $account->id],
                ['role' => $role, 'status' => 'active', 'joined_at' => now()],
            );
        }

        PartnerMember::query()->where('partner_id', $partner->id)->where('user_id', $admin->id)->delete();
        if ($adminWorkspace->name === 'Waflow Demo') {
            $partner->workspaces()->detach($adminWorkspace->id);
        }
        $partner->workspaces()->syncWithoutDetaching([
            $clientWorkspace->id => ['status' => 'active', 'referred_at' => now()],
        ]);
    }

    private function sandboxUser(string $email, string $name, bool $isPlatformAdmin = false): User
    {
        $user = User::query()->firstOrNew(['email' => $email]);
        $user->forceFill([
            'name' => $name,
            'password' => 'password123',
            'email_verified_at' => $user->email_verified_at ?: now(),
            'two_factor_enabled' => true,
            'is_platform_admin' => $isPlatformAdmin,
        ])->save();

        return $user;
    }
}
