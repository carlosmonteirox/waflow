<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Services\UsageLimiter;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ApiKeyController extends Controller
{
    use ResolvesWorkspace;

    public function index(Request $request): JsonResponse
    {
        $attributes = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'active' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $query = $this->workspace($request)->apiKeys()->latest();
        $query->when(filled($attributes['search'] ?? null), fn ($query) => $query->where('name', 'like', '%'.$attributes['search'].'%'));
        $query->when(array_key_exists('active', $attributes), function ($query) use ($attributes): void {
            $attributes['active']
                ? $query->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                : $query->whereNotNull('expires_at')->where('expires_at', '<=', now());
        });

        return ApiResponse::paginated($query->paginate(ApiResponse::perPage($request)));
    }

    public function store(Request $request, UsageLimiter $limiter): JsonResponse
    {
        $workspace = $this->workspace($request);
        $attributes = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'abilities' => ['nullable', 'array'],
            'abilities.*' => ['string', 'max:80', Rule::in(array_merge(['*'], config('waflow.api.allowed_abilities', [])))],
        ]);

        $issued = $limiter->serialized($workspace, function () use ($workspace, $limiter, $attributes): array {
            $limiter->ensureApiKeys($workspace);

            return ApiKey::issue($workspace, $attributes['name'], $attributes['abilities'] ?? ['*']);
        });

        return response()->json([
            'data' => $issued['model'],
            'plain_text_token' => $issued['plain_text_token'],
        ], 201);
    }

    public function destroy(Request $request, int $apiKey): JsonResponse
    {
        $this->workspace($request)->apiKeys()->whereKey($apiKey)->firstOrFail()->delete();

        return response()->json(status: 204);
    }
}
