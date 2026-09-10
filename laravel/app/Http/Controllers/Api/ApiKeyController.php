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
use Illuminate\Validation\ValidationException;

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
            'abilities' => ['nullable', 'array', 'min:1'],
            'abilities.*' => ['string', 'max:80', Rule::in(array_merge(['*'], config('waflow.api.allowed_abilities', [])))],
        ]);
        $issuerAbilities = array_values(array_unique($request->attributes->get('waflow.api_key')?->abilities ?? []));
        $requestedAbilities = array_values(array_unique($attributes['abilities'] ?? $issuerAbilities));
        if (! $this->canDelegate($issuerAbilities, $requestedAbilities)) {
            throw ValidationException::withMessages([
                'abilities' => 'A nova chave não pode receber permissões que a chave atual não possui.',
            ]);
        }

        $issued = $limiter->serialized($workspace, function () use ($workspace, $limiter, $attributes, $requestedAbilities): array {
            $limiter->ensureApiKeys($workspace);

            return ApiKey::issue($workspace, $attributes['name'], $requestedAbilities);
        });

        return response()->json([
            'data' => $issued['model'],
            'plain_text_token' => $issued['plain_text_token'],
        ], 201);
    }

    /** @param array<int, string> $issuer @param array<int, string> $requested */
    private function canDelegate(array $issuer, array $requested): bool
    {
        if (in_array('*', $issuer, true)) {
            return true;
        }

        foreach ($requested as $ability) {
            $prefix = str($ability)->before(':').':*';
            if ($ability === '*' || (! in_array($ability, $issuer, true) && ! in_array($prefix, $issuer, true))) {
                return false;
            }
        }

        return true;
    }

    public function destroy(Request $request, int $apiKey): JsonResponse
    {
        $this->workspace($request)->apiKeys()->whereKey($apiKey)->firstOrFail()->delete();

        return response()->json(status: 204);
    }
}
