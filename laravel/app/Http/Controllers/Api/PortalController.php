<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\Portal;
use App\Services\TrialAbuseService;
use App\Services\UsageLimiter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PortalController extends Controller
{
    use ResolvesWorkspace;

    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->workspace($request)->portals()->latest()->get()]);
    }

    public function store(Request $request, UsageLimiter $limiter, TrialAbuseService $abuse): JsonResponse
    {
        $workspace = $this->workspace($request);
        $limiter->ensurePortals($workspace);
        $attributes = $request->validate(['name' => ['required', 'string', 'max:120'], 'url' => ['required', 'url:http,https', 'max:2048']]);
        $domain = $abuse->domainFromUrl($attributes['url']);
        $abuse->claimTrialIdentifier($workspace, 'portal_domain', $domain);
        $plainToken = Str::random(48);
        $portal = $workspace->portals()->create([
            'name' => $attributes['name'],
            'url' => rtrim($attributes['url'], '/'),
            'domain' => $domain,
            'status' => 'pending',
            'verification_token' => hash('sha256', $plainToken),
        ]);

        return response()->json(['data' => $portal, 'verification_token' => $plainToken], 201);
    }

    public function show(Request $request, Portal $portal): JsonResponse
    {
        $this->ensureWorkspace($portal, $this->workspace($request));

        return response()->json(['data' => $portal]);
    }

    public function update(Request $request, Portal $portal): JsonResponse
    {
        $this->ensureWorkspace($portal, $this->workspace($request));
        $portal->update($request->validate(['name' => ['sometimes', 'string', 'max:120'], 'metadata' => ['nullable', 'array']]));

        return response()->json(['data' => $portal->fresh()]);
    }

    public function verify(Request $request, Portal $portal): JsonResponse
    {
        $this->ensureWorkspace($portal, $this->workspace($request));
        $attributes = $request->validate(['verification_token' => ['required', 'string', 'max:255']]);
        abort_unless(hash_equals($portal->verification_token, hash('sha256', $attributes['verification_token'])), 422, 'Token de verificação inválido.');
        $portal->update(['status' => 'verified', 'verified_at' => now()]);

        return response()->json(['data' => $portal->fresh()]);
    }

    public function destroy(Request $request, Portal $portal): JsonResponse
    {
        $this->ensureWorkspace($portal, $this->workspace($request));
        $portal->delete();

        return response()->json(status: 204);
    }
}
