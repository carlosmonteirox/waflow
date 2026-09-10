<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Services\RegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function register(Request $request, RegistrationService $registration): JsonResponse
    {
        $request->merge([
            'email' => mb_strtolower(trim((string) $request->input('email'))),
            'partner_code' => filled($request->input('partner_code')) ? Str::upper(trim((string) $request->input('partner_code'))) : null,
        ]);
        $attributes = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'workspace_name' => ['nullable', 'string', 'max:120'],
            'site_url' => ['nullable', 'url:http,https', 'max:2048'],
            'portal_name' => ['nullable', 'string', 'max:120'],
            'device_fingerprint' => ['nullable', 'string', 'min:16', 'max:255'],
            'partner_code' => ['nullable', 'string', 'size:'.Partner::REFERRAL_CODE_LENGTH, 'regex:/^[A-Z0-9]+$/', Rule::exists('partners', 'referral_code')->where('status', 'active')],
        ]);

        $result = $registration->register($attributes, $request);

        return response()->json([
            'data' => [
                'user' => $result['user']->only(['id', 'name', 'email']),
                'workspace' => $result['workspace']->only(['id', 'name', 'slug']),
                'api_key' => $result['issued']['plain_text_token'],
                'trial_ends_at' => $result['workspace']->subscription?->trial_ends_at?->toIso8601String(),
                'portal_verification_token' => $result['portalToken'],
                'email_verification_required' => true,
            ],
        ], 201);
    }

    public function resendVerification(Request $request): JsonResponse
    {
        $user = $request->attributes->get('waflow.workspace')?->owner;
        if ($user && ! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        return response()->json(['message' => 'Se a conta estiver pendente, um novo link será enviado.']);
    }
}
