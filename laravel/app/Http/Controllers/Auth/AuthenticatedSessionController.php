<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request, TwoFactorService $twoFactor): RedirectResponse
    {
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::validate($credentials)) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'As credenciais informadas não conferem.']);
        }

        $user = User::query()->where('email', mb_strtolower($credentials['email']))->firstOrFail();
        abort_if($user->blocked_at, 403, 'Esta conta está bloqueada.');

        $request->session()->regenerate();
        if ($user->two_factor_enabled) {
            $request->session()->put([
                'auth.two_factor.user_id' => $user->id,
                'auth.two_factor.remember' => $request->boolean('remember'),
            ]);
            $twoFactor->issue($user, $request);

            return redirect()->route('two-factor.challenge');
        }

        Auth::login($user, $request->boolean('remember'));

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
