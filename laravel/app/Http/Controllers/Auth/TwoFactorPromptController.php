<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\LoginDestination;
use App\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TwoFactorPromptController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('auth.two_factor.user_id')) {
            return redirect()->route('login');
        }

        return view('auth.two-factor');
    }

    public function store(Request $request, TwoFactorService $twoFactor, LoginDestination $destination): RedirectResponse
    {
        $user = $this->pendingUser($request);
        $input = $request->input('verification_code', $request->input('code'));
        $plainCode = is_array($input) ? implode('', $input) : (string) $input;
        if (! preg_match('/^\d{6}$/', $plainCode)) {
            throw ValidationException::withMessages(['verification_code' => 'Informe os seis números do código.']);
        }
        if (! $twoFactor->verify($user, $plainCode)) {
            throw ValidationException::withMessages(['verification_code' => 'Código inválido.']);
        }

        $remember = (bool) $request->session()->pull('auth.two_factor.remember', false);
        $request->session()->forget('auth.two_factor.user_id');
        Auth::login($user, $remember);
        $request->session()->regenerate();

        $request->session()->forget('url.intended');

        return redirect()->to($destination->for($user));
    }

    public function resend(Request $request, TwoFactorService $twoFactor): RedirectResponse
    {
        $twoFactor->issue($this->pendingUser($request), $request, enforceCooldown: true);

        return back()->with('status', 'Enviamos um novo código para o seu e-mail.');
    }

    private function pendingUser(Request $request): User
    {
        $id = $request->session()->get('auth.two_factor.user_id');
        if (! $id) {
            abort(401, 'A sessão de autenticação expirou.');
        }

        return User::query()->findOrFail($id);
    }
}
