<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Services\RegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(Request $request): View
    {
        $partnerCode = Str::upper(trim((string) $request->query('partner')));
        $partner = Partner::query()
            ->where('referral_code', $partnerCode)
            ->where('status', 'active')
            ->first();

        return view('auth.register', ['partner' => $partner]);
    }

    public function store(Request $request, RegistrationService $registration): RedirectResponse
    {
        $request->merge([
            'email' => mb_strtolower(trim((string) $request->input('email'))),
            'partner_code' => filled($request->input('partner_code')) ? Str::upper(trim((string) $request->input('partner_code'))) : null,
        ]);
        $attributes = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'workspace_name' => ['nullable', 'string', 'max:120'],
            'partner_code' => ['nullable', 'string', 'size:'.Partner::REFERRAL_CODE_LENGTH, 'regex:/^[A-Z0-9]+$/', Rule::exists('partners', 'referral_code')->where('status', 'active')],
        ]);

        $result = $registration->register($attributes, $request, issueApiKey: false);
        Auth::login($result['user']);

        return redirect()->route('verification.notice');
    }
}
