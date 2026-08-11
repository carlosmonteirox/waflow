<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VerifyEmailController extends Controller
{
    public function __invoke(Request $request, int $id, string $hash): RedirectResponse
    {
        $user = User::query()->findOrFail($id);
        abort_unless(hash_equals($hash, sha1($user->getEmailForVerification())), 403);

        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        if (Auth::check() && Auth::id() === $user->id) {
            $workspace = $user->workspaces()->first();
            $issued = $workspace && ! $workspace->apiKeys()->exists()
                ? ApiKey::issue($workspace, 'Chave inicial', ['*'])
                : null;

            return redirect()
                ->route('panel.api-keys')
                ->with('status', 'E-mail confirmado. Sua avaliação está liberada.')
                ->with('created_api_token', $issued['plain_text_token'] ?? null);
        }

        return redirect()->route('login')->with('status', 'E-mail confirmado. Faça login para continuar.');
    }
}
