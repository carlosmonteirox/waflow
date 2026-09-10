<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WorkspaceContext
{
    public function forUser(User $user): Workspace
    {
        $selectedUuid = session()->get('waflow.workspace_uuid');
        $query = $user->workspaces()
            ->with(['subscription.plan', 'members.user'])
            ->wherePivot('status', 'active');
        $workspace = $selectedUuid ? (clone $query)->where('workspaces.uuid', $selectedUuid)->first() : null;
        $workspace ??= $query->first();

        if ($workspace) {
            session()->put('waflow.workspace_uuid', $workspace->uuid);
        }

        return $workspace ?: $this->createForUser($user);
    }

    public function createForUser(User $user, ?string $workspaceName = null): Workspace
    {
        return DB::transaction(function () use ($user, $workspaceName): Workspace {
            $plan = $this->defaultPlan();
            $name = $workspaceName ?: 'Workspace de '.$user->name;

            $workspace = Workspace::query()->create([
                'uuid' => (string) Str::uuid(),
                'owner_id' => $user->id,
                'name' => $name,
                'slug' => $this->uniqueWorkspaceSlug($name),
                'timezone' => 'America/Sao_Paulo',
                'status' => 'active',
            ]);

            WorkspaceMember::query()->create([
                'workspace_id' => $workspace->id,
                'user_id' => $user->id,
                'role' => 'owner',
                'status' => 'active',
                'joined_at' => now(),
            ]);

            Subscription::query()->create([
                'workspace_id' => $workspace->id,
                'plan_id' => $plan->id,
                'status' => 'trialing',
                'billing_cycle' => 'monthly',
                'trial_ends_at' => now()->addDays((int) config('waflow.trial_days', 7)),
                'current_period_ends_at' => now()->addMonth(),
            ]);

            return $workspace->load(['subscription.plan', 'members.user']);
        });
    }

    public function defaultPlan(): Plan
    {
        return Plan::query()->firstOrCreate(
            ['slug' => 'growth'],
            [
                'name' => 'Growth',
                'monthly_price_cents' => 9900,
                'instance_limit' => 10,
                'message_limit' => 50000,
                'webhook_limit' => 10,
                'api_key_limit' => 5,
                'portal_limit' => 10,
                'features' => [
                    'Instâncias multi-tenant',
                    'Webhooks por workspace',
                    'API com chave por cliente',
                    'Fila e scheduler Laravel',
                ],
            ],
        );
    }

    private function uniqueWorkspaceSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'workspace';
        $slug = $base;
        $counter = 2;

        while (Workspace::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }
}
