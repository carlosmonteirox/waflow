<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesWorkspace;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkspaceController extends Controller
{
    use ResolvesWorkspace;

    public function show(Request $request): JsonResponse
    {
        $workspace = $this->workspace($request)->load('subscription.plan');

        return response()->json([
            'data' => [
                'id' => $workspace->id,
                'uuid' => $workspace->uuid,
                'name' => $workspace->name,
                'slug' => $workspace->slug,
                'timezone' => $workspace->timezone,
                'status' => $workspace->status,
                'plan' => $workspace->subscription?->plan?->only([
                    'name',
                    'slug',
                    'instance_limit',
                    'message_limit',
                    'webhook_limit',
                    'api_key_limit',
                    'portal_limit',
                ]),
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $workspace = $this->workspace($request);
        $attributes = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'timezone' => ['sometimes', 'string', 'max:80'],
        ]);

        $workspace->update($attributes);

        return response()->json(['data' => $workspace->fresh()]);
    }
}
