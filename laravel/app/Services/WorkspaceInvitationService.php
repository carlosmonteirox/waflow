<?php

namespace App\Services;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Models\WorkspaceMember;
use App\Notifications\WorkspaceInvitationNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WorkspaceInvitationService
{
    /** @return array{invitation: WorkspaceInvitation, plain_token: string} */
    public function invite(Workspace $workspace, string $email, string $role, ?User $inviter = null): array
    {
        $email = mb_strtolower(trim($email));
        if ($workspace->users()->where('email', $email)->exists()) {
            throw ValidationException::withMessages(['email' => 'Este usuário já participa do workspace.']);
        }

        $plainToken = Str::random(64);
        $invitation = DB::transaction(function () use ($workspace, $email, $role, $inviter, $plainToken): WorkspaceInvitation {
            Workspace::query()->whereKey($workspace->id)->lockForUpdate()->firstOrFail();
            $workspace->invitations()->where('email', $email)->where('status', 'pending')
                ->update(['status' => 'revoked', 'revoked_at' => now()]);

            return $workspace->invitations()->create([
                'invited_by' => $inviter?->id,
                'email' => $email,
                'role' => $role,
                'token_hash' => hash('sha256', $plainToken),
                'status' => 'pending',
                'expires_at' => now()->addDays(7),
            ]);
        });

        $this->notify($invitation, $plainToken);

        return ['invitation' => $invitation, 'plain_token' => $plainToken];
    }

    /** @return array{invitation: WorkspaceInvitation, plain_token: string} */
    public function resend(WorkspaceInvitation $invitation): array
    {
        $plainToken = Str::random(64);
        $invitation = DB::transaction(function () use ($invitation, $plainToken): WorkspaceInvitation {
            $locked = WorkspaceInvitation::query()->whereKey($invitation->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'pending', 409, 'Somente convites pendentes podem ser reenviados.');
            $locked->update(['token_hash' => hash('sha256', $plainToken), 'expires_at' => now()->addDays(7)]);

            return $locked->fresh('workspace');
        }, 3);
        $this->notify($invitation->fresh('workspace'), $plainToken);

        return ['invitation' => $invitation->fresh(), 'plain_token' => $plainToken];
    }

    public function accept(string $plainToken, User $user): WorkspaceMember
    {
        return DB::transaction(function () use ($plainToken, $user): WorkspaceMember {
            $invitation = WorkspaceInvitation::query()
                ->where('token_hash', hash('sha256', $plainToken))
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless($invitation->status === 'pending', 409, 'Este convite não está mais disponível.');
            abort_if($invitation->expires_at->isPast(), 410, 'Este convite expirou.');
            abort_unless(hash_equals($invitation->email, mb_strtolower($user->email)), 403, 'O convite pertence a outro endereço de e-mail.');
            $member = WorkspaceMember::query()->updateOrCreate(
                ['workspace_id' => $invitation->workspace_id, 'user_id' => $user->id],
                ['role' => $invitation->role, 'status' => 'active', 'joined_at' => now()],
            );
            $invitation->update(['status' => 'accepted', 'accepted_at' => now()]);

            return $member;
        }, 3);
    }

    public function revoke(WorkspaceInvitation $invitation): WorkspaceInvitation
    {
        return DB::transaction(function () use ($invitation): WorkspaceInvitation {
            $locked = WorkspaceInvitation::query()->whereKey($invitation->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'pending', 409, 'Somente convites pendentes podem ser revogados.');
            $locked->update(['status' => 'revoked', 'revoked_at' => now()]);

            return $locked->fresh();
        }, 3);
    }

    private function notify(WorkspaceInvitation $invitation, string $plainToken): void
    {
        $notification = new WorkspaceInvitationNotification($invitation->load('workspace'), $plainToken);
        $user = User::query()->where('email', $invitation->email)->first();
        $user
            ? $user->notify($notification)
            : Notification::route('mail', $invitation->email)->notify($notification);
    }
}
