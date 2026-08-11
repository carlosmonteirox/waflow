<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Services\UsageLimiter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ApiKeyController extends Controller
{
    use ResolvesWorkspace;

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->workspace($request)->apiKeys()->latest()->get(),
        ]);
    }

    public function store(Request $request, UsageLimiter $limiter): JsonResponse
    {
        $workspace = $this->workspace($request);
        $limiter->ensureApiKeys($workspace);
        $attributes = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'abilities' => ['nullable', 'array'],
            'abilities.*' => ['string', 'max:80', Rule::in(array_merge(['*'], config('waflow.api.allowed_abilities', [])))],
        ]);

        $issued = ApiKey::issue($workspace, $attributes['name'], $attributes['abilities'] ?? ['*']);

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
