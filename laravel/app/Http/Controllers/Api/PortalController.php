<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\Portal;
use App\Services\TrialAbuseService;
use App\Services\UsageLimiter;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PortalController extends Controller
{
    use ResolvesWorkspace;

    public function index(Request $request): JsonResponse
    {
        $attributes = $request->validate([
            'status' => ['nullable', 'string', 'max:40'],
            'search' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $query = $this->workspace($request)->portals()->latest();
        $query->when(filled($attributes['status'] ?? null), fn ($query) => $query->where('status', $attributes['status']));
        $query->when(filled($attributes['search'] ?? null), function ($query) use ($attributes): void {
            $search = '%'.$attributes['search'].'%';
            $query->where(fn ($query) => $query->where('name', 'like', $search)->orWhere('domain', 'like', $search));
        });

        return ApiResponse::paginated($query->paginate(ApiResponse::perPage($request)));
    }

    public function store(Request $request, UsageLimiter $limiter, TrialAbuseService $abuse): JsonResponse
    {
        $workspace = $this->workspace($request);
        $attributes = $request->validate(['name' => ['required', 'string', 'max:120'], 'url' => ['required', 'url:http,https', 'max:2048']]);
        $domain = $abuse->domainFromUrl($attributes['url']);
        $plainToken = Str::random(48);
        $portal = $limiter->serialized($workspace, function () use ($workspace, $limiter, $abuse, $attributes, $domain, $plainToken): Portal {
            $limiter->ensurePortals($workspace);
            $abuse->claimTrialIdentifier($workspace, 'portal_domain', $domain);

            return $workspace->portals()->create([
                'name' => $attributes['name'],
                'url' => rtrim($attributes['url'], '/'),
                'domain' => $domain,
                'status' => 'pending',
                'verification_token' => hash('sha256', $plainToken),
            ]);
        });

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
