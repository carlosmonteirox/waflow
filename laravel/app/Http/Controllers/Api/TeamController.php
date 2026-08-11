<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\WorkspaceInvitation;
use App\Models\WorkspaceMember;
use App\Services\WorkspaceInvitationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TeamController extends Controller
{
    use ResolvesWorkspace;

    private const ROLES = ['admin', 'support', 'developer', 'member'];

    public function members(Request $request): JsonResponse
    {
        $attributes = $request->validate([
            'role' => ['nullable', Rule::in(array_merge(['owner'], self::ROLES))],
            'status' => ['nullable', Rule::in(['active', 'suspended'])],
            'search' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $query = $this->workspace($request)->members()->with('user')->latest();
        $query->when(filled($attributes['role'] ?? null), fn ($query) => $query->where('role', $attributes['role']));
        $query->when(filled($attributes['status'] ?? null), fn ($query) => $query->where('status', $attributes['status']));
        $query->when(filled($attributes['search'] ?? null), function ($query) use ($attributes): void {
            $search = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $attributes['search']).'%';
            $query->whereHas('user', fn ($users) => $users->where('name', 'like', $search)->orWhere('email', 'like', $search));
        });

        return ApiResponse::paginated($query->paginate(ApiResponse::perPage($request)));
    }

    public function updateMember(Request $request, WorkspaceMember $member): JsonResponse
    {
        $this->ensureWorkspace($member, $this->workspace($request));
        abort_if($member->role === 'owner', 409, 'O proprietário do workspace não pode ser alterado.');
        $member->update($request->validate([
            'role' => ['sometimes', Rule::in(self::ROLES)],
            'status' => ['sometimes', Rule::in(['active', 'suspended'])],
        ]));

        return response()->json(['data' => $member->fresh('user')]);
    }

    public function removeMember(Request $request, WorkspaceMember $member): JsonResponse
    {
        $this->ensureWorkspace($member, $this->workspace($request));
        abort_if($member->role === 'owner', 409, 'O proprietário do workspace não pode ser removido.');
        $member->delete();

        return response()->json(status: 204);
    }

    public function invitations(Request $request): JsonResponse
    {
        $attributes = $request->validate([
            'status' => ['nullable', Rule::in(['pending', 'accepted', 'revoked', 'expired'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $query = $this->workspace($request)->invitations()->latest();
        $query->when(filled($attributes['status'] ?? null), fn ($query) => $query->where('status', $attributes['status']));

        return ApiResponse::paginated($query->paginate(ApiResponse::perPage($request)));
    }

    public function invite(Request $request, WorkspaceInvitationService $invitations): JsonResponse
    {
        $attributes = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', Rule::in(self::ROLES)],
        ]);
        $workspace = $this->workspace($request);
        $issued = $invitations->invite($workspace, $attributes['email'], $attributes['role'], $workspace->owner);

        return response()->json([
            'data' => $issued['invitation'],
            'accept_url' => route('invitations.accept', $issued['plain_token']),
        ], 201);
    }

    public function resend(Request $request, WorkspaceInvitation $invitation, WorkspaceInvitationService $invitations): JsonResponse
    {
        $this->ensureWorkspace($invitation, $this->workspace($request));
        $issued = $invitations->resend($invitation);

        return response()->json(['data' => $issued['invitation'], 'accept_url' => route('invitations.accept', $issued['plain_token'])]);
    }

    public function revoke(Request $request, WorkspaceInvitation $invitation, WorkspaceInvitationService $invitations): JsonResponse
    {
        $this->ensureWorkspace($invitation, $this->workspace($request));

        return response()->json(['data' => $invitations->revoke($invitation)]);
    }
}
