<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\WorkspaceInvitation;
use App\Services\WorkspaceInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InvitationAcceptanceController extends Controller
{
    public function __invoke(Request $request, string $token, WorkspaceInvitationService $invitations): RedirectResponse
    {
        $invitation = WorkspaceInvitation::query()->where('token_hash', hash('sha256', $token))->firstOrFail();
        if (! $request->user()) {
            $request->session()->put('workspace_invitation_token', $token);
            $request->session()->put('url.intended', route('invitations.accept', $token));

            return redirect()->route('login')->with('status', 'Entre com '.$invitation->email.' para aceitar o convite.');
        }

        $member = $invitations->accept($token, $request->user());
        $request->session()->forget('workspace_invitation_token');
        $request->session()->put('waflow.workspace_uuid', $member->workspace->uuid);

        return redirect()->route('panel.team')->with('status', 'Convite aceito. Você já faz parte do workspace.');
    }
}
